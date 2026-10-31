<?php
/**
 * Mini ERP Stock & Sales — F07 Create Invoice UI & Dedicated Print View Verification Test Suite
 *
 * Verifies Phase 3 of F07:
 * 1. Unauthenticated Access Protection (create, ajax, print -> login redirect)
 * 2. Role-Based Access Scoping:
 *    - Warehouse role forbidden from create, ajax, and print.
 *    - Sales role authorized for create, scoped to assigned warehouses.
 *    - Admin role authorized for create, all warehouses.
 * 3. Dynamic AJAX Product Endpoint (sales/invoices/ajax_get_products):
 *    - Returns active products with stock.
 *    - Enforces warehouse assignment for Sales user.
 * 4. Invoice Creation via POST:
 *    - Validation: customer, warehouse, non-empty items, valid quantities.
 *    - Stock availability: blocks if requested quantity > available stock.
 *    - Financial integrity: calculates subtotal, discount, and grand total strictly on server.
 *    - Stock movements & ACID transaction: decrements stock and creates 'sale' movement.
 *    - Idempotency: replay prevention with unique key.
 * 5. Dedicated A4 Print View (sales/invoices/print/(:num)):
 *    - Clean HTML structure free of sidebar/topbar layout chrome.
 *    - Includes @media print and .no-print classes.
 *    - Authorization scoping: Sales user can only print own invoices in assigned warehouses.
 * 6. Clean Teardown & Database State Preservation.
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
$CI->load->model('Stock_model');

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
echo " Mini ERP Stock & Sales — F07 Phase 3: Create UI & Print Verification\n";
echo "====================================================================\n\n";

// Snapshot initial DB counts
$initial_stock_count     = $db->count_all('warehouse_stock');
$initial_movements_count = $db->count_all('stock_movements');
$initial_invoices_count  = $db->count_all('sales_invoices');
$initial_items_count     = $db->count_all('sales_invoice_items');
$initial_customers_count = $db->count_all('customers');
$initial_users_count     = $db->count_all('users');

echo "Initial DB Counts: Stock={$initial_stock_count}, Movements={$initial_movements_count}, Invoices={$initial_invoices_count}, Items={$initial_items_count}, Customers={$initial_customers_count}, Users={$initial_users_count}\n\n";

// HTTP Test Client Helper
class HttpTestClient {
    private $cookie_file;
    private $base_url;

    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'ci_f07_p3_');
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
            if (is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
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

try {

    // =========================================================================
    // SECTION 1: UNAUTHENTICATED ACCESS CHECKS
    // =========================================================================
    echo "--- SECTION 1: UNAUTHENTICATED ACCESS CHECKS ---\n";
    $guest = new HttpTestClient($base_url);

    $r1 = $guest->request('index.php/sales/invoices/create');
    assert_test("Guest GET sales/invoices/create redirects to login", is_redirect_status($r1['status']) && strpos($r1['location'], 'login') !== FALSE);

    $guest_token = $guest->get_csrf_token('index.php/login');
    $r2 = $guest->request('index.php/sales/invoices/create', 'POST', array('csrf_erp_token' => $guest_token, 'warehouse_id' => 1));
    assert_test("Guest POST sales/invoices/create redirects to login", is_redirect_status($r2['status']) && strpos($r2['location'], 'login') !== FALSE);

    $r3 = $guest->request('index.php/sales/invoices/ajax_get_products?warehouse_id=1');
    assert_test("Guest GET sales/invoices/ajax_get_products redirects or returns 401", is_redirect_status($r3['status']) || $r3['status'] === 401);

    $r4 = $guest->request('index.php/sales/invoices/print/1');
    assert_test("Guest GET sales/invoices/print/1 redirects to login", is_redirect_status($r4['status']) && strpos($r4['location'], 'login') !== FALSE);

    echo "\n";

    // =========================================================================
    // SECTION 2: SETUP FIXTURES (TEST USERS, WAREHOUSES, PRODUCTS, CUSTOMERS)
    // =========================================================================
    echo "--- SECTION 2: SETUP TEST FIXTURES ---\n";
    $hashed_pw = password_hash('TestPass@123', PASSWORD_BCRYPT);

    // 1. Warehouse user
    $db->insert('users', array(
        'username'      => 'twh_' . substr(bin2hex(random_bytes(3)), 0, 5),
        'full_name'     => 'مستخدم مستودع تجريبي',
        'email'         => 'twh_' . bin2hex(random_bytes(3)) . '@test.local',
        'password_hash' => $hashed_pw,
        'role_id'       => 2, // warehouse
        'is_active'     => 1,
        'created_at'    => date('Y-m-d H:i:s'),
    ));
    $wh_user_id = (int)$db->insert_id();
    $wh_username = $db->get_where('users', array('id' => $wh_user_id))->row()->username;

    // 2. Sales user 1
    $db->insert('users', array(
        'username'      => 'ts1_' . substr(bin2hex(random_bytes(3)), 0, 5),
        'full_name'     => 'مسؤول مبيعات أول',
        'email'         => 'ts1_' . bin2hex(random_bytes(3)) . '@test.local',
        'password_hash' => $hashed_pw,
        'role_id'       => 3, // sales
        'is_active'     => 1,
        'created_at'    => date('Y-m-d H:i:s'),
    ));
    $sales1_id = (int)$db->insert_id();
    $sales1_username = $db->get_where('users', array('id' => $sales1_id))->row()->username;

    // 3. Sales user 2 (other seller)
    $db->insert('users', array(
        'username'      => 'ts2_' . substr(bin2hex(random_bytes(3)), 0, 5),
        'full_name'     => 'مسؤول مبيعات ثان',
        'email'         => 'ts2_' . bin2hex(random_bytes(3)) . '@test.local',
        'password_hash' => $hashed_pw,
        'role_id'       => 3, // sales
        'is_active'     => 1,
        'created_at'    => date('Y-m-d H:i:s'),
    ));
    $sales2_id = (int)$db->insert_id();
    $sales2_username = $db->get_where('users', array('id' => $sales2_id))->row()->username;

    // Find two active warehouses
    $active_warehouses = $db->where('is_active', 1)->order_by('id', 'ASC')->get('warehouses')->result();
    $wh_assigned   = $active_warehouses[0];
    $wh_unassigned = $active_warehouses[1];

    // Assign wh_assigned to sales1 only
    $db->insert('user_warehouses', array(
        'user_id'      => $sales1_id,
        'warehouse_id' => $wh_assigned->id,
        'created_at'   => date('Y-m-d H:i:s'),
    ));

    // Assign wh_unassigned to sales2 only
    $db->insert('user_warehouses', array(
        'user_id'      => $sales2_id,
        'warehouse_id' => $wh_unassigned->id,
        'created_at'   => date('Y-m-d H:i:s'),
    ));

    // Create a temporary active customer
    $db->insert('customers', array(
        'name'       => 'شركة الأفق للاختبار F07',
        'phone'      => '0559988776',
        'email'      => 'horizon_test@test.local',
        'address'    => 'الرياض، المملكة العربية السعودية',
        'is_active'  => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ));
    $customer_id = (int)$db->insert_id();

    // Find a product and ensure known stock in wh_assigned
    $product = $db->where('is_active', 1)->order_by('id', 'ASC')->get('products')->row();
    $prod_id = (int)$product->id;
    $prod_price = (float)$product->sale_price;

    // Get current stock or initialize
    $stock_row = $db->where('warehouse_id', $wh_assigned->id)->where('product_id', $prod_id)->get('warehouse_stock')->row();
    $original_stock_qty = $stock_row ? (int)$stock_row->quantity : 0;
    // Set stock to 50 for testing
    if ($stock_row) {
        $db->where('id', $stock_row->id)->update('warehouse_stock', array('quantity' => 50));
    } else {
        $db->insert('warehouse_stock', array(
            'warehouse_id'  => $wh_assigned->id,
            'product_id'    => $prod_id,
            'quantity'      => 50,
            'reorder_level' => 5,
            'created_at'    => date('Y-m-d H:i:s'),
        ));
    }

    echo "Created users: wh={$wh_username}, sales1={$sales1_username}, sales2={$sales2_username}\n";
    echo "Customer ID: {$customer_id}, Product ID: {$prod_id} (Price: {$prod_price}), Stock in WH #{$wh_assigned->id} set to 50\n\n";

    // =========================================================================
    // SECTION 3: WAREHOUSE ROLE PERMISSION CHECKS (FORBIDDEN)
    // =========================================================================
    echo "--- SECTION 3: WAREHOUSE ROLE PERMISSION CHECKS ---\n";
    $wh_client = new HttpTestClient($base_url);
    $login_ok = $wh_client->login($wh_username, 'TestPass@123');
    assert_test("Warehouse user logs in successfully", $login_ok);

    $r_wh_c = $wh_client->request('index.php/sales/invoices/create');
    assert_test("Warehouse user GET sales/invoices/create is redirected to dashboard", is_redirect_status($r_wh_c['status']) && strpos($r_wh_c['location'], 'dashboard') !== FALSE);

    $r_wh_ajax = $wh_client->request('index.php/sales/invoices/ajax_get_products?warehouse_id=' . $wh_assigned->id, 'GET', array(), array('X-Requested-With' => 'XMLHttpRequest'));
    assert_test("Warehouse user AJAX ajax_get_products returns 403 Forbidden", $r_wh_ajax['status'] === 403);

    $r_wh_p = $wh_client->request('index.php/sales/invoices/print/1');
    assert_test("Warehouse user GET sales/invoices/print/1 is redirected to dashboard", is_redirect_status($r_wh_p['status']) && strpos($r_wh_p['location'], 'dashboard') !== FALSE);

    echo "\n";

    // =========================================================================
    // SECTION 4: SALES USER WAREHOUSE SCOPING & AJAX ENDPOINT
    // =========================================================================
    echo "--- SECTION 4: SALES USER SCOPING & AJAX PRODUCTS ---\n";
    $s1_client = new HttpTestClient($base_url);
    $login_s1 = $s1_client->login($sales1_username, 'TestPass@123');
    assert_test("Sales user 1 logs in successfully", $login_s1);

    // GET sales/invoices/create
    $r_s1_create = $s1_client->request('index.php/sales/invoices/create');
    assert_test("Sales user 1 GET sales/invoices/create returns 200 OK", $r_s1_create['status'] === 200);
    assert_test("Form contains customer option", strpos($r_s1_create['body'], 'شركة الأفق للاختبار F07') !== FALSE);
    assert_test("Form contains assigned warehouse", strpos($r_s1_create['body'], $wh_assigned->name) !== FALSE);
    assert_test("Form does NOT contain unassigned warehouse", strpos($r_s1_create['body'], $wh_unassigned->name) === FALSE);
    assert_test("Form contains 32-char idempotency_key input", preg_match('/name="idempotency_key"\s+value="([a-f0-9]{32})"/', $r_s1_create['body']) === 1);

    // AJAX get products for assigned warehouse
    $r_ajax_ok = $s1_client->request('index.php/sales/invoices/ajax_get_products?warehouse_id=' . $wh_assigned->id, 'GET', array(), array('X-Requested-With' => 'XMLHttpRequest'));
    assert_test("Sales user AJAX products for assigned warehouse returns 200 OK", $r_ajax_ok['status'] === 200);
    $ajax_data = json_decode($r_ajax_ok['body'], true);
    assert_test("AJAX response success is true", isset($ajax_data['success']) && $ajax_data['success'] === true);
    assert_test("AJAX response contains product array with stock", isset($ajax_data['products']) && count($ajax_data['products']) > 0);

    // Check that prod_id has stock_quantity == 50 in ajax response
    $found_prod = null;
    foreach ($ajax_data['products'] as $p) {
        if ((int)$p['id'] === $prod_id) {
            $found_prod = $p;
            break;
        }
    }
    assert_test("AJAX reports exact warehouse stock (50)", $found_prod !== null && (int)$found_prod['stock_quantity'] === 50);

    // AJAX get products for unassigned warehouse -> must return 403
    $r_ajax_forbidden = $s1_client->request('index.php/sales/invoices/ajax_get_products?warehouse_id=' . $wh_unassigned->id, 'GET', array(), array('X-Requested-With' => 'XMLHttpRequest'));
    assert_test("Sales user AJAX products for unassigned warehouse returns 403 Forbidden", $r_ajax_forbidden['status'] === 403);

    // AJAX get products with invalid warehouse ID -> must return 400
    $r_ajax_bad = $s1_client->request('index.php/sales/invoices/ajax_get_products?warehouse_id=-99', 'GET', array(), array('X-Requested-With' => 'XMLHttpRequest'));
    assert_test("AJAX products with invalid warehouse ID returns 400 Bad Request", $r_ajax_bad['status'] === 400);

    echo "\n";

    // =========================================================================
    // SECTION 5: ADMIN ROLE CHECKS & WAREHOUSE ACCESS
    // =========================================================================
    echo "--- SECTION 5: ADMIN ROLE CHECKS ---\n";
    $admin_client = new HttpTestClient($base_url);
    $login_adm = $admin_client->login('admin', 'Admin@123456');
    assert_test("Admin logs in successfully", $login_adm);

    $r_adm_create = $admin_client->request('index.php/sales/invoices/create');
    assert_test("Admin GET sales/invoices/create returns 200 OK", $r_adm_create['status'] === 200);
    assert_test("Admin sees assigned warehouse in dropdown", strpos($r_adm_create['body'], $wh_assigned->name) !== FALSE);
    assert_test("Admin sees unassigned warehouse in dropdown", strpos($r_adm_create['body'], $wh_unassigned->name) !== FALSE);

    $r_adm_ajax = $admin_client->request('index.php/sales/invoices/ajax_get_products?warehouse_id=' . $wh_unassigned->id, 'GET', array(), array('X-Requested-With' => 'XMLHttpRequest'));
    assert_test("Admin can access AJAX products for any warehouse (200 OK)", $r_adm_ajax['status'] === 200);

    echo "\n";

    // =========================================================================
    // SECTION 6: INVOICE CREATION POST VALIDATIONS
    // =========================================================================
    echo "--- SECTION 6: INVOICE CREATION POST VALIDATIONS ---\n";

    // 1. Empty items rejected
    $token = $s1_client->get_csrf_token('index.php/sales/invoices/create');
    $r_empty = $s1_client->request('index.php/sales/invoices/create', 'POST', array(
        'csrf_erp_token'  => $token,
        'idempotency_key' => bin2hex(random_bytes(16)),
        'customer_id'     => $customer_id,
        'warehouse_id'    => $wh_assigned->id,
        'invoice_date'    => date('Y-m-d'),
    ));
    assert_test("Empty invoice rejected (renders form with error)", $r_empty['status'] === 200 && strpos($r_empty['body'], 'لا يمكن حفظ فاتورة فارغة') !== FALSE);

    // 2. Missing customer rejected
    $token = $s1_client->get_csrf_token('index.php/sales/invoices/create');
    $r_no_cust = $s1_client->request('index.php/sales/invoices/create', 'POST', array(
        'csrf_erp_token'  => $token,
        'idempotency_key' => bin2hex(random_bytes(16)),
        'customer_id'     => '',
        'warehouse_id'    => $wh_assigned->id,
        'invoice_date'    => date('Y-m-d'),
        'product_id'      => array($prod_id),
        'quantity'        => array(2),
    ));
    assert_test("Missing customer rejected (renders form with error)", $r_no_cust['status'] === 200 && strpos($r_no_cust['body'], 'اختيار العميل') !== FALSE);

    // 3. Sales user trying to create invoice in unassigned warehouse
    $token = $s1_client->get_csrf_token('index.php/sales/invoices/create');
    $r_unauth_wh = $s1_client->request('index.php/sales/invoices/create', 'POST', array(
        'csrf_erp_token'  => $token,
        'idempotency_key' => bin2hex(random_bytes(16)),
        'customer_id'     => $customer_id,
        'warehouse_id'    => $wh_unassigned->id,
        'invoice_date'    => date('Y-m-d'),
        'product_id'      => array($prod_id),
        'quantity'        => array(1),
    ));
    assert_test("Sales user creating invoice in unassigned warehouse rejected", $r_unauth_wh['status'] === 200 && strpos($r_unauth_wh['body'], 'غير مصرح لك بالبيع من هذا المستودع') !== FALSE);

    // 4. Exceeding available stock rejected (request 999 when stock is 50)
    $token = $s1_client->get_csrf_token('index.php/sales/invoices/create');
    $r_exceed = $s1_client->request('index.php/sales/invoices/create', 'POST', array(
        'csrf_erp_token'  => $token,
        'idempotency_key' => bin2hex(random_bytes(16)),
        'customer_id'     => $customer_id,
        'warehouse_id'    => $wh_assigned->id,
        'invoice_date'    => date('Y-m-d'),
        'product_id'      => array($prod_id),
        'quantity'        => array(999),
    ));
    assert_test("Exceeding available stock rejected by server", $r_exceed['status'] === 200 && strpos($r_exceed['body'], 'غير كاف') !== FALSE);

    // 5. Zero or negative quantity rejected
    $token = $s1_client->get_csrf_token('index.php/sales/invoices/create');
    $r_zero_qty = $s1_client->request('index.php/sales/invoices/create', 'POST', array(
        'csrf_erp_token'  => $token,
        'idempotency_key' => bin2hex(random_bytes(16)),
        'customer_id'     => $customer_id,
        'warehouse_id'    => $wh_assigned->id,
        'invoice_date'    => date('Y-m-d'),
        'product_id'      => array($prod_id),
        'quantity'        => array(0),
    ));
    assert_test("Zero quantity rejected by server", $r_zero_qty['status'] === 200);

    echo "\n";

    // =========================================================================
    // SECTION 7: VALID INVOICE CREATION & ACID DEDUCTION
    // =========================================================================
    echo "--- SECTION 7: VALID INVOICE CREATION & ACID INTEGRITY ---\n";

    $token = $s1_client->get_csrf_token('index.php/sales/invoices/create');
    $idem_key = bin2hex(random_bytes(16));
    $qty_to_buy = 5;
    $discount_pct = 10.00;

    $post_data = array(
        'csrf_erp_token'      => $token,
        'idempotency_key'     => $idem_key,
        'customer_id'         => $customer_id,
        'warehouse_id'        => $wh_assigned->id,
        'invoice_date'        => date('Y-m-d'),
        'discount_percentage' => $discount_pct,
        'notes'               => 'فاتورة اختبار تجريبية للمرحلة الثالثة',
        'items'               => array(
            array('product_id' => $prod_id, 'quantity' => $qty_to_buy)
        ),
    );

    $r_create_ok = $s1_client->request('index.php/sales/invoices/create', 'POST', $post_data);
    assert_test("Valid invoice POST redirects to view page", is_redirect_status($r_create_ok['status']) && strpos($r_create_ok['location'], 'sales/invoices/view/') !== FALSE);

    // Extract created invoice ID from redirect location
    preg_match('/sales\/invoices\/view\/(\d+)/', $r_create_ok['location'], $loc_m);
    $created_invoice_id = isset($loc_m[1]) ? (int)$loc_m[1] : 0;
    assert_test("Created invoice ID is positive integer ({$created_invoice_id})", $created_invoice_id > 0);

    // Verify invoice row in DB
    $inv_row = $db->get_where('sales_invoices', array('id' => $created_invoice_id))->row();
    assert_test("Invoice exists in database with status active", $inv_row && $inv_row->status === 'active');
    assert_test("Invoice daily number format matches INV-YYYYMMDD-XXXX", preg_match('/^INV-\d{8}-\d{4}$/', $inv_row->invoice_number) === 1);
    assert_test("Invoice created_by is sales1 user ID", (int)$inv_row->created_by === $sales1_id);
    assert_test("Invoice currency is SAR", $inv_row->currency_code === 'SAR');

    // Financial math checks
    $expected_subtotal = round($qty_to_buy * $prod_price, 2);
    $expected_discount = round($expected_subtotal * ($discount_pct / 100), 2);
    $expected_total    = round($expected_subtotal - $expected_discount, 2);

    assert_test("Invoice subtotal is exact ({$expected_subtotal})", (float)$inv_row->subtotal === (float)$expected_subtotal);
    assert_test("Invoice discount amount is exact ({$expected_discount})", (float)$inv_row->discount_amount === (float)$expected_discount);
    assert_test("Invoice total amount is exact ({$expected_total})", (float)$inv_row->total_amount === (float)$expected_total);

    // Verify line item row
    $item_rows = $db->get_where('sales_invoice_items', array('invoice_id' => $created_invoice_id))->result();
    assert_test("Invoice line items count is exactly 1", count($item_rows) === 1);
    assert_test("Line item quantity is 5", (int)$item_rows[0]->quantity === 5);
    assert_test("Line item unit price matches product sale price", (float)$item_rows[0]->unit_price === (float)$prod_price);

    // Verify stock deduction: was 50, now must be 45
    $after_stock = $db->where('warehouse_id', $wh_assigned->id)->where('product_id', $prod_id)->get('warehouse_stock')->row();
    assert_test("Stock is decremented from 50 to 45", (int)$after_stock->quantity === 45);

    // Verify stock movement
    $movement = $db->where('sales_invoice_id', $created_invoice_id)->get('stock_movements')->row();
    assert_test("Stock movement created with movement_type 'sale'", $movement && $movement->movement_type === 'sale');
    assert_test("Stock movement quantity_delta is -5", (int)$movement->quantity_delta === -5);
    assert_test("Stock movement before_quantity=50 and after_quantity=45", (int)$movement->before_quantity === 50 && (int)$movement->after_quantity === 45);

    // Idempotent replay test: re-submitting with identical idempotency_key
    $token_replay = $s1_client->get_csrf_token('index.php/sales/invoices/create');
    $post_data['csrf_erp_token'] = $token_replay;
    $r_replay = $s1_client->request('index.php/sales/invoices/create', 'POST', $post_data);
    assert_test("Replay POST with same idempotency_key redirects to same invoice", is_redirect_status($r_replay['status']) && strpos($r_replay['location'], 'sales/invoices/view/' . $created_invoice_id) !== FALSE);

    // Stock should STILL be 45, not decremented again
    $after_stock_replay = $db->where('warehouse_id', $wh_assigned->id)->where('product_id', $prod_id)->get('warehouse_stock')->row();
    assert_test("Stock not double-decremented on replay (remains 45)", (int)$after_stock_replay->quantity === 45);

    echo "\n";

    // =========================================================================
    // SECTION 8: DEDICATED PRINT VIEW & SCOPING
    // =========================================================================
    echo "--- SECTION 8: DEDICATED PRINT VIEW & SCOPING ---\n";

    // 1. Sales user 1 prints own invoice -> 200 OK
    $r_print_s1 = $s1_client->request('index.php/sales/invoices/print/' . $created_invoice_id);
    assert_test("Sales user 1 can print own invoice (200 OK)", $r_print_s1['status'] === 200);
    assert_test("Print view contains invoice number", strpos($r_print_s1['body'], $inv_row->invoice_number) !== FALSE);
    assert_test("Print view contains customer name", strpos($r_print_s1['body'], 'شركة الأفق للاختبار F07') !== FALSE);
    assert_test("Print view contains warehouse name", strpos($r_print_s1['body'], $wh_assigned->name) !== FALSE);
    assert_test("Print view contains @media print stylesheet", strpos($r_print_s1['body'], '@media print') !== FALSE);
    assert_test("Print view contains no-print toolbar button", strpos($r_print_s1['body'], 'no-print') !== FALSE);
    assert_test("Print view does NOT contain main sidebar layout", strpos($r_print_s1['body'], 'class="sidebar"') === FALSE);

    // 2. Admin prints any invoice -> 200 OK
    $r_print_adm = $admin_client->request('index.php/sales/invoices/print/' . $created_invoice_id);
    assert_test("Admin can print any invoice (200 OK)", $r_print_adm['status'] === 200);

    // 3. Sales user 2 (different seller) attempts to print Sales user 1's invoice -> Blocked / Redirected
    $s2_client = new HttpTestClient($base_url);
    $login_s2 = $s2_client->login($sales2_username, 'TestPass@123');
    assert_test("Sales user 2 logs in successfully", $login_s2);

    $r_print_s2 = $s2_client->request('index.php/sales/invoices/print/' . $created_invoice_id);
    assert_test("Sales user 2 blocked from printing Sales user 1 invoice (redirects with error)", is_redirect_status($r_print_s2['status']));

    // 4. Printing non-existent invoice -> Redirects with error
    $r_print_404 = $admin_client->request('index.php/sales/invoices/print/9999999');
    assert_test("Printing non-existent invoice redirects with error", is_redirect_status($r_print_404['status']));

    echo "\n";

} finally {
    // =========================================================================
    // SECTION 9: CLEAN TEARDOWN & IMMUTABILITY CHECK
    // =========================================================================
    echo "--- SECTION 9: CLEAN TEARDOWN & RECOVERY ---\n";

    // Delete created invoices and items
    if (isset($created_invoice_id) && $created_invoice_id > 0) {
        $db->where('sales_invoice_id', $created_invoice_id)->delete('stock_movements');
        $db->where('invoice_id', $created_invoice_id)->delete('sales_invoice_items');
        $db->where('id', $created_invoice_id)->delete('sales_invoices');
    }

    // Clean up any remaining test invoice sequences
    $today_str = date('Ymd');
    $db->where('sequence_date', $today_str)->delete('invoice_sequences');

    // Restore stock quantity
    if (isset($wh_assigned) && isset($prod_id)) {
        if ($stock_row) {
            $db->where('id', $stock_row->id)->update('warehouse_stock', array('quantity' => $original_stock_qty));
        } else {
            $db->where('warehouse_id', $wh_assigned->id)->where('product_id', $prod_id)->delete('warehouse_stock');
        }
    }

    // Delete customer
    if (isset($customer_id) && $customer_id > 0) {
        $db->where('id', $customer_id)->delete('customers');
    }

    // Delete user warehouse assignments
    if (isset($sales1_id)) {
        $db->where('user_id', $sales1_id)->delete('user_warehouses');
    }
    if (isset($sales2_id)) {
        $db->where('user_id', $sales2_id)->delete('user_warehouses');
    }

    // Delete temporary users
    if (isset($wh_user_id)) {
        $db->where('id', $wh_user_id)->delete('users');
    }
    if (isset($sales1_id)) {
        $db->where('id', $sales1_id)->delete('users');
    }
    if (isset($sales2_id)) {
        $db->where('id', $sales2_id)->delete('users');
    }

    // Final Counts Verification
    $final_stock_count     = $db->count_all('warehouse_stock');
    $final_movements_count = $db->count_all('stock_movements');
    $final_invoices_count  = $db->count_all('sales_invoices');
    $final_items_count     = $db->count_all('sales_invoice_items');
    $final_customers_count = $db->count_all('customers');
    $final_users_count     = $db->count_all('users');

    echo "Final DB Counts: Stock={$final_stock_count}, Movements={$final_movements_count}, Invoices={$final_invoices_count}, Items={$final_items_count}, Customers={$final_customers_count}, Users={$final_users_count}\n";

    assert_test("Database stock count restored exactly", $final_stock_count === $initial_stock_count);
    assert_test("Database movements count restored exactly", $final_movements_count === $initial_movements_count);
    assert_test("Database invoices count restored exactly", $final_invoices_count === $initial_invoices_count);
    assert_test("Database items count restored exactly", $final_items_count === $initial_items_count);
    assert_test("Database customers count restored exactly", $final_customers_count === $initial_customers_count);
    assert_test("Database users count restored exactly", $final_users_count === $initial_users_count);
}

echo "\n====================================================================\n";
echo " TEST SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
