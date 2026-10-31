<?php
/**
 * Mini ERP Stock & Sales — F07 Sales Invoices Models & Service Layer Verification
 *
 * Comprehensive integration test suite for:
 * - Customer_model (CRUD, validation, pagination, active status)
 * - Sales_invoice_model (Atomic transaction, stock deduction, sale movements, daily sequence, financial math, scoping)
 *
 * PHP Compatibility: 7.4+
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('Customer_model');
$CI->load->model('Sales_invoice_model');
$CI->load->model('Stock_movement_model');
$CI->load->model('Warehouse_model');
$CI->load->model('Product_model');
$CI->load->model('User_warehouse_model');
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
echo " Mini ERP Stock & Sales — F07 Models & Service Verification Suite\n";
echo "====================================================================\n\n";

// Snapshot pre-existing stock & movements to guarantee zero collateral damage
$initial_stock_count = $db->count_all('warehouse_stock');
$initial_movements_count = $db->count_all('stock_movements');
$initial_invoices_count = $db->count_all('sales_invoices');
$initial_customers_count = $db->count_all('customers');
$initial_users_count = $db->count_all('users');

// Ensure invoice_sequences for 2026-10-31 is synchronized with existing invoices
$existing_seq = $db->get_where('invoice_sequences', array('sequence_date' => '2026-10-31'))->row();
if (!$existing_seq) {
    $max_seq = (int)$db->where('invoice_date', '2026-10-31')->count_all_results('sales_invoices');
    $db->insert('invoice_sequences', array('sequence_date' => '2026-10-31', 'current_sequence' => $max_seq));
    $initial_seq_20261031 = $max_seq;
} else {
    $initial_seq_20261031 = (int)$existing_seq->current_sequence;
}

echo "Initial State: Stock Rows={$initial_stock_count}, Movements={$initial_movements_count}, Invoices={$initial_invoices_count}, Customers={$initial_customers_count}, Users={$initial_users_count}\n\n";

// -------------------------------------------------------------------------
// SECTION 1: CUSTOMER_MODEL TESTS
// -------------------------------------------------------------------------
echo "--- SECTION 1: CUSTOMER_MODEL (CRUD, VALIDATION, STATUS) ---\n";

// 1.1 Customer creation validation
$invalid_res = $CI->Customer_model->create(array('name' => 'A')); // Too short
assert_test("Reject customer with name < 2 chars", $invalid_res['success'] === FALSE && $invalid_res['error_code'] === 'VALIDATION_FAILED');

$invalid_res2 = $CI->Customer_model->create(array('name' => 'Valid Name', 'email' => 'invalid-email-format'));
assert_test("Reject customer with invalid email", $invalid_res2['success'] === FALSE && $invalid_res2['error_code'] === 'VALIDATION_FAILED');

// 1.2 Valid customer creation
$cust_create = $CI->Customer_model->create(array(
    'name'    => 'شركة الاختبار البرمجي المحدودة',
    'phone'   => '0501112233',
    'email'   => 'test_f07@example.com',
    'address' => 'الرياض - حي الملز'
));
assert_test("Create valid customer", $cust_create['success'] === TRUE && $cust_create['customer_id'] > 0);
$test_customer_id = $cust_create['customer_id'];

// 1.3 Active customer retrieval
$active_cust = $CI->Customer_model->get_active_customer($test_customer_id);
assert_test("Retrieve active customer by ID", is_object($active_cust) && (int)$active_cust->is_active === 1 && $active_cust->name === 'شركة الاختبار البرمجي المحدودة');

// 1.4 Search and Pagination
$search_count = $CI->Customer_model->count_customers('0501112233', 1);
assert_test("Search customer by phone number", $search_count >= 1);

$paged_customers = $CI->Customer_model->get_customers_paged(10, 0, 'الاختبار البرمجي');
assert_test("Paged search customer by name keyword", is_array($paged_customers) && count($paged_customers) >= 1 && (int)$paged_customers[0]->id === $test_customer_id);

// 1.5 Soft-disable customer
$disable_res = $CI->Customer_model->set_status($test_customer_id, 0);
assert_test("Soft-disable customer (set is_active = 0)", $disable_res['success'] === TRUE && $disable_res['new_status'] === 0);

$cust_after_disable = $CI->Customer_model->get_active_customer($test_customer_id);
assert_test("get_active_customer returns NULL for soft-disabled customer", $cust_after_disable === NULL);

$cust_raw = $CI->Customer_model->get_by_id($test_customer_id);
assert_test("get_by_id still retrieves soft-disabled customer", is_object($cust_raw) && (int)$cust_raw->is_active === 0);

// 1.6 Re-enable customer
$enable_res = $CI->Customer_model->set_status($test_customer_id, 1);
assert_test("Re-enable customer (set is_active = 1)", $enable_res['success'] === TRUE && $enable_res['new_status'] === 1);
$cust_after_enable = $CI->Customer_model->get_active_customer($test_customer_id);
assert_test("get_active_customer returns customer after re-enable", is_object($cust_after_enable) && (int)$cust_after_enable->is_active === 1);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 2: SETUP ISOLATED WAREHOUSE & STOCK FOR SALES INVOICE TESTS
// -------------------------------------------------------------------------
echo "--- SECTION 2: SETUP TEST FIXTURES ---\n";

// Create isolated warehouse
$wh_res = $db->insert('warehouses', array(
    'code'       => 'WH-F07-TEST',
    'name'       => 'مستودع اختبار المبيعات F07',
    'location'   => 'موقع اختباري معزول',
    'is_active'  => 1,
    'created_at' => date('Y-m-d H:i:s')
));
$test_wh_id = $db->insert_id();

// Create isolated category & product
$db->insert('categories', array(
    'name'       => 'تصنيف مبيعات F07',
    'is_active'  => 1,
    'created_at' => date('Y-m-d H:i:s')
));
$test_cat_id = $db->insert_id();

$db->insert('products', array(
    'category_id' => $test_cat_id,
    'sku'         => 'SKU-F07-P1',
    'name'        => 'منتج اختبار المبيعات 1',
    'sale_price'  => 50.00,
    'is_active'   => 1,
    'created_at'  => date('Y-m-d H:i:s')
));
$test_p1_id = $db->insert_id();

$db->insert('products', array(
    'category_id' => $test_cat_id,
    'sku'         => 'SKU-F07-P2',
    'name'        => 'منتج اختبار المبيعات 2',
    'sale_price'  => 25.50,
    'is_active'   => 1,
    'created_at'  => date('Y-m-d H:i:s')
));
$test_p2_id = $db->insert_id();

// Seed initial stock in test warehouse: P1 has 20 units, P2 has 10 units
$db->insert('warehouse_stock', array(
    'warehouse_id'  => $test_wh_id,
    'product_id'    => $test_p1_id,
    'quantity'      => 20,
    'reorder_level' => 5,
    'created_at'    => date('Y-m-d H:i:s')
));
$db->insert('warehouse_stock', array(
    'warehouse_id'  => $test_wh_id,
    'product_id'    => $test_p2_id,
    'quantity'      => 10,
    'reorder_level' => 2,
    'created_at'    => date('Y-m-d H:i:s')
));

// Fetch users for role-based testing
$admin_user = $CI->User_model->get_by_username_for_auth('admin');

// Create isolated test warehouse user (role_id = 2)
$db->insert('users', array(
    'username'      => 'wh_test_f07_' . substr(bin2hex(random_bytes(3)), 0, 5),
    'password_hash' => password_hash('Wh@123456', PASSWORD_BCRYPT),
    'full_name'     => 'أمين مستودع تجريبي F07',
    'email'         => 'wh_f07_' . bin2hex(random_bytes(3)) . '@example.com',
    'role_id'       => 2, // warehouse
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s')
));
$test_wh_user_id = (int)$db->insert_id();
$warehouse_user = $CI->User_model->get_by_id($test_wh_user_id);

// Create isolated test sales user
$db->insert('users', array(
    'username'      => 'sales_test_f07',
    'password_hash' => password_hash('Sales@123', PASSWORD_BCRYPT),
    'full_name'     => 'بائع تجريبي F07',
    'email'         => 'sales_f07@example.com',
    'role_id'       => 3, // sales
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s')
));
$test_sales_user_id = $db->insert_id();
$sales_user = $CI->User_model->get_by_id($test_sales_user_id);

// Assign sales_user to test warehouse
$CI->User_warehouse_model->assign($sales_user->id, $test_wh_id);

assert_test("Test warehouse, products, stock, and users prepared", $test_wh_id > 0 && $test_p1_id > 0 && $test_p2_id > 0);
echo "\n";

// -------------------------------------------------------------------------
// SECTION 3: PERMISSIONS & PRE-VALIDATION REJECTIONS
// -------------------------------------------------------------------------
echo "--- SECTION 3: PERMISSIONS & PRE-VALIDATIONS ---\n";

// 3.1 Warehouse user cannot create invoices
$wh_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $warehouse_user
);
assert_test("Warehouse role rejected from creating invoice", $wh_attempt['success'] === FALSE && $wh_attempt['error_code'] === 'PERMISSION_DENIED');

// 3.2 Sales user cannot create invoice in unassigned warehouse
$unassigned_wh_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => 1), // Assuming warehouse 1 not assigned to sales_user
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $sales_user
);
// Verify if warehouse 1 was assigned or not; if assigned, test against a dummy unassigned ID
$is_assigned_1 = $CI->User_warehouse_model->is_assigned($sales_user->id, 1);
if ($is_assigned_1) {
    // Pick dummy id
    $unassigned_wh_attempt = $CI->Sales_invoice_model->create_invoice(
        array('customer_id' => $test_customer_id, 'warehouse_id' => 99999),
        array(array('product_id' => $test_p1_id, 'quantity' => 1)),
        $sales_user
    );
}
assert_test("Sales role rejected in unassigned warehouse", $unassigned_wh_attempt['success'] === FALSE && $unassigned_wh_attempt['error_code'] === 'UNAUTHORIZED_WAREHOUSE');

// 3.3 Reject inactive customer
$CI->Customer_model->set_status($test_customer_id, 0); // Disable customer
$inactive_cust_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("Reject invoice creation for inactive customer", $inactive_cust_attempt['success'] === FALSE && $inactive_cust_attempt['error_code'] === 'INACTIVE_CUSTOMER');
$CI->Customer_model->set_status($test_customer_id, 1); // Re-enable

// 3.4 Reject empty invoice items
$empty_items_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id),
    array(),
    $admin_user
);
assert_test("Reject empty items array", $empty_items_attempt['success'] === FALSE && $empty_items_attempt['error_code'] === 'EMPTY_INVOICE_ITEMS');

// 3.5 Reject zero or negative quantity
$zero_qty_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id),
    array(array('product_id' => $test_p1_id, 'quantity' => 0)),
    $admin_user
);
assert_test("Reject item with quantity = 0", $zero_qty_attempt['success'] === FALSE && $zero_qty_attempt['error_code'] === 'INVALID_ITEM_QUANTITY');

$neg_qty_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id),
    array(array('product_id' => $test_p1_id, 'quantity' => -5)),
    $admin_user
);
assert_test("Reject item with negative quantity", $neg_qty_attempt['success'] === FALSE && $neg_qty_attempt['error_code'] === 'INVALID_ITEM_QUANTITY');

// 3.6 Reject out of range discount (< 0 or > 100)
$invalid_disc_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'discount_percentage' => 105.00),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("Reject discount > 100%", $invalid_disc_attempt['success'] === FALSE && $invalid_disc_attempt['error_code'] === 'DISCOUNT_OUT_OF_RANGE');

echo "\n";

// -------------------------------------------------------------------------
// SECTION 4: INSUFFICIENT STOCK & ATOMIC ROLLBACK
// -------------------------------------------------------------------------
echo "--- SECTION 4: INSUFFICIENT STOCK & ATOMIC ROLLBACK ---\n";

// Available P1 is 20, request 25
$over_stock_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id),
    array(array('product_id' => $test_p1_id, 'quantity' => 25)),
    $admin_user
);
assert_test("Reject invoice when requested quantity exceeds stock", $over_stock_attempt['success'] === FALSE && $over_stock_attempt['error_code'] === 'INSUFFICIENT_STOCK');

// Verify stock was NOT modified
$stock_p1 = $db->get_where('warehouse_stock', array('warehouse_id' => $test_wh_id, 'product_id' => $test_p1_id))->row();
assert_test("Stock remains intact at 20 after failed invoice creation", (int)$stock_p1->quantity === 20);

// Verify no partial invoice or movement was created
$no_partial_inv = $db->get_where('sales_invoices', array('warehouse_id' => $test_wh_id))->num_rows();
assert_test("No partial invoice created", $no_partial_inv === 0);

$no_partial_mov = $db->get_where('stock_movements', array('warehouse_id' => $test_wh_id))->num_rows();
assert_test("No partial movement created", $no_partial_mov === 0);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 5: VALID INVOICE CREATION & FINANCIAL CALCULATIONS
// -------------------------------------------------------------------------
echo "--- SECTION 5: VALID INVOICE CREATION & FINANCIAL CALCULATIONS ---\n";

// Scenario 5.1: Valid invoice with 2 items and 10% discount
// P1: qty 2 @ 50.00 = 100.00
// P2: qty 3 @ 25.50 = 76.50
// Subtotal = 176.50
// Discount 10% = 17.65
// Total = 158.85
$inv1_res = $CI->Sales_invoice_model->create_invoice(
    array(
        'customer_id'         => $test_customer_id,
        'warehouse_id'        => $test_wh_id,
        'invoice_date'        => '2026-10-31',
        'discount_percentage' => 10.00,
        'notes'               => 'فاتورة اختبار F07 رقم 1'
    ),
    array(
        array('product_id' => $test_p1_id, 'quantity' => 2),
        array('product_id' => $test_p2_id, 'quantity' => 3)
    ),
    $sales_user
);

assert_test("Invoice 1 created successfully", $inv1_res['success'] === TRUE && $inv1_res['invoice_id'] > 0);
assert_test("Invoice 1 numbering matches pattern INV-20261031-XXXX", (bool)preg_match('/^INV-20261031-\d{4}$/', $inv1_res['invoice_number']));
assert_test("Invoice 1 subtotal calculation (176.50)", (float)$inv1_res['subtotal'] === 176.50);
assert_test("Invoice 1 discount amount (17.65)", (float)$inv1_res['discount_amount'] === 17.65);
assert_test("Invoice 1 total amount (158.85)", (float)$inv1_res['total_amount'] === 158.85);

$inv1_id = $inv1_res['invoice_id'];

// Verify Sales Invoice in DB
$inv1_row = $CI->Sales_invoice_model->get_by_id($inv1_id);
assert_test("Invoice DB currency is SAR", $inv1_row->currency_code === 'SAR');
assert_test("Invoice DB status is active", $inv1_row->status === 'active');
assert_test("Invoice DB math balance: total = subtotal - discount", (float)$inv1_row->total_amount == (float)$inv1_row->subtotal - (float)$inv1_row->discount_amount);

// Verify Line Items Snapshots
$inv1_items = $CI->Sales_invoice_model->get_items_by_invoice_id($inv1_id);
assert_test("Invoice line items count is 2", is_array($inv1_items) && count($inv1_items) === 2);
assert_test("Line item 1 SKU snapshot preserved", $inv1_items[0]->sku_snapshot === 'SKU-F07-P1' && (int)$inv1_items[0]->quantity === 2);
assert_test("Line item 2 SKU snapshot preserved", $inv1_items[1]->sku_snapshot === 'SKU-F07-P2' && (int)$inv1_items[1]->quantity === 3);

// Verify Stock Deduction in warehouse_stock
// P1 initial was 20, sold 2 => 18
// P2 initial was 10, sold 3 => 7
$st1 = $db->get_where('warehouse_stock', array('warehouse_id' => $test_wh_id, 'product_id' => $test_p1_id))->row();
$st2 = $db->get_where('warehouse_stock', array('warehouse_id' => $test_wh_id, 'product_id' => $test_p2_id))->row();
assert_test("Warehouse stock P1 decremented to 18", (int)$st1->quantity === 18);
assert_test("Warehouse stock P2 decremented to 7", (int)$st2->quantity === 7);

// Verify Stock Movements
$movements = $db->where('sales_invoice_id', $inv1_id)->order_by('id', 'ASC')->get('stock_movements')->result();
assert_test("Stock movements count is 2 for invoice", count($movements) === 2);
assert_test("Movement 1 type is 'sale' and delta is -2", $movements[0]->movement_type === 'sale' && (int)$movements[0]->quantity_delta === -2);
assert_test("Movement 1 before/after matches (20 -> 18)", (int)$movements[0]->before_quantity === 20 && (int)$movements[0]->after_quantity === 18);
assert_test("Movement 2 type is 'sale' and delta is -3", $movements[1]->movement_type === 'sale' && (int)$movements[1]->quantity_delta === -3);
assert_test("Movement 2 before/after matches (10 -> 7)", (int)$movements[1]->before_quantity === 10 && (int)$movements[1]->after_quantity === 7);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 6: DISCOUNT BOUNDARIES (0% AND 100%) & HALF_UP ROUNDING
// -------------------------------------------------------------------------
echo "--- SECTION 6: DISCOUNT BOUNDARIES & HALF_UP ROUNDING ---\n";

// 6.1 0% Discount: Subtotal 50.00, Total 50.00
$inv_zero_disc = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'discount_percentage' => 0.00),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("0% discount allowed: total equals subtotal", $inv_zero_disc['success'] === TRUE && (float)$inv_zero_disc['discount_amount'] === 0.00 && (float)$inv_zero_disc['total_amount'] === (float)$inv_zero_disc['subtotal']);

// 6.2 100% Discount: Subtotal 50.00, Discount 50.00, Total 0.00
$inv_full_disc = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'discount_percentage' => 100.00),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("100% discount allowed: total is 0.00", $inv_full_disc['success'] === TRUE && (float)$inv_full_disc['total_amount'] === 0.00 && (float)$inv_full_disc['discount_amount'] === (float)$inv_full_disc['subtotal']);

// 6.3 Rounding check with PHP_ROUND_HALF_UP:
// Subtotal = 25.50 * 1 = 25.50
// Discount 15.25% = 25.50 * 0.1525 = 3.88875 -> 3.89
// Total = 25.50 - 3.89 = 21.61
$inv_round = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'discount_percentage' => 15.25),
    array(array('product_id' => $test_p2_id, 'quantity' => 1)),
    $admin_user
);
assert_test("PHP_ROUND_HALF_UP discount rounding: 3.89 on 15.25%", $inv_round['success'] === TRUE && (float)$inv_round['discount_amount'] === 3.89 && (float)$inv_round['total_amount'] === 21.61);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 7: DAILY INVOICE NUMBERING & CEILING AT 9999
// -------------------------------------------------------------------------
echo "--- SECTION 7: DAILY INVOICE NUMBERING & CEILING ---\n";

// Sequential check on same test date
$test_date = '2026-12-31';
$inv_seq1 = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'invoice_date' => $test_date),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
$inv_seq2 = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'invoice_date' => $test_date),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("First invoice on date is INV-20261231-0001", $inv_seq1['invoice_number'] === 'INV-20261231-0001');
assert_test("Second invoice on date is INV-20261231-0002", $inv_seq2['invoice_number'] === 'INV-20261231-0002');

// Simulate ceiling boundary 9999
$db->where('sequence_date', $test_date)->update('invoice_sequences', array('current_sequence' => 9999));
$inv_ceiling = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'invoice_date' => $test_date),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("Reject invoice when daily sequence reaches 9999", $inv_ceiling['success'] === FALSE && $inv_ceiling['error_code'] === 'DAILY_SEQUENCE_LIMIT_EXCEEDED');

echo "\n";

// -------------------------------------------------------------------------
// SECTION 8: IDEMPOTENCY REPLAY
// -------------------------------------------------------------------------
echo "--- SECTION 8: IDEMPOTENCY REPLAY ---\n";

$fixed_idem_key = 'idem_f07_' . bin2hex(random_bytes(10));
$stock_before_idem = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $test_wh_id, 'product_id' => $test_p1_id))->row()->quantity;

$inv_idem1 = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'idempotency_key' => $fixed_idem_key),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("First invoice creation with idempotency key succeeds", $inv_idem1['success'] === TRUE && $inv_idem1['is_replay'] === FALSE);

// Replay with exact same idempotency key
$inv_idem2 = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $test_customer_id, 'warehouse_id' => $test_wh_id, 'idempotency_key' => $fixed_idem_key),
    array(array('product_id' => $test_p1_id, 'quantity' => 1)),
    $admin_user
);
assert_test("Second request with same idempotency key returns replay response", $inv_idem2['success'] === TRUE && $inv_idem2['is_replay'] === TRUE && $inv_idem2['invoice_id'] === $inv_idem1['invoice_id']);

$stock_after_idem = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $test_wh_id, 'product_id' => $test_p1_id))->row()->quantity;
assert_test("Stock decremented only once across replay requests", $stock_after_idem === ($stock_before_idem - 1));

echo "\n";

// -------------------------------------------------------------------------
// SECTION 9: ROLE-BASED SCOPING (ADMIN VS SALES VS WAREHOUSE)
// -------------------------------------------------------------------------
echo "--- SECTION 9: ROLE-BASED SCOPING ---\n";

// Admin sees all invoices in test warehouse
$admin_count = $CI->Sales_invoice_model->count_invoices(array('warehouse_id' => $test_wh_id), $admin_user->id, 'admin');
assert_test("Admin sees all invoices in warehouse ({$admin_count})", $admin_count >= 5);

// Sales user only sees invoices they created in assigned warehouse
$sales_count = $CI->Sales_invoice_model->count_invoices(array('warehouse_id' => $test_wh_id), $sales_user->id, 'sales');
assert_test("Sales user sees only their created invoices (1)", $sales_count === 1);

// Warehouse user has zero access to invoices
$wh_count = $CI->Sales_invoice_model->count_invoices(array('warehouse_id' => $test_wh_id), $warehouse_user->id, 'warehouse');
assert_test("Warehouse user sees zero invoices", $wh_count === 0);

$wh_paged = $CI->Sales_invoice_model->get_invoices_paged(10, 0, array('warehouse_id' => $test_wh_id), $warehouse_user->id, 'warehouse');
assert_test("Warehouse user get_invoices_paged returns empty array", empty($wh_paged));

echo "\n";

// -------------------------------------------------------------------------
// SECTION 10: CLEAN TEARDOWN & RECOVERY
// -------------------------------------------------------------------------
echo "--- SECTION 10: CLEAN TEARDOWN ---\n";

// Delete created test movements
$db->where('warehouse_id', $test_wh_id)->delete('stock_movements');

// Delete created test invoice items
$test_inv_ids = $db->select('id')->where('warehouse_id', $test_wh_id)->get('sales_invoices')->result_array();
if (!empty($test_inv_ids)) {
    $ids = array_column($test_inv_ids, 'id');
    $db->where_in('invoice_id', $ids)->delete('sales_invoice_items');
}

// Delete created test invoices
$db->where('warehouse_id', $test_wh_id)->delete('sales_invoices');

// Delete test sequences created or restore pre-existing
if ($initial_seq_20261031 !== NULL) {
    $db->where('sequence_date', '2026-10-31')->update('invoice_sequences', array('current_sequence' => $initial_seq_20261031));
} else {
    $db->where('sequence_date', '2026-10-31')->delete('invoice_sequences');
}
$db->where('sequence_date', '2026-12-31')->delete('invoice_sequences');

// Delete test stock
$db->where('warehouse_id', $test_wh_id)->delete('warehouse_stock');

// Delete test user_warehouse assignment
$CI->User_warehouse_model->remove($sales_user->id, $test_wh_id);

// Delete test sales & warehouse users
$db->where('id', $test_sales_user_id)->delete('users');
$db->where('id', $test_wh_user_id)->delete('users');

// Delete test customer
$db->where('id', $test_customer_id)->delete('customers');

// Delete test products and category
$db->where('id', $test_p1_id)->delete('products');
$db->where('id', $test_p2_id)->delete('products');
$db->where('id', $test_cat_id)->delete('categories');

// Delete test warehouse
$db->where('id', $test_wh_id)->delete('warehouses');

// Final check: pre-existing live rows count
$final_stock_count = $db->count_all('warehouse_stock');
$final_movements_count = $db->count_all('stock_movements');
$final_invoices_count = $db->count_all('sales_invoices');
$final_customers_count = $db->count_all('customers');
$final_users_count = $db->count_all('users');

assert_test("Zero collateral damage on warehouse_stock ({$final_stock_count} == {$initial_stock_count})", $final_stock_count === $initial_stock_count);
assert_test("Zero collateral damage on stock_movements ({$final_movements_count} == {$initial_movements_count})", $final_movements_count === $initial_movements_count);
assert_test("Zero collateral damage on sales_invoices ({$final_invoices_count} == {$initial_invoices_count})", $final_invoices_count === $initial_invoices_count);
assert_test("Zero collateral damage on customers ({$final_customers_count} == {$initial_customers_count})", $final_customers_count === $initial_customers_count);
assert_test("Zero collateral damage on users ({$final_users_count} == {$initial_users_count})", $final_users_count === $initial_users_count);

echo "\n====================================================================\n";
echo " TEST SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
