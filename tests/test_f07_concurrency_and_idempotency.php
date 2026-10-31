<?php
/**
 * Mini ERP Stock & Sales — F07 Concurrency & Idempotency Deep Verification Suite
 *
 * Covers:
 * 1. Real parallel OS process concurrency testing for invoice creation:
 *    - Daily sequential numbering race condition under concurrent load.
 *    - Limited stock contention (preventing over-selling / negative stock).
 *    - Atomic rollback and absence of orphan partial records.
 * 2. Idempotency payload sensitivity:
 *    - Exact payload replay (is_replay = TRUE, zero stock change).
 *    - Payload alteration rejection (customer, warehouse, product, quantity, discount) -> IDEMPOTENCY_CONFLICT.
 * 3. Complete database cleanup & verification of zero collateral damage.
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('Customer_model');
$CI->load->model('Sales_invoice_model');
$CI->load->model('User_model');

$db = $CI->db;

$passed = 0;
$failed = 0;

function assert_test($title, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$title}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$title}" . ($details ? " --> {$details}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo " F07 CONCURRENCY & IDEMPOTENCY VERIFICATION SUITE\n";
echo "====================================================================\n\n";

// Initial counts snapshot
$initial_stock_count     = $db->count_all('warehouse_stock');
$initial_movements_count = $db->count_all('stock_movements');
$initial_invoices_count  = $db->count_all('sales_invoices');
$initial_customers_count = $db->count_all('customers');

echo "Initial DB State: Stock={$initial_stock_count}, Movements={$initial_movements_count}, Invoices={$initial_invoices_count}, Customers={$initial_customers_count}\n\n";

// -------------------------------------------------------------------------
// SECTION 0: FIXTURES SETUP
// -------------------------------------------------------------------------
echo "--- SECTION 0: TEST FIXTURES SETUP ---\n";

$admin_user = $CI->User_model->get_by_username_for_auth('admin');

// Warehouse W1 & W2
$db->insert('warehouses', array('code' => 'WH-CONC-1', 'name' => 'مستودع التزامن 1', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$wh1_id = $db->insert_id();

$db->insert('warehouses', array('code' => 'WH-CONC-2', 'name' => 'مستودع التزامن 2', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$wh2_id = $db->insert_id();

// Customer C1 & C2
$db->insert('customers', array('name' => 'عميل التزامن 1', 'phone' => '0509990001', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$cust1_id = $db->insert_id();

$db->insert('customers', array('name' => 'عميل التزامن 2', 'phone' => '0509990002', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$cust2_id = $db->insert_id();

// Category & Product P1 & P2
$db->insert('categories', array('name' => 'تصنيف التزامن', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$cat_id = $db->insert_id();

$db->insert('products', array('category_id' => $cat_id, 'sku' => 'SKU-CONC-1', 'name' => 'منتج التزامن 1', 'sale_price' => 100.00, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$p1_id = $db->insert_id();

$db->insert('products', array('category_id' => $cat_id, 'sku' => 'SKU-CONC-2', 'name' => 'منتج التزامن 2', 'sale_price' => 50.00, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$p2_id = $db->insert_id();

// Initial Stock for WH1: P1 has 100 units, P2 has 50 units
$db->insert('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p1_id, 'quantity' => 100, 'reorder_level' => 5, 'created_at' => date('Y-m-d H:i:s')));
$db->insert('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p2_id, 'quantity' => 50, 'reorder_level' => 5, 'created_at' => date('Y-m-d H:i:s')));

// Initial Stock for WH2: P1 has 20 units
$db->insert('warehouse_stock', array('warehouse_id' => $wh2_id, 'product_id' => $p1_id, 'quantity' => 20, 'reorder_level' => 5, 'created_at' => date('Y-m-d H:i:s')));

assert_test("Fixtures initialized successfully", $wh1_id > 0 && $wh2_id > 0 && $cust1_id > 0 && $p1_id > 0);
echo "\n";

// -------------------------------------------------------------------------
// SECTION 1: REAL MULTI-PROCESS CONCURRENCY (DAILY NUMBERING & STOCK LOCKING)
// -------------------------------------------------------------------------
echo "--- SECTION 1: MULTI-PROCESS CONCURRENCY TESTING ---\n";

$worker_script = __DIR__ . '/f07_concurrent_worker.php';
$in_file_1  = sys_get_temp_dir() . '/in_worker1_' . uniqid() . '.json';
$in_file_2  = sys_get_temp_dir() . '/in_worker2_' . uniqid() . '.json';
$out_file_1 = sys_get_temp_dir() . '/out_worker1_' . uniqid() . '.json';
$out_file_2 = sys_get_temp_dir() . '/out_worker2_' . uniqid() . '.json';

// Test 1.1: Concurrent Invoice Numbering (Both request 1 unit simultaneously on date 2026-11-20)
$conc_date = '2026-11-20';

$payload_1 = json_encode(array(
    'user_id'             => $admin_user->id,
    'customer_id'         => $cust1_id,
    'warehouse_id'        => $wh1_id,
    'invoice_date'        => $conc_date,
    'discount_percentage' => 0.00,
    'idempotency_key'     => 'conc_num_1_' . bin2hex(random_bytes(8)),
    'items'               => array(array('product_id' => $p1_id, 'quantity' => 1))
));
file_put_contents($in_file_1, $payload_1);

$payload_2 = json_encode(array(
    'user_id'             => $admin_user->id,
    'customer_id'         => $cust1_id,
    'warehouse_id'        => $wh1_id,
    'invoice_date'        => $conc_date,
    'discount_percentage' => 0.00,
    'idempotency_key'     => 'conc_num_2_' . bin2hex(random_bytes(8)),
    'items'               => array(array('product_id' => $p1_id, 'quantity' => 1))
));
file_put_contents($in_file_2, $payload_2);

// Launch both processes in parallel
$cmd1 = 'php ' . escapeshellarg($worker_script) . ' ' . escapeshellarg($in_file_1) . ' ' . escapeshellarg($out_file_1);
$cmd2 = 'php ' . escapeshellarg($worker_script) . ' ' . escapeshellarg($in_file_2) . ' ' . escapeshellarg($out_file_2);

$null_spec = array(0 => array("file", "nul", "r"), 1 => array("file", "nul", "w"), 2 => array("file", "nul", "w"));

$p1 = proc_open($cmd1, $null_spec, $pipes1);
$p2 = proc_open($cmd2, $null_spec, $pipes2);

// Wait for both processes to complete
proc_close($p1);
proc_close($p2);

@unlink($in_file_1);
@unlink($in_file_2);

$res1 = file_exists($out_file_1) ? json_decode(file_get_contents($out_file_1), TRUE) : NULL;
$res2 = file_exists($out_file_2) ? json_decode(file_get_contents($out_file_2), TRUE) : NULL;

assert_test("Process 1 executed and returned valid JSON", is_array($res1) && isset($res1['success']));
assert_test("Process 2 executed and returned valid JSON", is_array($res2) && isset($res2['success']));
assert_test("Process 1 succeeded", isset($res1['success']) && $res1['success'] === TRUE);
assert_test("Process 2 succeeded", isset($res2['success']) && $res2['success'] === TRUE);

$inv_num_1 = isset($res1['invoice_number']) ? $res1['invoice_number'] : '';
$inv_num_2 = isset($res2['invoice_number']) ? $res2['invoice_number'] : '';

assert_test("Invoice numbers are strictly unique under concurrent execution", $inv_num_1 !== '' && $inv_num_2 !== '' && $inv_num_1 !== $inv_num_2);

$numbers = array($inv_num_1, $inv_num_2);
sort($numbers);
assert_test("Invoice numbers contain sequential numbers 0001 and 0002", $numbers[0] === "INV-20261120-0001" && $numbers[1] === "INV-20261120-0002");

// Verify sequence table state
$seq_row = $db->get_where('invoice_sequences', array('sequence_date' => $conc_date))->row();
assert_test("Sequence counter in DB is exactly 2", $seq_row && (int)$seq_row->current_sequence === 2);

@unlink($out_file_1);
@unlink($out_file_2);

echo "\n";

// Test 1.2: Stock Contention Concurrency (Race Condition preventing over-selling)
echo "--- TEST 1.2: STOCK CONTENTION RACE CONDITION (LIMITED STOCK = 5) ---\n";

// Set stock of P2 in WH1 to exactly 5 units
$db->where('warehouse_id', $wh1_id)->where('product_id', $p2_id)->update('warehouse_stock', array('quantity' => 5));

// Worker A requests 4 units; Worker B requests 3 units. Total 7 > 5.
$in_file_A  = sys_get_temp_dir() . '/in_workerA_' . uniqid() . '.json';
$in_file_B  = sys_get_temp_dir() . '/in_workerB_' . uniqid() . '.json';
$out_file_A = sys_get_temp_dir() . '/workerA_' . uniqid() . '.json';
$out_file_B = sys_get_temp_dir() . '/workerB_' . uniqid() . '.json';

$payload_A = json_encode(array(
    'user_id'             => $admin_user->id,
    'customer_id'         => $cust1_id,
    'warehouse_id'        => $wh1_id,
    'invoice_date'        => '2026-11-21',
    'discount_percentage' => 0.00,
    'idempotency_key'     => 'conc_race_A_' . bin2hex(random_bytes(8)),
    'items'               => array(array('product_id' => $p2_id, 'quantity' => 4))
));
file_put_contents($in_file_A, $payload_A);

$payload_B = json_encode(array(
    'user_id'             => $admin_user->id,
    'customer_id'         => $cust1_id,
    'warehouse_id'        => $wh1_id,
    'invoice_date'        => '2026-11-21',
    'discount_percentage' => 0.00,
    'idempotency_key'     => 'conc_race_B_' . bin2hex(random_bytes(8)),
    'items'               => array(array('product_id' => $p2_id, 'quantity' => 3))
));
file_put_contents($in_file_B, $payload_B);

$cmdA = 'php ' . escapeshellarg($worker_script) . ' ' . escapeshellarg($in_file_A) . ' ' . escapeshellarg($out_file_A);
$cmdB = 'php ' . escapeshellarg($worker_script) . ' ' . escapeshellarg($in_file_B) . ' ' . escapeshellarg($out_file_B);

$pA = proc_open($cmdA, $null_spec, $pipesA);
$pB = proc_open($cmdB, $null_spec, $pipesB);

proc_close($pA);
proc_close($pB);

@unlink($in_file_A);
@unlink($in_file_B);

$resA = file_exists($out_file_A) ? json_decode(file_get_contents($out_file_A), TRUE) : NULL;
$resB = file_exists($out_file_B) ? json_decode(file_get_contents($out_file_B), TRUE) : NULL;

$success_count = (($resA['success'] ?? FALSE) ? 1 : 0) + (($resB['success'] ?? FALSE) ? 1 : 0);
$failure_count = ((!($resA['success'] ?? FALSE)) ? 1 : 0) + ((!($resB['success'] ?? FALSE)) ? 1 : 0);

assert_test("Exactly ONE concurrent process succeeded", $success_count === 1);
assert_test("Exactly ONE concurrent process failed", $failure_count === 1);

// Identify which succeeded
$winner = ($resA['success'] ?? FALSE) ? 'A (qty 4)' : 'B (qty 3)';
$failed_res = ($resA['success'] ?? FALSE) ? $resB : $resA;
assert_test("Winner was process {$winner}, and failed process returned INSUFFICIENT_STOCK", $failed_res['error_code'] === 'INSUFFICIENT_STOCK');

// Verify stock in database: initial 5 minus either 4 (leaves 1) or 3 (leaves 2)
$final_stock_p2 = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p2_id))->row()->quantity;
$expected_stock = ($resA['success'] ?? FALSE) ? 1 : 2;
assert_test("Stock is strictly non-negative and matches expected remaining stock ({$final_stock_p2} == {$expected_stock})", $final_stock_p2 === $expected_stock);

// Verify that the failed process left NO partial invoice in database
$inv_count_race = $db->where('invoice_date', '2026-11-21')->count_all_results('sales_invoices');
assert_test("Exactly 1 invoice header created for race date", $inv_count_race === 1);

$mov_count_race = $db->where('reason LIKE', '%20261121%')->count_all_results('stock_movements');
assert_test("Exactly 1 stock movement created for race date", $mov_count_race === 1);

@unlink($out_file_A);
@unlink($out_file_B);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 2: IDEMPOTENCY PAYLOAD-SENSITIVITY TESTING
// -------------------------------------------------------------------------
echo "--- SECTION 2: IDEMPOTENCY PAYLOAD SENSITIVITY ---\n";

$fixed_key = 'idem_test_' . bin2hex(random_bytes(10));

// Base Payload
$base_header = array(
    'customer_id'         => $cust1_id,
    'warehouse_id'        => $wh1_id,
    'invoice_date'        => '2026-11-25',
    'discount_percentage' => 10.00,
    'idempotency_key'     => $fixed_key
);
$base_items = array(
    array('product_id' => $p1_id, 'quantity' => 2)
);

// 2.1 First Execution: Create Invoice
$stock_before_idem = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p1_id))->row()->quantity;
$inv_initial = $CI->Sales_invoice_model->create_invoice($base_header, $base_items, $admin_user);
assert_test("Initial invoice creation with key succeeds", $inv_initial['success'] === TRUE && $inv_initial['is_replay'] === FALSE);

$first_inv_id = $inv_initial['invoice_id'];
$first_inv_num = $inv_initial['invoice_number'];

// 2.2 Replay with EXACT SAME PAYLOAD
$stock_after_first = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p1_id))->row()->quantity;
assert_test("Stock decremented by 2 for first invoice ({$stock_before_idem} -> {$stock_after_first})", $stock_after_first === ($stock_before_idem - 2));

$inv_replay = $CI->Sales_invoice_model->create_invoice($base_header, $base_items, $admin_user);
assert_test("Exact payload replay returns success=TRUE with is_replay=TRUE", $inv_replay['success'] === TRUE && $inv_replay['is_replay'] === TRUE && $inv_replay['invoice_id'] === $first_inv_id && $inv_replay['invoice_number'] === $first_inv_num);

$stock_after_replay = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p1_id))->row()->quantity;
assert_test("Replay did NOT alter stock", $stock_after_replay === $stock_after_first);

// 2.3 Same Key with DIFFERENT CUSTOMER
$diff_cust_header = $base_header;
$diff_cust_header['customer_id'] = $cust2_id; // Different customer!
$res_diff_cust = $CI->Sales_invoice_model->create_invoice($diff_cust_header, $base_items, $admin_user);
assert_test("Same key + different customer rejected with IDEMPOTENCY_CONFLICT", $res_diff_cust['success'] === FALSE && $res_diff_cust['error_code'] === 'IDEMPOTENCY_CONFLICT');

// 2.4 Same Key with DIFFERENT WAREHOUSE
$diff_wh_header = $base_header;
$diff_wh_header['warehouse_id'] = $wh2_id; // Different warehouse!
$res_diff_wh = $CI->Sales_invoice_model->create_invoice($diff_wh_header, $base_items, $admin_user);
assert_test("Same key + different warehouse rejected with IDEMPOTENCY_CONFLICT", $res_diff_wh['success'] === FALSE && $res_diff_wh['error_code'] === 'IDEMPOTENCY_CONFLICT');

// 2.5 Same Key with DIFFERENT QUANTITY
$diff_qty_items = array(
    array('product_id' => $p1_id, 'quantity' => 5) // Different quantity (5 vs 2)!
);
$res_diff_qty = $CI->Sales_invoice_model->create_invoice($base_header, $diff_qty_items, $admin_user);
assert_test("Same key + different quantity rejected with IDEMPOTENCY_CONFLICT", $res_diff_qty['success'] === FALSE && $res_diff_qty['error_code'] === 'IDEMPOTENCY_CONFLICT');

// 2.6 Same Key with DIFFERENT PRODUCT
$diff_prod_items = array(
    array('product_id' => $p2_id, 'quantity' => 2) // Different product (P2 vs P1)!
);
$res_diff_prod = $CI->Sales_invoice_model->create_invoice($base_header, $diff_prod_items, $admin_user);
assert_test("Same key + different product rejected with IDEMPOTENCY_CONFLICT", $res_diff_prod['success'] === FALSE && $res_diff_prod['error_code'] === 'IDEMPOTENCY_CONFLICT');

// 2.7 Same Key with DIFFERENT DISCOUNT
$diff_disc_header = $base_header;
$diff_disc_header['discount_percentage'] = 25.00; // Different discount (25% vs 10%)!
$res_diff_disc = $CI->Sales_invoice_model->create_invoice($diff_disc_header, $base_items, $admin_user);
assert_test("Same key + different discount rejected with IDEMPOTENCY_CONFLICT", $res_diff_disc['success'] === FALSE && $res_diff_disc['error_code'] === 'IDEMPOTENCY_CONFLICT');

// 2.8 Same Key with DIFFERENT INVOICE DATE
$diff_date_header = $base_header;
$diff_date_header['invoice_date'] = '2026-11-26'; // Different date!
$res_diff_date = $CI->Sales_invoice_model->create_invoice($diff_date_header, $base_items, $admin_user);
assert_test("Same key + different date rejected with IDEMPOTENCY_CONFLICT", $res_diff_date['success'] === FALSE && $res_diff_date['error_code'] === 'IDEMPOTENCY_CONFLICT');

// 2.9 Same Key with DIFFERENT NOTES
$diff_notes_header = $base_header;
$diff_notes_header['notes'] = 'ملاحظة مختلفة تماماً عن الأصل';
$res_diff_notes = $CI->Sales_invoice_model->create_invoice($diff_notes_header, $base_items, $admin_user);
assert_test("Same key + different notes rejected with IDEMPOTENCY_CONFLICT", $res_diff_notes['success'] === FALSE && $res_diff_notes['error_code'] === 'IDEMPOTENCY_CONFLICT');

// Verify stock remained untouched through all conflict attempts
$stock_after_conflicts = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p1_id))->row()->quantity;
assert_test("Stock completely unchanged after all conflict rejection attempts", $stock_after_conflicts === $stock_after_first);

// Verify no additional invoices or movements exist for this idempotency key
$invoices_with_key = $db->get_where('sales_invoices', array('idempotency_key' => $fixed_key))->num_rows();
assert_test("Exactly 1 invoice header exists with the key in DB", $invoices_with_key === 1);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 3: TEARDOWN & CLEANUP
// -------------------------------------------------------------------------
echo "--- SECTION 3: TEARDOWN & COLLATERAL DAMAGE AUDIT ---\n";

// Delete movements linked to test warehouses
$db->where_in('warehouse_id', array($wh1_id, $wh2_id))->delete('stock_movements');

// Delete items linked to test invoices
$test_inv_rows = $db->select('id')->where_in('warehouse_id', array($wh1_id, $wh2_id))->get('sales_invoices')->result_array();
if (!empty($test_inv_rows)) {
    $inv_ids = array_column($test_inv_rows, 'id');
    $db->where_in('invoice_id', $inv_ids)->delete('sales_invoice_items');
}

// Delete test invoices
$db->where_in('warehouse_id', array($wh1_id, $wh2_id))->delete('sales_invoices');

// Delete test sequences
$db->where_in('sequence_date', array('2026-11-20', '2026-11-21', '2026-11-25'))->delete('invoice_sequences');

// Delete test stock
$db->where_in('warehouse_id', array($wh1_id, $wh2_id))->delete('warehouse_stock');

// Delete test products, categories, customers, warehouses
$db->where_in('id', array($p1_id, $p2_id))->delete('products');
$db->where('id', $cat_id)->delete('categories');
$db->where_in('id', array($cust1_id, $cust2_id))->delete('customers');
$db->where_in('id', array($wh1_id, $wh2_id))->delete('warehouses');

// Final check: pre-existing live rows count
$final_stock_count     = $db->count_all('warehouse_stock');
$final_movements_count = $db->count_all('stock_movements');
$final_invoices_count  = $db->count_all('sales_invoices');
$final_customers_count = $db->count_all('customers');

assert_test("Zero collateral damage on warehouse_stock ({$final_stock_count} == {$initial_stock_count})", $final_stock_count === $initial_stock_count);
assert_test("Zero collateral damage on stock_movements ({$final_movements_count} == {$initial_movements_count})", $final_movements_count === $initial_movements_count);
assert_test("Zero collateral damage on sales_invoices ({$final_invoices_count} == {$initial_invoices_count})", $final_invoices_count === $initial_invoices_count);
assert_test("Zero collateral damage on customers ({$final_customers_count} == {$initial_customers_count})", $final_customers_count === $initial_customers_count);

echo "\n====================================================================\n";
echo " TEST SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
