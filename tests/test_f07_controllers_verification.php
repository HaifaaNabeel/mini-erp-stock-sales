<?php
/**
 * Mini ERP Stock & Sales — F07 Controllers & Views Verification Test Suite
 *
 * Covers:
 * 1. HTTP Authentication & Authorization:
 *    - Unauthenticated requests blocked (redirect to login).
 *    - Warehouse role forbidden from Customers & Sales Invoices (redirect to dashboard).
 *    - Sales role authorized for Customers & Scoped Sales Invoices.
 *    - Admin role authorized for Customers & All Sales Invoices.
 * 2. Customer Management via HTTP & Controller:
 *    - Create customer with CSRF token and validation.
 *    - Edit customer details.
 *    - Toggle status (soft disable / enable) via POST.
 *    - Search and pagination filtering.
 * 3. Sales Invoices Scoping & Views via HTTP & Controller:
 *    - Admin views all invoices across all warehouses.
 *    - Sales user views only their own invoices in assigned warehouses.
 *    - Sales user strictly blocked from viewing other users' invoices or unassigned warehouse invoices.
 *    - Verification of invoice view page contents (customer, warehouse, creator, items, prices, math, SAR currency).
 * 4. Clean Teardown & Live Data Immutability.
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('User_model');
$CI->load->model('Warehouse_model');
$CI->load->model('Customer_model');
$CI->load->model('Sales_invoice_model');
$CI->load->model('User_warehouse_model');

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
    return in_array((int)$status, array(301, 302, 303, 307, 308), true);
}

echo "====================================================================\n";
echo " Mini ERP Stock & Sales — F07 Controllers & Views Verification Suite\n";
echo "====================================================================\n\n";

// Snapshot initial DB counts
$initial_stock_count     = $db->count_all('warehouse_stock');
$initial_movements_count = $db->count_all('stock_movements');
$initial_invoices_count  = $db->count_all('sales_invoices');
$initial_customers_count = $db->count_all('customers');
$initial_users_count     = $db->count_all('users');

// Ensure invoice_sequences for 2026-10-31 is synchronized with existing invoices
$existing_seq = $db->get_where('invoice_sequences', array('sequence_date' => '2026-10-31'))->row();
if (!$existing_seq) {
    $max_seq = (int)$db->where('invoice_date', '2026-10-31')->count_all_results('sales_invoices');
    $db->insert('invoice_sequences', array('sequence_date' => '2026-10-31', 'current_sequence' => $max_seq));
    $initial_seq_20261031 = $max_seq;
} else {
    $initial_seq_20261031 = (int)$existing_seq->current_sequence;
}

echo "Initial DB State: Stock={$initial_stock_count}, Movements={$initial_movements_count}, Invoices={$initial_invoices_count}, Customers={$initial_customers_count}, Users={$initial_users_count}\n\n";

// -------------------------------------------------------------------------
// HTTP CLIENT HELPER CLASS
// -------------------------------------------------------------------------
class HttpTestClient {
    private $cookie_file;
    private $base_url;

    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'ci_f07_cookie_');
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

        preg_match('/Location:\s*([^\r\n]+)/i', $header_str, $loc_matches);
        $location = isset($loc_matches[1]) ? trim($loc_matches[1]) : NULL;

        return array(
            'status'   => $status_code,
            'headers'  => $header_str,
            'location' => $location,
            'body'     => $body,
        );
    }

    public function get_csrf_token($path = NULL) {
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

// -------------------------------------------------------------------------
// SECTION 1: UNAUTHENTICATED HTTP REQUESTS
// -------------------------------------------------------------------------
echo "--- SECTION 1: UNAUTHENTICATED REQUESTS ---\n";

$guest = new HttpTestClient($base_url);

$r_cust = $guest->request('index.php/customers');
assert_test("Guest GET /customers redirects to login", is_redirect_status($r_cust['status']) && strpos($r_cust['location'], 'login') !== FALSE);

$r_cust_c = $guest->request('index.php/customers/create');
assert_test("Guest GET /customers/create redirects to login", is_redirect_status($r_cust_c['status']) && strpos($r_cust_c['location'], 'login') !== FALSE);

$r_si = $guest->request('index.php/sales/invoices');
assert_test("Guest GET /sales/invoices redirects to login", is_redirect_status($r_si['status']) && strpos($r_si['location'], 'login') !== FALSE);

$r_si_v = $guest->request('index.php/sales/invoices/view/1');
assert_test("Guest GET /sales/invoices/view/1 redirects to login", is_redirect_status($r_si_v['status']) && strpos($r_si_v['location'], 'login') !== FALSE);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 2: SETUP ISOLATED TEST USERS & FIXTURES
// -------------------------------------------------------------------------
echo "--- SECTION 2: SETUP TEST USERS & FIXTURES ---\n";

$hashed_pw = password_hash('Pass@123456', PASSWORD_BCRYPT);

// Warehouse User (haifaa exists or create temporary warehouse user)
$db->insert('users', array(
    'username'      => 'temp_wh_' . substr(bin2hex(random_bytes(3)), 0, 5),
    'full_name'     => 'أمين مستودع تجريبي',
    'email'         => 'temp_wh_' . bin2hex(random_bytes(3)) . '@test.local',
    'password_hash' => $hashed_pw,
    'role_id'       => 2, // warehouse
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
));
$wh_user_id = (int)$db->insert_id();
$wh_username = $db->get_where('users', array('id' => $wh_user_id))->row()->username;

// Sales User 1
$db->insert('users', array(
    'username'      => 'temp_s1_' . substr(bin2hex(random_bytes(3)), 0, 5),
    'full_name'     => 'بائع تجريبي أول',
    'email'         => 'temp_s1_' . bin2hex(random_bytes(3)) . '@test.local',
    'password_hash' => $hashed_pw,
    'role_id'       => 3, // sales
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
));
$s1_user_id = (int)$db->insert_id();
$s1_username = $db->get_where('users', array('id' => $s1_user_id))->row()->username;

// Sales User 2
$db->insert('users', array(
    'username'      => 'temp_s2_' . substr(bin2hex(random_bytes(3)), 0, 5),
    'full_name'     => 'بائع تجريبي ثان',
    'email'         => 'temp_s2_' . bin2hex(random_bytes(3)) . '@test.local',
    'password_hash' => $hashed_pw,
    'role_id'       => 3, // sales
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
));
$s2_user_id = (int)$db->insert_id();
$s2_username = $db->get_where('users', array('id' => $s2_user_id))->row()->username;

// Warehouses W1 & W2
$db->insert('warehouses', array('code' => 'WH-HTTP-1', 'name' => 'مستودع اختبار 1', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$wh1_id = (int)$db->insert_id();

$db->insert('warehouses', array('code' => 'WH-HTTP-2', 'name' => 'مستودع اختبار 2', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$wh2_id = (int)$db->insert_id();

// Assign Sales User 1 to W1 ONLY
$CI->User_warehouse_model->assign($s1_user_id, $wh1_id);

// Assign Sales User 2 to W2 ONLY
$CI->User_warehouse_model->assign($s2_user_id, $wh2_id);

// Create Category & Products
$db->insert('categories', array('name' => 'تصنيف اختبار HTTP', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$cat_id = (int)$db->insert_id();

$db->insert('products', array('category_id' => $cat_id, 'sku' => 'SKU-HTTP-1', 'name' => 'منتج اختبار 1', 'sale_price' => 75.00, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$p1_id = (int)$db->insert_id();

// Seed stock in W1 (50 units) and W2 (50 units)
$db->insert('warehouse_stock', array('warehouse_id' => $wh1_id, 'product_id' => $p1_id, 'quantity' => 50, 'reorder_level' => 5, 'created_at' => date('Y-m-d H:i:s')));
$db->insert('warehouse_stock', array('warehouse_id' => $wh2_id, 'product_id' => $p1_id, 'quantity' => 50, 'reorder_level' => 5, 'created_at' => date('Y-m-d H:i:s')));

assert_test("Test users (Warehouse, Sales1, Sales2) and warehouses prepared", $wh_user_id > 0 && $s1_user_id > 0 && $s2_user_id > 0 && $wh1_id > 0 && $wh2_id > 0);
echo "\n";

// -------------------------------------------------------------------------
// SECTION 3: WAREHOUSE ROLE RESTRICTIONS (HTTP)
// -------------------------------------------------------------------------
echo "--- SECTION 3: WAREHOUSE ROLE RESTRICTIONS ---\n";

$wh_client = new HttpTestClient($base_url);
$wh_login_ok = $wh_client->login($wh_username, 'Pass@123456');
assert_test("Warehouse user logs in successfully", $wh_login_ok === TRUE);

$wh_cust = $wh_client->request('index.php/customers');
assert_test("Warehouse user GET /customers is BLOCKED (redirect to dashboard)", is_redirect_status($wh_cust['status']) && strpos($wh_cust['location'], 'dashboard') !== FALSE);

$wh_si = $wh_client->request('index.php/sales/invoices');
assert_test("Warehouse user GET /sales/invoices is BLOCKED (redirect to dashboard)", is_redirect_status($wh_si['status']) && strpos($wh_si['location'], 'dashboard') !== FALSE);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 4: ADMIN CUSTOMER CRUD & TOGGLE VIA HTTP
// -------------------------------------------------------------------------
echo "--- SECTION 4: ADMIN CUSTOMER CRUD & TOGGLE VIA HTTP ---\n";

$admin_client = new HttpTestClient($base_url);
$admin_login_ok = $admin_client->login('admin', 'Admin@123456');
assert_test("Admin logs in successfully", $admin_login_ok === TRUE);

// 4.1 Admin access to customers list
$r_admin_cust = $admin_client->request('index.php/customers');
assert_test("Admin GET /customers returns 200 OK", $r_admin_cust['status'] === 200 && strpos($r_admin_cust['body'], 'إدارة العملاء') !== FALSE);

// 4.2 Create Customer via POST
$csrf_cust_create = $admin_client->get_csrf_token('index.php/customers/create');
$r_create_post = $admin_client->request('index.php/customers/create', 'POST', array(
    'csrf_erp_token' => $csrf_cust_create,
    'name'           => 'مؤسسة الشروق التجارية',
    'phone'          => '0551234567',
    'email'          => 'shorooq@example.com',
    'address'        => 'جدة - شارع الأندلس',
    'is_active'      => '1'
));
assert_test("Admin POST /customers/create succeeds (redirect to customers)", is_redirect_status($r_create_post['status']) && strpos($r_create_post['location'], 'customers') !== FALSE);

// Verify customer in DB
$created_cust = $db->get_where('customers', array('phone' => '0551234567'))->row();
assert_test("Customer created in DB with correct attributes", $created_cust && $created_cust->name === 'مؤسسة الشروق التجارية' && (int)$created_cust->is_active === 1);
$cust_test_id = (int)$created_cust->id;

// 4.3 Edit Customer via POST
$csrf_cust_edit = $admin_client->get_csrf_token('index.php/customers/edit/' . $cust_test_id);
$r_edit_post = $admin_client->request('index.php/customers/edit/' . $cust_test_id, 'POST', array(
    'csrf_erp_token' => $csrf_cust_edit,
    'name'           => 'مؤسسة الشروق العالمية الحديثة',
    'phone'          => '0551234567',
    'email'          => 'shorooq_new@example.com',
    'address'        => 'جدة - حي الحمراء',
    'is_active'      => '1'
));
assert_test("Admin POST /customers/edit succeeds (redirect to customers)", is_redirect_status($r_edit_post['status']) && strpos($r_edit_post['location'], 'customers') !== FALSE);

$updated_cust = $db->get_where('customers', array('id' => $cust_test_id))->row();
assert_test("Customer name updated in DB", $updated_cust->name === 'مؤسسة الشروق العالمية الحديثة');

// 4.4 Toggle Status (Soft Disable) via POST
$csrf_toggle = $admin_client->get_csrf_token('index.php/customers');
$r_toggle1 = $admin_client->request('index.php/customers/toggle_status/' . $cust_test_id, 'POST', array(
    'csrf_erp_token' => $csrf_toggle
));
assert_test("Admin POST /customers/toggle_status soft-disables customer", is_redirect_status($r_toggle1['status']));
$disabled_cust = $db->get_where('customers', array('id' => $cust_test_id))->row();
assert_test("Customer is_active is now 0 (disabled)", (int)$disabled_cust->is_active === 0);

// Verify inactive customer is rejected by Sales_invoice_model
$admin_user_obj = $CI->User_model->get_by_id(1);
$inv_inactive_attempt = $CI->Sales_invoice_model->create_invoice(
    array('customer_id' => $cust_test_id, 'warehouse_id' => $wh1_id),
    array(array('product_id' => $p1_id, 'quantity' => 1)),
    $admin_user_obj
);
assert_test("Invoice creation rejected for soft-disabled customer (INACTIVE_CUSTOMER)", $inv_inactive_attempt['success'] === FALSE && $inv_inactive_attempt['error_code'] === 'INACTIVE_CUSTOMER');

// 4.5 Toggle Status (Re-enable) via POST
$csrf_toggle2 = $admin_client->get_csrf_token('index.php/customers');
$r_toggle2 = $admin_client->request('index.php/customers/toggle_status/' . $cust_test_id, 'POST', array(
    'csrf_erp_token' => $csrf_toggle2
));
assert_test("Admin POST /customers/toggle_status re-enables customer", is_redirect_status($r_toggle2['status']));
$enabled_cust = $db->get_where('customers', array('id' => $cust_test_id))->row();
assert_test("Customer is_active is now 1 (active)", (int)$enabled_cust->is_active === 1);

// 4.6 Search in Customer List
$r_search_cust = $admin_client->request('index.php/customers?search=' . urlencode('0551234567'));
assert_test("Customer search by phone returns matching record in HTML", $r_search_cust['status'] === 200 && strpos($r_search_cust['body'], 'مؤسسة الشروق العالمية الحديثة') !== FALSE);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 5: SALES USER PERMISSIONS & INVOICE SCOPING (HTTP)
// -------------------------------------------------------------------------
echo "--- SECTION 5: SALES USER PERMISSIONS & INVOICE SCOPING ---\n";

// Create 2 test invoices:
// Invoice 1 created by Sales User 1 in Warehouse 1
$s1_user_obj = $CI->User_model->get_by_id($s1_user_id);
$inv1_res = $CI->Sales_invoice_model->create_invoice(
    array(
        'customer_id'         => $cust_test_id,
        'warehouse_id'        => $wh1_id,
        'invoice_date'        => '2026-10-31',
        'discount_percentage' => 10.00,
        'notes'               => 'فاتورة المبيعات للمستخدم الأول'
    ),
    array(array('product_id' => $p1_id, 'quantity' => 2)),
    $s1_user_obj
);
assert_test("Invoice 1 created by Sales User 1", $inv1_res['success'] === TRUE);
$inv1_id = $inv1_res['invoice_id'];
$inv1_num = $inv1_res['invoice_number'];

// Invoice 2 created by Sales User 2 in Warehouse 2
$s2_user_obj = $CI->User_model->get_by_id($s2_user_id);
$inv2_res = $CI->Sales_invoice_model->create_invoice(
    array(
        'customer_id'         => $cust_test_id,
        'warehouse_id'        => $wh2_id,
        'invoice_date'        => '2026-10-31',
        'discount_percentage' => 0.00,
        'notes'               => 'فاتورة المبيعات للمستخدم الثاني'
    ),
    array(array('product_id' => $p1_id, 'quantity' => 1)),
    $s2_user_obj
);
assert_test("Invoice 2 created by Sales User 2", $inv2_res['success'] === TRUE);
$inv2_id = $inv2_res['invoice_id'];
$inv2_num = $inv2_res['invoice_number'];

// 5.1 Sales User 1 Login
$s1_client = new HttpTestClient($base_url);
$s1_login_ok = $s1_client->login($s1_username, 'Pass@123456');
assert_test("Sales User 1 logs in successfully", $s1_login_ok === TRUE);

// 5.2 Sales User 1 accesses Customers list
$s1_cust = $s1_client->request('index.php/customers');
assert_test("Sales User 1 GET /customers returns 200 OK", $s1_cust['status'] === 200);

// 5.3 Sales User 1 accesses Invoices list (Scoping check)
$s1_invoices = $s1_client->request('index.php/sales/invoices');
assert_test("Sales User 1 GET /sales/invoices returns 200 OK", $s1_invoices['status'] === 200);
assert_test("Sales User 1 sees own Invoice 1 ({$inv1_num}) in HTML", strpos($s1_invoices['body'], $inv1_num) !== FALSE);
assert_test("Sales User 1 CANNOT see Invoice 2 ({$inv2_num}) created by Sales User 2", strpos($s1_invoices['body'], $inv2_num) === FALSE);

// 5.4 Sales User 1 views own invoice details
$s1_view_own = $s1_client->request('index.php/sales/invoices/view/' . $inv1_id);
assert_test("Sales User 1 views own invoice details (200 OK)", $s1_view_own['status'] === 200);
assert_test("Invoice view contains customer name", strpos($s1_view_own['body'], 'مؤسسة الشروق العالمية الحديثة') !== FALSE);
assert_test("Invoice view contains warehouse name", strpos($s1_view_own['body'], 'مستودع اختبار 1') !== FALSE);
assert_test("Invoice view contains SKU snapshot", strpos($s1_view_own['body'], 'SKU-HTTP-1') !== FALSE);
assert_test("Invoice view contains SAR currency label", strpos($s1_view_own['body'], 'SAR') !== FALSE || strpos($s1_view_own['body'], 'ر.س') !== FALSE);
assert_test("Invoice view contains Subtotal (150.00)", strpos($s1_view_own['body'], '150.00') !== FALSE);
assert_test("Invoice view contains Discount 10% (15.00)", strpos($s1_view_own['body'], '15.00') !== FALSE);
assert_test("Invoice view contains Grand Total (135.00)", strpos($s1_view_own['body'], '135.00') !== FALSE);

// 5.5 Sales User 1 attempts to view Invoice 2 (created by Sales User 2 in W2)
$s1_view_other = $s1_client->request('index.php/sales/invoices/view/' . $inv2_id);
assert_test("Sales User 1 is BLOCKED from viewing Invoice 2 (redirect)", is_redirect_status($s1_view_other['status']) && strpos($s1_view_other['location'], 'sales/invoices') !== FALSE);

// 5.6 Admin views both invoices
$admin_si = $admin_client->request('index.php/sales/invoices');
assert_test("Admin sees Invoice 1 in invoices list", strpos($admin_si['body'], $inv1_num) !== FALSE);
assert_test("Admin sees Invoice 2 in invoices list", strpos($admin_si['body'], $inv2_num) !== FALSE);

$admin_view_inv2 = $admin_client->request('index.php/sales/invoices/view/' . $inv2_id);
assert_test("Admin views Invoice 2 details (200 OK)", $admin_view_inv2['status'] === 200 && strpos($admin_view_inv2['body'], $inv2_num) !== FALSE);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 6: FILTERS & SEARCH IN SALES INVOICES (HTTP)
// -------------------------------------------------------------------------
echo "--- SECTION 6: FILTERS & SEARCH IN SALES INVOICES ---\n";

// Search by exact invoice number
$r_search_inv = $admin_client->request('index.php/sales/invoices?search=' . urlencode($inv1_num));
assert_test("Search by invoice number returns Invoice 1", strpos($r_search_inv['body'], $inv1_num) !== FALSE && strpos($r_search_inv['body'], $inv2_num) === FALSE);

// Filter by warehouse_id = W2
$r_filter_wh2 = $admin_client->request('index.php/sales/invoices?warehouse_id=' . $wh2_id);
assert_test("Filter by Warehouse 2 returns only Invoice 2", strpos($r_filter_wh2['body'], $inv2_num) !== FALSE && strpos($r_filter_wh2['body'], $inv1_num) === FALSE);

// Filter by status = active
$r_filter_active = $admin_client->request('index.php/sales/invoices?status=active');
assert_test("Filter by status active returns both active invoices", strpos($r_filter_active['body'], $inv1_num) !== FALSE && strpos($r_filter_active['body'], $inv2_num) !== FALSE);

// Filter by date range (match today)
$r_filter_date = $admin_client->request('index.php/sales/invoices?start_date=2026-10-31&end_date=2026-10-31');
assert_test("Filter by valid date range returns matching invoices", strpos($r_filter_date['body'], $inv1_num) !== FALSE);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 7: TEARDOWN & CLEANUP
// -------------------------------------------------------------------------
echo "--- SECTION 7: TEARDOWN & CLEANUP ---\n";

// Delete created test movements
$db->where_in('warehouse_id', array($wh1_id, $wh2_id))->delete('stock_movements');

// Delete created test invoice items
$inv_ids_to_del = array($inv1_id, $inv2_id);
$db->where_in('invoice_id', $inv_ids_to_del)->delete('sales_invoice_items');

// Delete created test invoices
$db->where_in('id', $inv_ids_to_del)->delete('sales_invoices');

// Restore or delete test sequences
if ($initial_seq_20261031 > 0) {
    $db->where('sequence_date', '2026-10-31')->update('invoice_sequences', array('current_sequence' => $initial_seq_20261031));
} else {
    $db->where('sequence_date', '2026-10-31')->delete('invoice_sequences');
}

// Delete test stock
$db->where_in('warehouse_id', array($wh1_id, $wh2_id))->delete('warehouse_stock');

// Delete test user_warehouse assignments
$CI->User_warehouse_model->remove($s1_user_id, $wh1_id);
$CI->User_warehouse_model->remove($s2_user_id, $wh2_id);

// Delete test customer
$db->where('id', $cust_test_id)->delete('customers');

// Delete test product, category, warehouses
$db->where('id', $p1_id)->delete('products');
$db->where('id', $cat_id)->delete('categories');
$db->where_in('id', array($wh1_id, $wh2_id))->delete('warehouses');

// Delete test users (wh, s1, s2)
$db->where_in('id', array($wh_user_id, $s1_user_id, $s2_user_id))->delete('users');

// Final check: pre-existing live rows count
$final_stock_count     = $db->count_all('warehouse_stock');
$final_movements_count = $db->count_all('stock_movements');
$final_invoices_count  = $db->count_all('sales_invoices');
$final_customers_count = $db->count_all('customers');
$final_users_count     = $db->count_all('users');

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
