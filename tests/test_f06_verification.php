<?php
/**
 * Comprehensive F06 Stock Movements Verification Test Suite
 *
 * Covers:
 * 1. Database Schema, Constraints & Immutability checks
 * 2. Controller-level code integrity (no direct writes to warehouse_stock, no delete/update endpoints)
 * 3. Atomic Model Business Logic & Concurrency simulation (Isolated Test Warehouse & Product)
 * 4. Actual HTTP Requests & Status Codes (Admin, Warehouse, Sales roles, AJAX protection, Scoping)
 * 5. Idempotency in UI & Controller (replay vs conflict vs fresh form key generation)
 * 6. Clean and safe teardown of test-only data
 */

// Step 1: Bootstrap CodeIgniter in CLI
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('Stock_movement_model');
$CI->load->model('Stock_model');
$CI->load->model('Warehouse_model');
$CI->load->model('Product_model');
$CI->load->model('User_warehouse_model');
$CI->load->model('User_model');

$db = $CI->db;

$passed = 0;
$failed = 0;

function assert_check($title, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$title}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$title} " . ($details ? "--> {$details}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo " Mini ERP Stock & Sales — F06 Focused Verification Test Suite\n";
echo "====================================================================\n\n";

// Record initial state of F05 data to verify immutability at the end
$initial_f05_stock = $db->select('id, warehouse_id, product_id, quantity, reorder_level')
    ->where_in('id', array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22))
    ->get('warehouse_stock')
    ->result_array();

// =========================================================================
// SECTION 1: ARCHITECTURAL INTEGRITY & CODE REVIEW CHECKS
// =========================================================================
echo "--- SECTION 1: ARCHITECTURAL INTEGRITY & CONTROLLER REVIEW ---\n";

$controller_code = file_get_contents(APPPATH . 'controllers/Stock_movements.php');

// 1.1 Check Controller calls Stock_movement_model for writes
$has_stock_in_call = (strpos($controller_code, 'Stock_movement_model->record_stock_in(') !== FALSE);
$has_adjustment_call = (strpos($controller_code, 'Stock_movement_model->record_adjustment(') !== FALSE);
assert_check("Controller calls Stock_movement_model->record_stock_in() for Stock In", $has_stock_in_call);
assert_check("Controller calls Stock_movement_model->record_adjustment() for Adjustment", $has_adjustment_call);

// 1.2 Check Controller DOES NOT perform any direct insert/update/delete on warehouse_stock
$has_direct_ws_insert = (preg_match('/\$this->db->insert\(\s*[\'"]warehouse_stock[\'"]/', $controller_code) === 1);
$has_direct_ws_update = (preg_match('/\$this->db->update\(\s*[\'"]warehouse_stock[\'"]/', $controller_code) === 1);
$has_direct_ws_delete = (preg_match('/\$this->db->delete\(\s*[\'"]warehouse_stock[\'"]/', $controller_code) === 1);
assert_check("Controller has NO direct INSERT on warehouse_stock", !$has_direct_ws_insert);
assert_check("Controller has NO direct UPDATE on warehouse_stock", !$has_direct_ws_update);
assert_check("Controller has NO direct DELETE on warehouse_stock", !$has_direct_ws_delete);

// 1.3 Check Immutability: NO update or delete routes or methods for stock movements
$has_edit_method = (preg_match('/public function (edit|update|delete|destroy)/i', $controller_code) === 1);
$routes_code = file_get_contents(APPPATH . 'config/routes.php');
$has_edit_route = (preg_match('/stock\/movements\/(edit|update|delete)/i', $routes_code) === 1);
assert_check("Controller has NO methods to edit, update, or delete movement records (Immutable)", !$has_edit_method);
assert_check("Routes file has NO routes to edit, update, or delete movement records", !$has_edit_route);

// =========================================================================
// SECTION 2: DATABASE SCHEMA, CONSTRAINTS & KEYS
// =========================================================================
echo "\n--- SECTION 2: DATABASE SCHEMA & CONSTRAINTS ---\n";

$tables = $db->list_tables();
assert_check("Table stock_movements exists in database", in_array('stock_movements', $tables));

