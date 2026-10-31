<?php
/**
 * Mini ERP Stock & Sales — F07 Explicit Customer Status Operations Test Suite
 *
 * Verifies:
 * 1. Model-level explicit activation & deactivation (Idempotent: activate(), deactivate(), set_status())
 * 2. HTTP Controller-level deactivation of active customer (POST customers/deactivate/$id)
 * 3. Idempotent repetition: sending multiple deactivation requests keeps customer deactivated (never toggles to active)
 * 4. HTTP Controller-level activation of deactivated customer (POST customers/activate/$id)
 * 5. Idempotent repetition: sending multiple activation requests keeps customer active (never toggles to inactive)
 * 6. Method rejection: GET requests to activate/deactivate are rejected (HTTP 404)
 * 7. CSRF rejection: requests without valid CSRF token are blocked
 * 8. Role authorization: unauthenticated and Warehouse users are blocked
 * 9. Backwards compatibility: toggle_status endpoint remains functional and safe
 * 10. Clean database teardown
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('User_model');
$CI->load->model('Customer_model');

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
echo " Mini ERP Stock & Sales — F07 Explicit Customer Status Verification\n";
echo "====================================================================\n\n";

$initial_stock_count     = $db->count_all('warehouse_stock');
$initial_movements_count = $db->count_all('stock_movements');
$initial_invoices_count  = $db->count_all('sales_invoices');
$initial_customers_count = $db->count_all('customers');
$initial_users_count     = $db->count_all('users');

echo "Initial DB Counts: Stock={$initial_stock_count}, Movements={$initial_movements_count}, Invoices={$initial_invoices_count}, Customers={$initial_customers_count}, Users={$initial_users_count}\n\n";

// HTTP Test Client Helper
class HttpTestClient {
    private $cookie_file;
    private $base_url;

    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'ci_f07_cust_');
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
    // -------------------------------------------------------------------------
    // SECTION 1: MODEL-LEVEL EXPLICIT STATUS OPERATIONS
    // -------------------------------------------------------------------------
    echo "--- SECTION 1: MODEL-LEVEL EXPLICIT STATUS TESTS ---\n";

    // Create test customer
    $c_res = $CI->Customer_model->create(array(
        'name'    => 'عميل اختبار العمليات الصريحة',
        'phone'   => '0550011223',
        'email'   => 'explicit_test@example.local',
        'address' => 'الرياض'
    ));
    assert_test("Create test customer via model", $c_res['success'] === TRUE && $c_res['customer_id'] > 0);
    $cid = $c_res['customer_id'];

    // Verify initial is_active = 1
    $c_init = $CI->Customer_model->get_by_id($cid);
    assert_test("Initial customer status is active (is_active = 1)", (int)$c_init->is_active === 1);

    // Explicit deactivate
    $deact1 = $CI->Customer_model->deactivate($cid);
    assert_test("Model deactivate() returns success and new_status=0", $deact1['success'] === TRUE && $deact1['new_status'] === 0);
    $c_deact = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer is_active is now 0", (int)$c_deact->is_active === 0);

    // Repeat deactivate (Idempotency test)
    $deact2 = $CI->Customer_model->deactivate($cid);
    assert_test("Repeated deactivate() returns success", $deact2['success'] === TRUE && $deact2['new_status'] === 0);
    $deact3 = $CI->Customer_model->deactivate($cid);
    $c_deact_repeat = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer REMAINS deactivated after 3 deactivate calls (is_active = 0)", (int)$c_deact_repeat->is_active === 0);

    // Explicit activate
    $act1 = $CI->Customer_model->activate($cid);
    assert_test("Model activate() returns success and new_status=1", $act1['success'] === TRUE && $act1['new_status'] === 1);
    $c_act = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer is_active is now 1", (int)$c_act->is_active === 1);

    // Repeat activate (Idempotency test)
    $act2 = $CI->Customer_model->activate($cid);
    assert_test("Repeated activate() returns success", $act2['success'] === TRUE && $act2['new_status'] === 1);
    $act3 = $CI->Customer_model->activate($cid);
    $c_act_repeat = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer REMAINS active after 3 activate calls (is_active = 1)", (int)$c_act_repeat->is_active === 1);

    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 2: HTTP CONTROLLER-LEVEL EXPLICIT OPERATIONS & IDEMPOTENCY
    // -------------------------------------------------------------------------
    echo "--- SECTION 2: HTTP CONTROLLER EXPLICIT OPERATIONS ---\n";

    $admin_client = new HttpTestClient($base_url);
    $login_adm = $admin_client->login('admin', 'Admin@123456');
    assert_test("Admin logs in successfully", $login_adm);

    // 2.1 First HTTP Deactivation request
    $token1 = $admin_client->get_csrf_token('index.php/customers');
    $r_deact1 = $admin_client->request('index.php/customers/deactivate/' . $cid, 'POST', array(
        'csrf_erp_token' => $token1
    ));
    assert_test("Admin POST customers/deactivate/{$cid} redirects with success", is_redirect_status($r_deact1['status']));
    $c_http_deact1 = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer is_active is now 0 after HTTP deactivate", (int)$c_http_deact1->is_active === 0);

    // 2.2 REPEATED HTTP Deactivation request (Simulating network retry / double-click)
    $token2 = $admin_client->get_csrf_token('index.php/customers');
    $r_deact2 = $admin_client->request('index.php/customers/deactivate/' . $cid, 'POST', array(
        'csrf_erp_token' => $token2
    ));
    assert_test("Repeated POST customers/deactivate/{$cid} redirects successfully", is_redirect_status($r_deact2['status']));
    $c_http_deact2 = $CI->Customer_model->get_by_id($cid);
    assert_test("CRITICAL IDEMPOTENCY: Customer is STILL deactivated (is_active = 0, NOT toggled to active)", (int)$c_http_deact2->is_active === 0);

    // 2.3 Third HTTP Deactivation request
    $token3 = $admin_client->get_csrf_token('index.php/customers');
    $r_deact3 = $admin_client->request('index.php/customers/deactivate/' . $cid, 'POST', array(
        'csrf_erp_token' => $token3
    ));
    $c_http_deact3 = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer strictly remains is_active = 0 across 3 consecutive HTTP deactivations", (int)$c_http_deact3->is_active === 0);

    // 2.4 First HTTP Activation request
    $token4 = $admin_client->get_csrf_token('index.php/customers');
    $r_act1 = $admin_client->request('index.php/customers/activate/' . $cid, 'POST', array(
        'csrf_erp_token' => $token4
    ));
    assert_test("Admin POST customers/activate/{$cid} redirects with success", is_redirect_status($r_act1['status']));
    $c_http_act1 = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer is_active is now 1 after HTTP activate", (int)$c_http_act1->is_active === 1);

    // 2.5 REPEATED HTTP Activation request (Simulating network retry / double-click)
    $token5 = $admin_client->get_csrf_token('index.php/customers');
    $r_act2 = $admin_client->request('index.php/customers/activate/' . $cid, 'POST', array(
        'csrf_erp_token' => $token5
    ));
    assert_test("Repeated POST customers/activate/{$cid} redirects successfully", is_redirect_status($r_act2['status']));
    $c_http_act2 = $CI->Customer_model->get_by_id($cid);
    assert_test("CRITICAL IDEMPOTENCY: Customer is STILL active (is_active = 1, NOT toggled to inactive)", (int)$c_http_act2->is_active === 1);

    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 3: HTTP METHOD RESTRICTIONS & CSRF REJECTION
    // -------------------------------------------------------------------------
    echo "--- SECTION 3: METHOD REJECTION & CSRF CHECKS ---\n";

    // GET request to activate -> 404
    $r_get_act = $admin_client->request('index.php/customers/activate/' . $cid, 'GET');
    assert_test("GET customers/activate/{$cid} rejected with HTTP 404", $r_get_act['status'] === 404);

    // GET request to deactivate -> 404
    $r_get_deact = $admin_client->request('index.php/customers/deactivate/' . $cid, 'GET');
    assert_test("GET customers/deactivate/{$cid} rejected with HTTP 404", $r_get_deact['status'] === 404);

    // POST request with invalid CSRF token -> 403 Forbidden
    $r_csrf_bad = $admin_client->request('index.php/customers/activate/' . $cid, 'POST', array(
        'csrf_erp_token' => 'invalid_csrf_token_00000000000'
    ));
    assert_test("POST customers/activate with invalid CSRF token rejected (HTTP 403)", $r_csrf_bad['status'] === 403);

    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 4: ROLE AUTHORIZATION CHECKS
    // -------------------------------------------------------------------------
    echo "--- SECTION 4: ROLE AUTHORIZATION CHECKS ---\n";

    // Unauthenticated user
    $guest = new HttpTestClient($base_url);
    $g_token = $guest->get_csrf_token('index.php/login');
    $r_guest_act = $guest->request('index.php/customers/activate/' . $cid, 'POST', array(
        'csrf_erp_token' => $g_token
    ));
    assert_test("Unauthenticated user POST customers/activate redirects to login", is_redirect_status($r_guest_act['status']) && strpos($r_guest_act['location'], 'login') !== FALSE);

    // Warehouse user (role_id = 2)
    $hashed_pw = password_hash('Pass@123456', PASSWORD_BCRYPT);
    $db->insert('users', array(
        'username'      => 'temp_wh_cust_' . substr(bin2hex(random_bytes(3)), 0, 5),
        'full_name'     => 'أمين مستودع فحص عملاء',
        'email'         => 'temp_wh_' . bin2hex(random_bytes(3)) . '@test.local',
        'password_hash' => $hashed_pw,
        'role_id'       => 2, // warehouse
        'is_active'     => 1,
        'created_at'    => date('Y-m-d H:i:s'),
    ));
    $wh_uid = (int)$db->insert_id();
    $wh_uname = $db->get_where('users', array('id' => $wh_uid))->row()->username;

    $wh_client = new HttpTestClient($base_url);
    $wh_login = $wh_client->login($wh_uname, 'Pass@123456');
    assert_test("Warehouse user logs in", $wh_login);

    $wh_token = $wh_client->get_csrf_token('index.php/customers');
    $r_wh_deact = $wh_client->request('index.php/customers/deactivate/' . $cid, 'POST', array(
        'csrf_erp_token' => $wh_token
    ));
    assert_test("Warehouse user POST customers/deactivate is BLOCKED (redirect to dashboard)", is_redirect_status($r_wh_deact['status']) && strpos($r_wh_deact['location'], 'dashboard') !== FALSE);

    // Sales user (role_id = 3) CAN activate/deactivate customers in F07
    $db->insert('users', array(
        'username'      => 'temp_s_cust_' . substr(bin2hex(random_bytes(3)), 0, 5),
        'full_name'     => 'بائع فحص عملاء',
        'email'         => 'temp_s_' . bin2hex(random_bytes(3)) . '@test.local',
        'password_hash' => $hashed_pw,
        'role_id'       => 3, // sales
        'is_active'     => 1,
        'created_at'    => date('Y-m-d H:i:s'),
    ));
    $sales_uid = (int)$db->insert_id();
    $sales_uname = $db->get_where('users', array('id' => $sales_uid))->row()->username;

    $sales_client = new HttpTestClient($base_url);
    $sales_login = $sales_client->login($sales_uname, 'Pass@123456');
    assert_test("Sales user logs in", $sales_login);

    $sales_token = $sales_client->get_csrf_token('index.php/customers');
    $r_sales_deact = $sales_client->request('index.php/customers/deactivate/' . $cid, 'POST', array(
        'csrf_erp_token' => $sales_token
    ));
    assert_test("Sales user can deactivate customer (F07 customer management access)", is_redirect_status($r_sales_deact['status']));
    $c_sales_deact = $CI->Customer_model->get_by_id($cid);
    assert_test("Customer deactivated by Sales user", (int)$c_sales_deact->is_active === 0);

    echo "\n";

    // -------------------------------------------------------------------------
    // SECTION 5: BACKWARDS COMPATIBILITY WRAPPER (toggle_status)
    // -------------------------------------------------------------------------
    echo "--- SECTION 5: COMPATIBILITY WRAPPER (toggle_status) ---\n";

    // Currently customer is deactivated (is_active = 0)
    $token_compat1 = $admin_client->get_csrf_token('index.php/customers');
    $r_compat1 = $admin_client->request('index.php/customers/toggle_status/' . $cid, 'POST', array(
        'csrf_erp_token' => $token_compat1
    ));
    assert_test("POST customers/toggle_status redirects successfully", is_redirect_status($r_compat1['status']));
    $c_compat1 = $CI->Customer_model->get_by_id($cid);
    assert_test("toggle_status activated inactive customer (is_active = 1)", (int)$c_compat1->is_active === 1);

    // Call toggle_status again -> deactivates
    $token_compat2 = $admin_client->get_csrf_token('index.php/customers');
    $r_compat2 = $admin_client->request('index.php/customers/toggle_status/' . $cid, 'POST', array(
        'csrf_erp_token' => $token_compat2
    ));
    $c_compat2 = $CI->Customer_model->get_by_id($cid);
    assert_test("toggle_status deactivated active customer (is_active = 0)", (int)$c_compat2->is_active === 0);

    echo "\n";

} finally {
    // -------------------------------------------------------------------------
    // SECTION 6: CLEAN TEARDOWN & RECOVERY
    // -------------------------------------------------------------------------
    echo "--- SECTION 6: CLEAN TEARDOWN & RECOVERY ---\n";

    if (isset($cid) && $cid > 0) {
        $db->where('id', $cid)->delete('customers');
    }
    if (isset($wh_uid)) {
        $db->where('id', $wh_uid)->delete('users');
    }
    if (isset($sales_uid)) {
        $db->where('id', $sales_uid)->delete('users');
    }

    $final_stock_count     = $db->count_all('warehouse_stock');
    $final_movements_count = $db->count_all('stock_movements');
    $final_invoices_count  = $db->count_all('sales_invoices');
    $final_customers_count = $db->count_all('customers');
    $final_users_count     = $db->count_all('users');

    echo "Final DB Counts: Stock={$final_stock_count}, Movements={$final_movements_count}, Invoices={$final_invoices_count}, Customers={$final_customers_count}, Users={$final_users_count}\n";

    assert_test("Database stock count restored exactly", $final_stock_count === $initial_stock_count);
    assert_test("Database movements count restored exactly", $final_movements_count === $initial_movements_count);
    assert_test("Database invoices count restored exactly", $final_invoices_count === $initial_invoices_count);
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

