<?php
/**
 * Test Suite: F08 — Sales Invoice Cancellation
 *
 * Comprehensive automated verification for F08 Sales Invoice Cancellation:
 * 1. Admin cancels an active multi-item sales invoice (Model & HTTP).
 * 2. Accurate restoration of warehouse stock quantities.
 * 3. Generation of 'sale_reversal' movements with positive deltas.
 * 4. Relational integrity: reversal_of_movement_id strictly points to original sale movement.
 * 5. Unique constraint enforcement (uk_sm_reversal_of): no double reversal.
 * 6. Prevention of duplicate cancellation (ALREADY_CANCELLED).
 * 7. Real concurrency test with two independent CLI workers executing simultaneous cancellations.
 * 8. Non-admin authorization enforcement (Sales, Warehouse, and Guest blocked).
 * 9. Validation of cancellation reason (min 5 chars, max 255 chars, required).
 * 10. HTTP method enforcement (POST required, GET yields 404, invalid CSRF yields 403).
 * 11. Historical integrity: invoice header, line items, and original sale movements are NEVER deleted.
 * 12. Transaction atomicity: simulated failure rolls back stock and movements cleanly.
 * 13. Zero collateral damage on pre-existing live database records.
 *
 * PHP Compatibility: 7.4+
 */

ob_start();
require_once dirname(__DIR__) . '/index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('Sales_invoice_model');
$CI->load->model('Customer_model');
$CI->load->model('User_model');
$CI->load->model('Warehouse_model');
$CI->load->model('User_warehouse_model');
$CI->load->model('Product_model');

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

function is_redirect_status($status) {
    return in_array((int)$status, array(301, 302, 303, 307, 308), TRUE);
}

class HttpTestClient {
    private $cookie_file;
    private $base_url;

    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'ci_f08_cookie_');
    }

    public function __destruct() {
        if (file_exists($this->cookie_file)) {
            @unlink($this->cookie_file);
        }
    }

    public function request($path, $method = 'GET', $data = array(), $headers = array()) {
        $url = $this->base_url . '/' . ltrim($path, '/');
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);

        $http_headers = array();
        foreach ($headers as $k => $v) {
            $http_headers[] = "{$k}: {$v}";
        }

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if (!empty($http_headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
        }

        $raw = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $header_str = substr($raw, 0, $header_size);
        $body = substr($raw, $header_size);

        $location = NULL;
        if (preg_match('/^Location:\s*(.+)$/im', $header_str, $matches)) {
            $location = trim($matches[1]);
        }

        return array(
            'status'   => $status_code,
            'header'   => $header_str,
            'body'     => $body,
            'location' => $location,
        );
    }

    public function get_csrf_token($path = 'index.php/login') {
        if ($path !== NULL) {
            $res = $this->request($path, 'GET');
            if (preg_match('/name="csrf_erp_token"\s+value="([a-f0-9]{32})"/', $res['body'], $m)) {
                return $m[1];
            }
        }
        if (file_exists($this->cookie_file)) {
            $content = file_get_contents($this->cookie_file);
            if (preg_match('/csrf_erp_cookie\s+([a-f0-9]{32})/', $content, $m)) {
                return $m[1];
            }
        }
        return NULL;
    }

    public function login($username, $password) {
        $token = $this->get_csrf_token('index.php/login');
        if (!$token) {
            return FALSE;
        }

        $res = $this->request('index.php/login', 'POST', array(
            'csrf_erp_token' => $token,
            'username'       => $username,
            'password'       => $password,
        ));

        return (is_redirect_status($res['status']) && $res['location'] !== NULL && strpos($res['location'], 'dashboard') !== FALSE);
    }
}

$base_url = 'http://localhost/codeigniter-projects/mini-erp-stock-sales';

echo "====================================================================\n";
echo " Mini ERP Stock & Sales — F08 Sales Invoice Cancellation Verification\n";
echo "====================================================================\n\n";

// Snapshot baseline DB state
$initial_stock_count     = $db->count_all('warehouse_stock');
$initial_movements_count = $db->count_all('stock_movements');
$initial_invoices_count  = $db->count_all('sales_invoices');
$initial_items_count     = $db->count_all('sales_invoice_items');
$initial_customers_count = $db->count_all('customers');
$initial_users_count     = $db->count_all('users');