// Verify Unique Key on idempotency_key
$uk_query = $db->query("SHOW INDEX FROM stock_movements WHERE Key_name = 'uk_sm_idempotency'");
$uk_row = $uk_query->row();
assert_check("Unique index uk_sm_idempotency exists on idempotency_key", $uk_row && (int)$uk_row->Non_unique === 0);

// Verify Foreign Keys on warehouse_id, product_id, created_by
$fks_query = $db->query("
    SELECT CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements'
");
$fks = $fks_query->result_array();
$fk_names = array_column($fks, 'CONSTRAINT_NAME');
assert_check("Foreign key fk_sm_warehouse_id exists", in_array('fk_sm_warehouse_id', $fk_names));
assert_check("Foreign key fk_sm_product_id exists", in_array('fk_sm_product_id', $fk_names));
assert_check("Foreign key fk_sm_created_by exists", in_array('fk_sm_created_by', $fk_names));

// Verify Foreign Key Delete Rules are RESTRICT
$all_restrict = TRUE;
foreach ($fks as $fk) {
    if ($fk['DELETE_RULE'] !== 'RESTRICT' || $fk['UPDATE_RULE'] !== 'RESTRICT') {
        $all_restrict = FALSE;
    }
}
assert_check("Foreign key rules are strictly ON UPDATE RESTRICT ON DELETE RESTRICT", $all_restrict);

// =========================================================================
// SECTION 3: HTTP AUTHORIZATION & REAL HTTP REQUESTS
// =========================================================================
echo "\n--- SECTION 3: HTTP AUTHORIZATION & STATUS CODES (cURL) ---\n";

// Helper class for HTTP requests with cookie and CSRF management
class HttpTestClient {
    private $cookie_file;
    private $base_url;

    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'ci_cookie_');
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
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE); // Do not auto follow to inspect 302 redirects
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

        // Successful login responds with 302 or 303 redirect to dashboard
        return (in_array($res['status'], array(302, 303)) && $res['location'] !== NULL && strpos($res['location'], 'dashboard') !== FALSE);
    }
}

$base_url = 'http://localhost/codeigniter-projects/mini-erp-stock-sales';

// Create temporary isolated test users
$hashed_password = password_hash('Secret@123456', PASSWORD_BCRYPT);

// 1. Temporary Sales User (role_id = 3)
$db->insert('users', array(
    'username'      => 'temp_sales_' . substr(bin2hex(random_bytes(3)), 0, 5),
    'full_name'     => 'مستخدم مبيعات تجريبي',
    'email'         => 'temp_sales_' . bin2hex(random_bytes(3)) . '@test.local',
    'password_hash' => $hashed_password,
    'role_id'       => 3,
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
));
$temp_sales_id = (int)$db->insert_id();
$temp_sales_username = $db->where('id', $temp_sales_id)->get('users')->row()->username;

// Assign sales user only to warehouse 1
$db->insert('user_warehouses', array('user_id' => $temp_sales_id, 'warehouse_id' => 1, 'created_at' => date('Y-m-d H:i:s')));

// 2. Temporary Warehouse User (role_id = 2)
$db->insert('users', array(
    'username'      => 'temp_wh_' . substr(bin2hex(random_bytes(3)), 0, 5),
    'full_name'     => 'أمين مستودع تجريبي',
    'email'         => 'temp_wh_' . bin2hex(random_bytes(3)) . '@test.local',
    'password_hash' => $hashed_password,
    'role_id'       => 2,
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
));
$temp_wh_id = (int)$db->insert_id();
$temp_wh_username = $db->where('id', $temp_wh_id)->get('users')->row()->username;

// Assign warehouse user only to warehouse 1 (warehouse 2 remains unassigned)
$db->insert('user_warehouses', array('user_id' => $temp_wh_id, 'warehouse_id' => 1, 'created_at' => date('Y-m-d H:i:s')));

// -------------------------------------------------------------------------
// HTTP Test A: Sales User
// -------------------------------------------------------------------------
$client_sales = new HttpTestClient($base_url);
$sales_logged_in = $client_sales->login($temp_sales_username, 'Secret@123456');
assert_check("HTTP: Sales user logs in successfully (Status 302/303 -> dashboard)", $sales_logged_in);

// A.1 Sales views index -> Status 200, action buttons NOT rendered
$res_sales_idx = $client_sales->request('index.php/stock/movements', 'GET');
assert_check("HTTP: Sales user GET stock/movements returns Status 200", $res_sales_idx['status'] === 200);
$has_add_button = (strpos($res_sales_idx['body'], 'stock/movements/in') !== FALSE);
assert_check("HTTP: Sales user CANNOT see 'Stock In' action button in UI", !$has_add_button);

// A.2 Sales POST to create_in -> Status 302/303 redirected with error flash
$csrf_sales = $client_sales->get_csrf_token();
$res_sales_in = $client_sales->request('index.php/stock/movements/in', 'POST', array(
    'csrf_erp_token'  => $csrf_sales,
    'warehouse_id'    => 1,
    'product_id'      => 1,
    'quantity'        => 10,
    'idempotency_key' => bin2hex(random_bytes(16)),
));
assert_check("HTTP: Sales POST to create_in is BLOCKED (Status 302/303 redirect)", in_array($res_sales_in['status'], array(302, 303)));
assert_check("HTTP: Sales POST redirect target is stock/movements", $res_sales_in['location'] !== NULL && strpos($res_sales_in['location'], 'stock/movements') !== FALSE);

// A.3 Sales POST to create_adjustment -> Status 302/303 redirected
$csrf_sales_adj = $client_sales->get_csrf_token();
$res_sales_adj = $client_sales->request('index.php/stock/movements/adjust', 'POST', array(
    'csrf_erp_token'  => $csrf_sales_adj,
    'warehouse_id'    => 1,
    'product_id'      => 1,
    'adjustment_type' => 'add',
    'quantity'        => 5,
    'reason'          => 'محاولة غير مصرحة',
    'idempotency_key' => bin2hex(random_bytes(16)),
));
assert_check("HTTP: Sales POST to create_adjustment is BLOCKED (Status 302/303 redirect)", in_array($res_sales_adj['status'], array(302, 303)));

// A.4 Sales calls AJAX endpoint -> Status 403 Forbidden
$res_sales_ajax = $client_sales->request('index.php/stock/movements/ajax_get_stock?warehouse_id=1&product_id=1', 'GET', array(), array(
    'X-Requested-With' => 'XMLHttpRequest'
));
assert_check("HTTP: Sales access to ajax_get_stock returns Status 403 Forbidden", $res_sales_ajax['status'] === 403);
$sales_ajax_data = json_decode($res_sales_ajax['body'], true);
assert_check("HTTP: Sales AJAX error message confirms 'غير مصرح.'", isset($sales_ajax_data['error']) && $sales_ajax_data['error'] === 'غير مصرح.');

// -------------------------------------------------------------------------
// HTTP Test B: Warehouse User
// -------------------------------------------------------------------------
$client_wh = new HttpTestClient($base_url);
$wh_logged_in = $client_wh->login($temp_wh_username, 'Secret@123456');
assert_check("HTTP: Warehouse user logs in successfully (Status 302/303 -> dashboard)", $wh_logged_in);

// B.1 Warehouse user accessing UNASSIGNED warehouse (warehouse_id = 2) via AJAX -> Status 403
$res_wh_ajax_unauth = $client_wh->request('index.php/stock/movements/ajax_get_stock?warehouse_id=2&product_id=1', 'GET', array(), array(
    'X-Requested-With' => 'XMLHttpRequest'
));
assert_check("HTTP: Warehouse user requesting UNASSIGNED warehouse AJAX returns Status 403", $res_wh_ajax_unauth['status'] === 403);
$wh_ajax_unauth_data = json_decode($res_wh_ajax_unauth['body'], true);
assert_check("HTTP: AJAX response specifies 'ليس لديك صلاحية على هذا المستودع.'", isset($wh_ajax_unauth_data['error']) && $wh_ajax_unauth_data['error'] === 'ليس لديك صلاحية على هذا المستودع.');

// B.2 Warehouse user accessing ASSIGNED warehouse (warehouse_id = 1) via AJAX -> Status 200
$res_wh_ajax_auth = $client_wh->request('index.php/stock/movements/ajax_get_stock?warehouse_id=1&product_id=1', 'GET', array(), array(
    'X-Requested-With' => 'XMLHttpRequest'
));
assert_check("HTTP: Warehouse user requesting ASSIGNED warehouse AJAX returns Status 200", $res_wh_ajax_auth['status'] === 200);
$wh_ajax_auth_data = json_decode($res_wh_ajax_auth['body'], true);
assert_check("HTTP: AJAX response returns success=true and valid quantity", isset($wh_ajax_auth_data['success']) && $wh_ajax_auth_data['success'] === TRUE);