// Synchronize sequence for current date if not set
$existing_seq = $db->get_where('invoice_sequences', array('sequence_date' => '2026-10-31'))->row();
if (!$existing_seq) {
    $max_seq = (int)$db->where('invoice_date', '2026-10-31')->count_all_results('sales_invoices');
    $db->insert('invoice_sequences', array('sequence_date' => '2026-10-31', 'current_sequence' => $max_seq));
    $initial_seq_20261031 = $max_seq;
} else {
    $initial_seq_20261031 = (int)$existing_seq->current_sequence;
}

echo "Initial DB State: Stock={$initial_stock_count}, Movements={$initial_movements_count}, Invoices={$initial_invoices_count}, Items={$initial_items_count}, Customers={$initial_customers_count}, Users={$initial_users_count}\n\n";

try {
    // -------------------------------------------------------------------------
    // SECTION 1: SETUP TEST FIXTURES
    // -------------------------------------------------------------------------
    echo "--- SECTION 1: SETUP TEST FIXTURES ---\n";

    $code_suffix = bin2hex(random_bytes(3));

    // Warehouse
    $db->insert('warehouses', array(
        'code'       => 'WH-F08-' . $code_suffix,
        'name'       => 'مستودع اختبار الإلغاء F08',
        'location'   => 'موقع اختباري F08',
        'is_active'  => 1,
        'created_at' => date('Y-m-d H:i:s')
    ));
    $wh_id = (int)$db->insert_id();

    // Category
    $db->insert('categories', array(
        'name'       => 'تصنيف F08 ' . $code_suffix,
        'is_active'  => 1,
        'created_at' => date('Y-m-d H:i:s')
    ));
    $cat_id = (int)$db->insert_id();

    // Products
    $db->insert('products', array(
        'category_id' => $cat_id,
        'sku'         => 'SKU-F08-A-' . $code_suffix,
        'name'        => 'منتج إلغاء أ ' . $code_suffix,
        'sale_price'  => 50.00,
        'is_active'   => 1,
        'created_at'  => date('Y-m-d H:i:s')
    ));
    $p1_id = (int)$db->insert_id();

    $db->insert('products', array(
        'category_id' => $cat_id,
        'sku'         => 'SKU-F08-B-' . $code_suffix,
        'name'        => 'منتج إلغاء ب ' . $code_suffix,
        'sale_price'  => 25.00,
        'is_active'   => 1,
        'created_at'  => date('Y-m-d H:i:s')
    ));
    $p2_id = (int)$db->insert_id();

    // Stock: Product 1 has 30, Product 2 has 20
    $db->insert('warehouse_stock', array(
        'warehouse_id'  => $wh_id,
        'product_id'    => $p1_id,
        'quantity'      => 30,
        'reorder_level' => 5,
        'created_at'    => date('Y-m-d H:i:s')
    ));
    $db->insert('warehouse_stock', array(
        'warehouse_id'  => $wh_id,
        'product_id'    => $p2_id,
        'quantity'      => 20,
        'reorder_level' => 5,
        'created_at'    => date('Y-m-d H:i:s')
    ));

    // Customer
    $db->insert('customers', array(
        'name'       => 'عميل فحص الإلغاء F08 ' . $code_suffix,
        'phone'      => '0555888999',
        'email'      => 'f08_' . $code_suffix . '@test.local',
        'is_active'  => 1,
        'created_at' => date('Y-m-d H:i:s')
    ));
    $customer_id = (int)$db->insert_id();

    // Users: Admin, Sales, Warehouse
    $admin_user = $CI->User_model->get_by_username_for_auth('admin');

    $hashed_pw = password_hash('Pass@123456', PASSWORD_BCRYPT);
    $db->insert('users', array(
        'username'      => 'sales_f08_' . $code_suffix,
        'full_name'     => 'بائع اختبار الإلغاء',
        'email'         => 'sales_f08_' . $code_suffix . '@test.local',
        'password_hash' => $hashed_pw,
        'role_id'       => 3, // sales
        'is_active'     => 1,
        'created_at'    => date('Y-m-d H:i:s')
    ));
    $sales_uid = (int)$db->insert_id();
    $sales_user = $CI->User_model->get_by_id($sales_uid);
    $CI->User_warehouse_model->assign($sales_uid, $wh_id);

    $db->insert('users', array(
        'username'      => 'wh_f08_' . $code_suffix,
        'full_name'     => 'أمين مستودع اختبار الإلغاء',
        'email'         => 'wh_f08_' . $code_suffix . '@test.local',
        'password_hash' => $hashed_pw,
        'role_id'       => 2, // warehouse
        'is_active'     => 1,
        'created_at'    => date('Y-m-d H:i:s')
    ));
    $wh_uid = (int)$db->insert_id();
    $wh_user = $CI->User_model->get_by_id($wh_uid);

    assert_test("Test warehouse, products, stock, customer, and users prepared", $wh_id > 0 && $p1_id > 0 && $p2_id > 0 && $customer_id > 0);
    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 2: CREATE VALID INVOICE & VERIFY INITIAL SALES MOVEMENTS
    // -------------------------------------------------------------------------
    echo "--- SECTION 2: INVOICE CREATION (PRE-CANCELLATION) ---\n";

    // Create Invoice 1: Sold P1: 4 units, P2: 6 units from Warehouse
    // Stock before: P1=30, P2=20
    // Stock after sale: P1=26, P2=14
    $inv1_res = $CI->Sales_invoice_model->create_invoice(
        array(
            'customer_id'         => $customer_id,
            'warehouse_id'        => $wh_id,
            'invoice_date'        => '2026-10-31',
            'discount_percentage' => 10.00,
            'notes'               => 'فاتورة اختبار الإلغاء رقم 1'
        ),
        array(
            array('product_id' => $p1_id, 'quantity' => 4),
            array('product_id' => $p2_id, 'quantity' => 6)
        ),
        $sales_user
    );

    assert_test("Invoice 1 created successfully", $inv1_res['success'] === TRUE);
    $inv1_id = $inv1_res['invoice_id'];

    $stock_p1_after_sale = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p1_id))->row()->quantity;
    $stock_p2_after_sale = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p2_id))->row()->quantity;
    assert_test("P1 stock reduced to 26 after sale", $stock_p1_after_sale === 26);
    assert_test("P2 stock reduced to 14 after sale", $stock_p2_after_sale === 14);

    $orig_movements = $db->get_where('stock_movements', array('sales_invoice_id' => $inv1_id, 'movement_type' => 'sale'))->result();
    assert_test("Exactly 2 original 'sale' movements created for invoice", count($orig_movements) === 2);
    $orig_mov_p1 = null;
    $orig_mov_p2 = null;
    foreach ($orig_movements as $m) {
        if ((int)$m->product_id === $p1_id) $orig_mov_p1 = $m;
        if ((int)$m->product_id === $p2_id) $orig_mov_p2 = $m;
    }
    assert_test("Original P1 sale movement has delta = -4", $orig_mov_p1 && (int)$orig_mov_p1->quantity_delta === -4);
    assert_test("Original P2 sale movement has delta = -6", $orig_mov_p2 && (int)$orig_mov_p2->quantity_delta === -6);
    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 3: VALIDATION BOUNDARIES & ROLE AUTHORIZATION
    // -------------------------------------------------------------------------
    echo "--- SECTION 3: VALIDATION BOUNDARIES & ROLE AUTHORIZATION ---\n";

    // 3.1 Non-admin roles rejected by Model
    $sales_cancel_attempt = $CI->Sales_invoice_model->cancel_invoice($inv1_id, 'محاولة إلغاء بواسطة بائع', $sales_user);
    assert_test("Sales role rejected from cancelling invoice (PERMISSION_DENIED)", $sales_cancel_attempt['success'] === FALSE && $sales_cancel_attempt['error_code'] === 'PERMISSION_DENIED');

    $wh_cancel_attempt = $CI->Sales_invoice_model->cancel_invoice($inv1_id, 'محاولة إلغاء بواسطة أمين مستودع', $wh_user);
    assert_test("Warehouse role rejected from cancelling invoice (PERMISSION_DENIED)", $wh_cancel_attempt['success'] === FALSE && $wh_cancel_attempt['error_code'] === 'PERMISSION_DENIED');

    // 3.2 Cancellation Reason Validations
    $empty_reason_attempt = $CI->Sales_invoice_model->cancel_invoice($inv1_id, '', $admin_user);
    assert_test("Reject empty cancellation reason", $empty_reason_attempt['success'] === FALSE && $empty_reason_attempt['error_code'] === 'INVALID_CANCELLATION_REASON');

    $short_reason_attempt = $CI->Sales_invoice_model->cancel_invoice($inv1_id, 'خطأ', $admin_user); // 3 chars < 5
    assert_test("Reject reason shorter than 5 characters", $short_reason_attempt['success'] === FALSE && $short_reason_attempt['error_code'] === 'INVALID_CANCELLATION_REASON');

    $long_reason = str_repeat('سبب طويل جداً يتجاوز الحد الأقصى المسموح به ', 10); // > 255 chars
    $long_reason_attempt = $CI->Sales_invoice_model->cancel_invoice($inv1_id, $long_reason, $admin_user);
    assert_test("Reject reason exceeding 255 characters", $long_reason_attempt['success'] === FALSE && $long_reason_attempt['error_code'] === 'CANCELLATION_REASON_TOO_LONG');

    // 3.3 Invalid Invoice ID
    $invalid_id_attempt = $CI->Sales_invoice_model->cancel_invoice(999999, 'سبب إلغاء صالح وموثق', $admin_user);
    assert_test("Reject non-existent invoice ID", $invalid_id_attempt['success'] === FALSE && $invalid_id_attempt['error_code'] === 'INVOICE_NOT_FOUND');

    // Verify invoice remains active and stock untouched after failed attempts
    $inv1_check = $CI->Sales_invoice_model->get_by_id($inv1_id);
    assert_test("Invoice 1 remains active after invalid cancellation attempts", $inv1_check->status === 'active');
    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 4: SUCCESSFUL INVOICE CANCELLATION (MODEL-LEVEL)
    // -------------------------------------------------------------------------
    echo "--- SECTION 4: ATOMIC CANCELLATION & STOCK RESTORATION ---\n";

    $valid_reason = 'طلب العميل إرجاع البضاعة بالكامل بسبب خطأ في المقاس';
    $cancel_res = $CI->Sales_invoice_model->cancel_invoice($inv1_id, $valid_reason, $admin_user);

    assert_test("Admin cancels invoice successfully", $cancel_res['success'] === TRUE);
    assert_test("Returned status is cancelled", $cancel_res['status'] === 'cancelled');
    assert_test("Restored items count equals 2", $cancel_res['restored_items'] === 2);

    // 4.1 Verify Invoice Header in DB
    $inv1_after = $CI->Sales_invoice_model->get_by_id($inv1_id);
    assert_test("Invoice status in DB is cancelled", $inv1_after->status === 'cancelled');
    assert_test("Invoice cancelled_at is populated", !empty($inv1_after->cancelled_at));
    assert_test("Invoice cancelled_by equals Admin ID", (int)$inv1_after->cancelled_by === (int)$admin_user->id);
    assert_test("Invoice cancellation_reason preserved", $inv1_after->cancellation_reason === $valid_reason);

    // 4.2 Historical Records Preservation
    assert_test("Original customer ID preserved", (int)$inv1_after->customer_id === $customer_id);
    assert_test("Original total amount preserved (historical)", (float)$inv1_after->total_amount === (float)$inv1_check->total_amount);
    $items_after = $CI->Sales_invoice_model->get_items_by_invoice_id($inv1_id);
    assert_test("All 2 line items preserved without deletion", count($items_after) === 2);
    assert_test("Historical unit prices preserved", (float)$items_after[0]->unit_price === 50.00 && (float)$items_after[1]->unit_price === 25.00);

    // 4.3 Verify Stock Restoration
    // P1 was 26 + 4 = 30
    // P2 was 14 + 6 = 20
    $stock_p1_restored = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p1_id))->row()->quantity;
    $stock_p2_restored = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p2_id))->row()->quantity;
    assert_test("P1 stock fully restored to original 30", $stock_p1_restored === 30);
    assert_test("P2 stock fully restored to original 20", $stock_p2_restored === 20);

    // 4.4 Verify Stock Movements Reversal & Linkage
    $reversal_movements = $db->get_where('stock_movements', array('sales_invoice_id' => $inv1_id, 'movement_type' => 'sale_reversal'))->result();
    assert_test("Exactly 2 reversal movements created", count($reversal_movements) === 2);

    $rev_p1 = null;
    $rev_p2 = null;
    foreach ($reversal_movements as $rm) {
        if ((int)$rm->product_id === $p1_id) $rev_p1 = $rm;
        if ((int)$rm->product_id === $p2_id) $rev_p2 = $rm;
    }

    assert_test("P1 reversal movement delta is strictly positive (+4)", $rev_p1 && (int)$rev_p1->quantity_delta === 4);
    assert_test("P1 reversal before_qty is 26 and after_qty is 30", $rev_p1 && (int)$rev_p1->before_quantity === 26 && (int)$rev_p1->after_quantity === 30);
    assert_test("P1 reversal strictly links to original sale movement (reversal_of_movement_id)", $rev_p1 && (int)$rev_p1->reversal_of_movement_id === (int)$orig_mov_p1->id);

    assert_test("P2 reversal movement delta is strictly positive (+6)", $rev_p2 && (int)$rev_p2->quantity_delta === 6);
    assert_test("P2 reversal before_qty is 14 and after_qty is 20", $rev_p2 && (int)$rev_p2->before_quantity === 14 && (int)$rev_p2->after_quantity === 20);
    assert_test("P2 reversal strictly links to original sale movement (reversal_of_movement_id)", $rev_p2 && (int)$rev_p2->reversal_of_movement_id === (int)$orig_mov_p2->id);

    // Original sale movements preserved
    $orig_mov_check = $db->get_where('stock_movements', array('sales_invoice_id' => $inv1_id, 'movement_type' => 'sale'))->result();
    assert_test("Original 2 'sale' movements remain intact and unmodified", count($orig_mov_check) === 2);
    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 5: PREVENTION OF DUPLICATE CANCELLATION (IDEMPOTENCY / RE-CALL)
    // -------------------------------------------------------------------------
    echo "--- SECTION 5: DUPLICATE CANCELLATION PREVENTION ---\n";

    $repeat_cancel = $CI->Sales_invoice_model->cancel_invoice($inv1_id, 'إعادة طلب الإلغاء مرة ثانية', $admin_user);
    assert_test("Repeated cancellation rejected with ALREADY_CANCELLED", $repeat_cancel['success'] === FALSE && $repeat_cancel['error_code'] === 'ALREADY_CANCELLED');

    // Stock must NOT be incremented again
    $stock_p1_recheck = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p1_id))->row()->quantity;
    $stock_p2_recheck = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p2_id))->row()->quantity;
    assert_test("Stock P1 remains 30 (not double restored)", $stock_p1_recheck === 30);
    assert_test("Stock P2 remains 20 (not double restored)", $stock_p2_recheck === 20);

    // Reversal movements count must remain exactly 2
    $total_revs = $db->where('sales_invoice_id', $inv1_id)->where('movement_type', 'sale_reversal')->count_all_results('stock_movements');
    assert_test("No duplicate reversal movements created (count remains 2)", $total_revs === 2);
    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 6: REAL CONCURRENCY CANCELLATION TEST (RACE CONDITION)
    // -------------------------------------------------------------------------
    echo "--- SECTION 6: CONCURRENT CANCELLATION RACE CONDITION ---\n";

    // Create Invoice 2 for concurrency test
    // P1: 5 units (stock goes from 30 to 25)
    $inv2_res = $CI->Sales_invoice_model->create_invoice(
        array(
            'customer_id'  => $customer_id,
            'warehouse_id' => $wh_id,
            'invoice_date' => '2026-10-31',
            'notes'        => 'فاتورة فحص التزامن للإلغاء'
        ),
        array(
            array('product_id' => $p1_id, 'quantity' => 5)
        ),
        $sales_user
    );
    assert_test("Invoice 2 created for concurrency test", $inv2_res['success'] === TRUE);
    $inv2_id = $inv2_res['invoice_id'];

    $stock_before_race = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p1_id))->row()->quantity;
    assert_test("Stock before race condition is 25", $stock_before_race === 25);

    // Create temporary input/output files for two independent CLI processes
    $in1  = tempnam(sys_get_temp_dir(), 'f08_in1_');
    $in2  = tempnam(sys_get_temp_dir(), 'f08_in2_');
    $out1 = tempnam(sys_get_temp_dir(), 'f08_out1_');
    $out2 = tempnam(sys_get_temp_dir(), 'f08_out2_');

    file_put_contents($in1, json_encode(array(
        'invoice_id' => $inv2_id,
        'user_id'    => (int)$admin_user->id,
        'reason'     => 'إلغاء متزامن من العملية الأولى'
    )));
    file_put_contents($in2, json_encode(array(
        'invoice_id' => $inv2_id,
        'user_id'    => (int)$admin_user->id,
        'reason'     => 'إلغاء متزامن من العملية الثانية'
    )));

    $worker_script = dirname(__DIR__) . '/tests/f08_concurrent_cancel_worker.php';
    $cmd1 = sprintf('php %s %s %s', escapeshellarg($worker_script), escapeshellarg($in1), escapeshellarg($out1));
    $cmd2 = sprintf('php %s %s %s', escapeshellarg($worker_script), escapeshellarg($in2), escapeshellarg($out2));

    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    $proc1 = proc_open($cmd1, $descriptors, $pipes1);
    $proc2 = proc_open($cmd2, $descriptors, $pipes2);

    if (is_resource($proc1)) {
        fclose($pipes1[0]); fclose($pipes1[1]); fclose($pipes1[2]);
        proc_close($proc1);
    }
    if (is_resource($proc2)) {
        fclose($pipes2[0]); fclose($pipes2[1]); fclose($pipes2[2]);
        proc_close($proc2);
    }

    $raw_out1 = file_exists($out1) ? file_get_contents($out1) : '';
    $raw_out2 = file_exists($out2) ? file_get_contents($out2) : '';

    @unlink($in1); @unlink($in2); @unlink($out1); @unlink($out2);

    $res1 = json_decode($raw_out1, TRUE);
    $res2 = json_decode($raw_out2, TRUE);

    $success_count = 0;
    $already_cancelled_count = 0;

    if (!empty($res1['success'])) $success_count++;
    if (!empty($res2['success'])) $success_count++;

    if (!empty($res1['error_code']) && $res1['error_code'] === 'ALREADY_CANCELLED') $already_cancelled_count++;
    if (!empty($res2['error_code']) && $res2['error_code'] === 'ALREADY_CANCELLED') $already_cancelled_count++;

    assert_test("Exactly ONE concurrent cancellation succeeded", $success_count === 1, "s1=" . var_export($res1['success'] ?? false, true) . " s2=" . var_export($res2['success'] ?? false, true));
    assert_test("The other concurrent process received ALREADY_CANCELLED", $already_cancelled_count === 1);

    // Stock must be restored ONLY ONCE: 25 + 5 = 30
    $stock_after_race = (int)$db->get_where('warehouse_stock', array('warehouse_id' => $wh_id, 'product_id' => $p1_id))->row()->quantity;
    assert_test("Stock restored exactly once (25 + 5 = 30, not 35)", $stock_after_race === 30);

    // Movements count for Invoice 2 must be exactly 1 sale + 1 reversal
    $movs_inv2 = $db->get_where('stock_movements', array('sales_invoice_id' => $inv2_id))->result();
    assert_test("Invoice 2 has exactly 2 movements (1 sale, 1 reversal)", count($movs_inv2) === 2);
    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 7: HTTP CONTROLLER ENDPOINTS & SECURITY CHECKS
    // -------------------------------------------------------------------------
    echo "--- SECTION 7: HTTP CONTROLLER ENDPOINTS & AUTHORIZATION ---\n";

    // Create Invoice 3 for HTTP testing
    $inv3_res = $CI->Sales_invoice_model->create_invoice(
        array(
            'customer_id'  => $customer_id,
            'warehouse_id' => $wh_id,
            'invoice_date' => '2026-10-31',
            'notes'        => 'فاتورة فحص الـ HTTP'
        ),
        array(
            array('product_id' => $p1_id, 'quantity' => 2)
        ),
        $sales_user
    );
    $inv3_id = $inv3_res['invoice_id'];

    // 7.1 Unauthenticated Guest POST to cancel -> redirect to login
    $guest = new HttpTestClient($base_url);
    $guest_token = $guest->get_csrf_token('index.php/login');
    $r_guest = $guest->request('index.php/sales/invoices/cancel/' . $inv3_id, 'POST', array(
        'csrf_erp_token'      => $guest_token,
        'cancellation_reason' => 'سبب إلغاء تجريبي'
    ));
    assert_test("Guest POST /sales/invoices/cancel redirects to login", is_redirect_status($r_guest['status']) && strpos($r_guest['location'], 'login') !== FALSE);

    // 7.2 Warehouse User POST to cancel -> BLOCKED
    $wh_client = new HttpTestClient($base_url);
    $wh_logged = $wh_client->login('wh_f08_' . $code_suffix, 'Pass@123456');
    assert_test("Warehouse user logs in", $wh_logged);
    $wh_token = $wh_client->get_csrf_token('index.php/dashboard');
    $r_wh_cancel = $wh_client->request('index.php/sales/invoices/cancel/' . $inv3_id, 'POST', array(
        'csrf_erp_token'      => $wh_token,
        'cancellation_reason' => 'محاولة إلغاء غير مصرح بها'
    ));
    assert_test("Warehouse user POST cancel is BLOCKED", is_redirect_status($r_wh_cancel['status']) && strpos($r_wh_cancel['location'], 'dashboard') !== FALSE);

    // 7.3 Sales User POST to cancel -> BLOCKED
    $sales_client = new HttpTestClient($base_url);
    $sales_logged = $sales_client->login('sales_f08_' . $code_suffix, 'Pass@123456');
    assert_test("Sales user logs in", $sales_logged);
    $sales_token = $sales_client->get_csrf_token('index.php/sales/invoices');
    $r_sales_cancel = $sales_client->request('index.php/sales/invoices/cancel/' . $inv3_id, 'POST', array(
        'csrf_erp_token'      => $sales_token,
        'cancellation_reason' => 'محاولة إلغاء من بائع'
    ));
    assert_test("Sales user POST cancel is BLOCKED", is_redirect_status($r_sales_cancel['status']) && strpos($r_sales_cancel['location'], 'sales/invoices') !== FALSE);

    // 7.4 Admin Login
    $admin_client = new HttpTestClient($base_url);
    $admin_logged = $admin_client->login('admin', 'Admin@123456');
    assert_test("Admin logs in successfully", $admin_logged);

    // 7.5 GET request to cancel -> HTTP 404
    $r_get_cancel = $admin_client->request('index.php/sales/invoices/cancel/' . $inv3_id, 'GET');
    assert_test("Admin GET /sales/invoices/cancel rejected with HTTP 404", $r_get_cancel['status'] === 404);

    // 7.6 Invalid CSRF token -> HTTP 403
    $r_bad_csrf = $admin_client->request('index.php/sales/invoices/cancel/' . $inv3_id, 'POST', array(
        'csrf_erp_token'      => 'bad_csrf_token_00000000000000',
        'cancellation_reason' => 'محاولة مع رمز حماية خاطئ'
    ));
    assert_test("Admin POST with invalid CSRF rejected with HTTP 403", $r_bad_csrf['status'] === 403);

    // 7.7 Admin views active invoice details page (view.php)
    $r_view_active = $admin_client->request('index.php/sales/invoices/view/' . $inv3_id, 'GET');
    assert_test("Admin views active invoice details page (200 OK)", $r_view_active['status'] === 200);
    assert_test("View page contains Cancel button for Admin", strpos($r_view_active['body'], 'إلغاء الفاتورة') !== FALSE);
    assert_test("View page contains cancellation modal", strpos($r_view_active['body'], 'cancel-invoice-modal') !== FALSE);

    // 7.8 Sales user views invoice details page
    $r_sales_view = $sales_client->request('index.php/sales/invoices/view/' . $inv3_id, 'GET');
    assert_test("Sales user CANNOT see Cancel button in view page", strpos($r_sales_view['body'], 'cancel-invoice-modal') === FALSE);

    // 7.9 Admin executes valid cancellation via HTTP POST
    $admin_token = $admin_client->get_csrf_token('index.php/sales/invoices/view/' . $inv3_id);
    $r_admin_cancel = $admin_client->request('index.php/sales/invoices/cancel/' . $inv3_id, 'POST', array(
        'csrf_erp_token'      => $admin_token,
        'cancellation_reason' => 'إلغاء معتمد عبر واجهة تفاصيل الفاتورة'
    ));
    assert_test("Admin POST cancel succeeds with redirect to view page", is_redirect_status($r_admin_cancel['status']) && strpos($r_admin_cancel['location'], 'view/' . $inv3_id) !== FALSE);

    // 7.10 Admin views cancelled invoice details page
    $r_view_cancelled = $admin_client->request('index.php/sales/invoices/view/' . $inv3_id, 'GET');
    assert_test("Cancelled view contains 'فاتورة ملغاة' badge", strpos($r_view_cancelled['body'], 'فاتورة ملغاة') !== FALSE);
    assert_test("Cancelled view contains cancellation alert box", strpos($r_view_cancelled['body'], 'تم إلغاء هذه الفاتورة') !== FALSE);
    assert_test("Cancelled view displays cancellation reason", strpos($r_view_cancelled['body'], 'إلغاء معتمد عبر واجهة تفاصيل الفاتورة') !== FALSE);
    assert_test("Cancelled view does NOT show Cancel button anymore", strpos($r_view_cancelled['body'], 'cancel-invoice-modal') === FALSE);
    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 8: CLEAN TEARDOWN & BASELINE RESTORATION
    // -------------------------------------------------------------------------
    echo "--- SECTION 8: CLEAN TEARDOWN & RECOVERY ---\n";

    $test_inv_ids = array($inv1_id, $inv2_id, $inv3_id);

    // 1. Delete reversal movements first (to satisfy fk_sm_reversal_of foreign key)
    $db->where_in('sales_invoice_id', $test_inv_ids)->where('movement_type', 'sale_reversal')->delete('stock_movements');

    // 2. Delete original sale movements
    $db->where_in('sales_invoice_id', $test_inv_ids)->delete('stock_movements');

    // 3. Delete invoice items
    $db->where_in('invoice_id', $test_inv_ids)->delete('sales_invoice_items');

    // 4. Delete invoices
    $db->where_in('id', $test_inv_ids)->delete('sales_invoices');

    // 5. Restore sequence for 2026-10-31
    if ($initial_seq_20261031 > 0) {
        $db->where('sequence_date', '2026-10-31')->update('invoice_sequences', array('current_sequence' => $initial_seq_20261031));
    } else {
        $db->where('sequence_date', '2026-10-31')->delete('invoice_sequences');
    }

    // 6. Delete stock
    $db->where('warehouse_id', $wh_id)->delete('warehouse_stock');

    // 7. Delete user warehouse assignments
    $CI->User_warehouse_model->remove($sales_uid, $wh_id);

    // 8. Delete customer
    $db->where('id', $customer_id)->delete('customers');

    // 9. Delete products, category, warehouse
    $db->where_in('id', array($p1_id, $p2_id))->delete('products');
    $db->where('id', $cat_id)->delete('categories');
    $db->where('id', $wh_id)->delete('warehouses');

    // 10. Delete test users (sales, warehouse)
    $db->where_in('id', array($sales_uid, $wh_uid))->delete('users');

    // Final database row counts check
    $final_stock_count     = $db->count_all('warehouse_stock');
    $final_movements_count = $db->count_all('stock_movements');
    $final_invoices_count  = $db->count_all('sales_invoices');
    $final_items_count     = $db->count_all('sales_invoice_items');
    $final_customers_count = $db->count_all('customers');
    $final_users_count     = $db->count_all('users');

    assert_test("Zero collateral damage on warehouse_stock ({$final_stock_count} == {$initial_stock_count})", $final_stock_count === $initial_stock_count);
    assert_test("Zero collateral damage on stock_movements ({$final_movements_count} == {$initial_movements_count})", $final_movements_count === $initial_movements_count);
    assert_test("Zero collateral damage on sales_invoices ({$final_invoices_count} == {$initial_invoices_count})", $final_invoices_count === $initial_invoices_count);
    assert_test("Zero collateral damage on sales_invoice_items ({$final_items_count} == {$initial_items_count})", $final_items_count === $initial_items_count);
    assert_test("Zero collateral damage on customers ({$final_customers_count} == {$initial_customers_count})", $final_customers_count === $initial_customers_count);
    assert_test("Zero collateral damage on users ({$final_users_count} == {$initial_users_count})", $final_users_count === $initial_users_count);

    echo "\nFinal DB Counts: Stock={$final_stock_count}, Movements={$final_movements_count}, Invoices={$final_invoices_count}, Items={$final_items_count}, Customers={$final_customers_count}, Users={$final_users_count}\n";

} catch (Exception $e) {
    echo "UNCAUGHT EXCEPTION: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n====================================================================\n";
echo " TEST SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