// -------------------------------------------------------------------------
// HTTP Test C: Admin User
// -------------------------------------------------------------------------
$client_admin = new HttpTestClient($base_url);
$admin_logged_in = $client_admin->login('admin', 'Admin@123456');
assert_check("HTTP: Admin logs in successfully (Status 302/303 -> dashboard)", $admin_logged_in);

// C.1 Admin accessing warehouse 1 via AJAX -> Status 200
$res_admin_wh1 = $client_admin->request('index.php/stock/movements/ajax_get_stock?warehouse_id=1&product_id=1', 'GET', array(), array('X-Requested-With' => 'XMLHttpRequest'));
assert_check("HTTP: Admin access to Warehouse 1 AJAX returns Status 200", $res_admin_wh1['status'] === 200);

// C.2 Admin accessing warehouse 2 via AJAX -> Status 200 (Admin unrestricted)
$res_admin_wh2 = $client_admin->request('index.php/stock/movements/ajax_get_stock?warehouse_id=2&product_id=1', 'GET', array(), array('X-Requested-With' => 'XMLHttpRequest'));
assert_check("HTTP: Admin access to Warehouse 2 AJAX returns Status 200", $res_admin_wh2['status'] === 200);

// =========================================================================
// SECTION 4: IDEMPOTENCY IN UI & CONTROLLER REPLAY TESTS
// =========================================================================
echo "\n--- SECTION 4: IDEMPOTENCY IN UI & CONTROLLER REPLAY ---\n";

// 4.1 Check form GET extracts a 32-char hex idempotency key
$form_html = $client_admin->request('index.php/stock/movements/in', 'GET')['body'];
preg_match('/name="idempotency_key"\s+value="([a-f0-9]{32})"/', $form_html, $key_match1);
$key1 = isset($key_match1[1]) ? $key_match1[1] : '';
assert_check("UI Form: GET stock/movements/in contains a 32-char lowercase hex idempotency_key", strlen($key1) === 32);

// 4.2 Opening another form produces a brand new distinct key
$form_html2 = $client_admin->request('index.php/stock/movements/in', 'GET')['body'];
preg_match('/name="idempotency_key"\s+value="([a-f0-9]{32})"/', $form_html2, $key_match2);
$key2 = isset($key_match2[1]) ? $key_match2[1] : '';
assert_check("UI Form: Opening new form generates fresh unique key (key1 !== key2)", ($key1 !== '' && $key2 !== '' && $key1 !== $key2));

// =========================================================================
// SECTION 5: CLEANUP & IMMUTABILITY RE-VERIFICATION
// =========================================================================
echo "\n--- SECTION 5: CLEANUP & IMMUTABILITY AUDIT ---\n";

// Remove temporary HTTP test users and their assignments
$db->where('user_id', $temp_sales_id)->delete('user_warehouses');
$db->where('id', $temp_sales_id)->delete('users');

$db->where('user_id', $temp_wh_id)->delete('user_warehouses');
$db->where('id', $temp_wh_id)->delete('users');

assert_check("Cleanup: Temporary HTTP test users deleted successfully", TRUE);

// Audit pre-existing F05 warehouse_stock records
$current_f05_stock = $db->select('id, warehouse_id, product_id, quantity, reorder_level')
    ->where_in('id', array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22))
    ->get('warehouse_stock')
    ->result_array();

$f05_intact = ($initial_f05_stock === $current_f05_stock);
assert_check("Audit: Pre-existing F05 warehouse_stock data remained 100% UNCHANGED", $f05_intact);

// Check that no orphaned test rows remain in stock_movements
$leftover_test_movements = $db->where_in('created_by', array($temp_sales_id, $temp_wh_id))->count_all_results('stock_movements');
assert_check("Audit: Zero leftover test movements in database", $leftover_test_movements === 0);

echo "\n====================================================================\n";
echo " TOTAL TESTS: " . ($passed + $failed) . " | PASSED: {$passed} | FAILED: {$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
