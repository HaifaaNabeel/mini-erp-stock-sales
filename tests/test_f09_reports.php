<?php
/**
 * Mini ERP Stock & Sales — F09 Reports, Business Insights & CSV Export Verification Suite
 *
 * Automated integration tests covering:
 * 1. Model queries and aggregations for all 5 reports:
 *    - Stock Balances
 *    - Stock Movements
 *    - Sales Summary (Gross, Discounts, Net, Invoices Count)
 *    - Invoices Report (Active and Cancelled)
 *    - Low Stock Alert (quantity <= reorder_level)
 * 2. Financial accuracy & business rules:
 *    - Strict exclusion of cancelled invoices from sales summary totals
 *    - Sale reversals not counted as sales
 *    - Accurate shortage calculation: max(0, reorder_level - quantity)
 * 3. Filters & date boundaries (inclusive end-of-day)
 * 4. Role-based scoping (Admin vs Warehouse vs Sales)
 * 5. HTTP Controller endpoints & authorization enforcement
 * 6. CSV Export streaming, UTF-8 BOM, and CSV Formula Injection protection
 * 7. Clean database recovery (Zero collateral damage)
 *
 * PHP Compatibility: 7.4+
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('Report_model');
$CI->load->model('Warehouse_model');
$CI->load->model('Product_model');
$CI->load->model('Category_model');
$CI->load->model('Customer_model');
$CI->load->model('Stock_model');
$CI->load->model('Stock_movement_model');
$CI->load->model('Sales_invoice_model');
$CI->load->model('User_model');
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
    return in_array((int)$status, array(301, 302, 303, 307, 308), TRUE);
}

class HttpTestClient {
    private $cookie_file;
    private $base_url;

    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'ci_f09_cookie_');
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
}

function login_client($client, $username, $password = 'Pass@123') {
    $token = $client->get_csrf_token('index.php/login');
    $res = $client->request('index.php/login', 'POST', array(
        'csrf_erp_token' => $token,
        'username'       => $username,
        'password'       => $password,
    ));
    return is_redirect_status($res['status']);
}

echo "====================================================================\n";
echo " Mini ERP Stock & Sales — F09 Reports & CSV Export Verification Suite\n";
echo "====================================================================\n\n";

// Snapshot baseline counts
$initial_stock_count     = $db->count_all('warehouse_stock');
$initial_movements_count = $db->count_all('stock_movements');
$initial_invoices_count  = $db->count_all('sales_invoices');
$initial_items_count     = $db->count_all('sales_invoice_items');
$initial_customers_count = $db->count_all('customers');
$initial_users_count     = $db->count_all('users');
$initial_sequences_count = $db->count_all('invoice_sequences');

echo "Initial DB State: Stock={$initial_stock_count}, Movements={$initial_movements_count}, Invoices={$initial_invoices_count}, Items={$initial_items_count}, Customers={$initial_customers_count}, Users={$initial_users_count}\n\n";

// -------------------------------------------------------------------------
// SECTION 1: SETUP TEST FIXTURES
// -------------------------------------------------------------------------
echo "--- SECTION 1: SETUP TEST FIXTURES ---\n";

// 1.1 Category & Warehouses
$db->insert('categories', array('name' => 'تصنيف تقارير F09', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$test_cat_id = $db->insert_id();

$db->insert('warehouses', array('name' => 'مستودع تقارير أ', 'code' => 'WH-REP-A', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$wh_a_id = $db->insert_id();

$db->insert('warehouses', array('name' => 'مستودع تقارير ب', 'code' => 'WH-REP-B', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$wh_b_id = $db->insert_id();

// 1.2 Products: P1 (reorder=10), P2 (reorder=5), P3 (reorder=0)
$db->insert('products', array('name' => 'منتج تقارير 1', 'sku' => 'SKU-REP-P1', 'category_id' => $test_cat_id, 'sale_price' => 50.00, 'reorder_level' => 10, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$p1_id = $db->insert_id();

$db->insert('products', array('name' => 'منتج تقارير 2', 'sku' => 'SKU-REP-P2', 'category_id' => $test_cat_id, 'sale_price' => 20.00, 'reorder_level' => 5, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$p2_id = $db->insert_id();

$db->insert('products', array('name' => 'منتج تقارير 3', 'sku' => 'SKU-REP-P3', 'category_id' => $test_cat_id, 'sale_price' => 10.00, 'reorder_level' => 0, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$p3_id = $db->insert_id();

// 1.3 Stocks:
// In WH A: P1 quantity=4 (shortage=6, low stock), P2 quantity=15 (no shortage), P3 quantity=0 (reorder=0)
// In WH B: P1 quantity=10 (shortage=0, low stock because <= 10), P2 quantity=2 (shortage=3, low stock)
$db->insert('warehouse_stock', array('warehouse_id' => $wh_a_id, 'product_id' => $p1_id, 'quantity' => 4, 'reorder_level' => 10, 'created_at' => date('Y-m-d H:i:s')));
$db->insert('warehouse_stock', array('warehouse_id' => $wh_a_id, 'product_id' => $p2_id, 'quantity' => 15, 'reorder_level' => 5, 'created_at' => date('Y-m-d H:i:s')));
$db->insert('warehouse_stock', array('warehouse_id' => $wh_a_id, 'product_id' => $p3_id, 'quantity' => 0, 'reorder_level' => 0, 'created_at' => date('Y-m-d H:i:s')));

$db->insert('warehouse_stock', array('warehouse_id' => $wh_b_id, 'product_id' => $p1_id, 'quantity' => 10, 'reorder_level' => 10, 'created_at' => date('Y-m-d H:i:s')));
$db->insert('warehouse_stock', array('warehouse_id' => $wh_b_id, 'product_id' => $p2_id, 'quantity' => 2, 'reorder_level' => 5, 'created_at' => date('Y-m-d H:i:s')));

// 1.4 Customer
$db->insert('customers', array('name' => 'عميل تقارير أ', 'phone' => '0599988771', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')));
$cust_id = $db->insert_id();

// 1.5 Users: Admin, Warehouse User (assigned to WH A only), Sales User 1 (assigned to WH A only), Sales User 2 (assigned to WH B only)
$admin_user = $db->get_where('users', array('username' => 'admin'))->row();

$db->insert('users', array(
    'username'      => 'wh_rep_user',
    'full_name'     => 'أمين مستودع التقارير',
    'password_hash' => password_hash('Pass@123', PASSWORD_BCRYPT),
    'role_id'       => 2, // warehouse
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s')
));
$wh_user_id = $db->insert_id();

$db->insert('users', array(
    'username'      => 'sales_rep_1',
    'full_name'     => 'بائع تقارير 1',
    'password_hash' => password_hash('Pass@123', PASSWORD_BCRYPT),
    'role_id'       => 3, // sales
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s')
));
$sales1_id = $db->insert_id();

$db->insert('users', array(
    'username'      => 'sales_rep_2',
    'full_name'     => 'بائع تقارير 2',
    'password_hash' => password_hash('Pass@123', PASSWORD_BCRYPT),
    'role_id'       => 3, // sales
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s')
));
$sales2_id = $db->insert_id();

// Warehouse assignments
$CI->User_warehouse_model->assign($wh_user_id, $wh_a_id);
$CI->User_warehouse_model->assign($sales1_id, $wh_a_id);
$CI->User_warehouse_model->assign($sales2_id, $wh_b_id);

assert_test("Test fixtures prepared successfully", $wh_a_id > 0 && $wh_b_id > 0 && $p1_id > 0);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 2: STOCK BALANCES REPORT TESTS (3.1)
// -------------------------------------------------------------------------
echo "--- SECTION 2: STOCK BALANCES REPORT ---\n";

// 2.1 Admin sees all records (5 stock rows created)
$admin_stock_count = $CI->Report_model->count_stock_balances(array('category_id' => $test_cat_id), NULL);
assert_test("Admin counts all stock records in category (5)", $admin_stock_count === 5);

$admin_stock_rows = $CI->Report_model->get_stock_balances_paged(array('category_id' => $test_cat_id), 10, 0, NULL);
assert_test("Admin retrieves stock records paged", count($admin_stock_rows) === 5);

// Check shortage calculation on WH A P1 (qty=4, reorder=10 -> shortage=6)
$wh_a_p1 = NULL;
foreach ($admin_stock_rows as $sr) {
    if ((int)$sr->warehouse_id === $wh_a_id && (int)$sr->product_id === $p1_id) {
        $wh_a_p1 = $sr;
        break;
    }
}
assert_test("Stock balance shortage calculation: max(0, 10 - 4) = 6", $wh_a_p1 && (int)$wh_a_p1->shortage === 6);

// 2.2 Warehouse User scoped to WH A (3 stock rows)
$wh_scope_a = array($wh_a_id);
$wh_user_stock_count = $CI->Report_model->count_stock_balances(array('category_id' => $test_cat_id), $wh_scope_a);
assert_test("Warehouse user sees only assigned WH A records (3)", $wh_user_stock_count === 3);

// 2.3 Filter by category and search
$search_p1_count = $CI->Report_model->count_stock_balances(array('search' => 'SKU-REP-P1'), NULL);
assert_test("Search by SKU returns matching records across warehouses (2)", $search_p1_count === 2);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 3: LOW STOCK ALERT REPORT TESTS (3.5)
// -------------------------------------------------------------------------
echo "--- SECTION 3: LOW STOCK ALERT REPORT ---\n";

// Criteria: quantity <= reorder_level AND reorder_level > 0
// WH A: P1 (qty=4 <= 10 -> YES, shortage=6), P2 (qty=15 > 5 -> NO), P3 (reorder=0 -> NO)
// WH B: P1 (qty=10 <= 10 -> YES, shortage=0), P2 (qty=2 <= 5 -> YES, shortage=3)
// Total low stock in system: 3 items (WH A P1, WH B P1, WH B P2)

$total_low_stock = $CI->Report_model->count_low_stock(array('warehouse_id' => $wh_a_id), NULL);
assert_test("Low stock count in WH A is exactly 1 (P1)", $total_low_stock === 1);

$low_stock_rows_wh_a = $CI->Report_model->get_low_stock_paged(array('warehouse_id' => $wh_a_id), 10, 0, NULL);
assert_test("Low stock row in WH A has shortage = 6", count($low_stock_rows_wh_a) === 1 && (int)$low_stock_rows_wh_a[0]->shortage === 6);

$all_low_stock_count = $CI->Report_model->count_low_stock(array(), array($wh_a_id, $wh_b_id));
assert_test("All low stock across test warehouses is 3", $all_low_stock_count === 3);

// Warehouse user assigned to WH A only sees 1 low stock item
$wh_user_low_stock = $CI->Report_model->count_low_stock(array(), $wh_scope_a);
assert_test("Warehouse user assigned to WH A only sees 1 low stock item", $wh_user_low_stock === 1);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 4: STOCK MOVEMENTS REPORT TESTS (3.2)
// -------------------------------------------------------------------------
echo "--- SECTION 4: STOCK MOVEMENTS REPORT ---\n";

// Create movements with explicit idempotency keys
$today_date = date('Y-m-d');

$db->insert('stock_movements', array(
    'warehouse_id'     => $wh_a_id,
    'product_id'       => $p1_id,
    'movement_type'    => 'in',
    'quantity_delta'   => 10,
    'before_quantity'  => 0,
    'after_quantity'   => 10,
    'reason'           => 'توريد بضاعة تجريبي F09',
    'idempotency_key'  => 'idem_sm_in_' . uniqid('', true),
    'created_by'       => $wh_user_id,
    'created_at'       => $today_date . ' 10:00:00'
));
$sm_in_id = $db->insert_id();

$db->insert('stock_movements', array(
    'warehouse_id'     => $wh_a_id,
    'product_id'       => $p1_id,
    'movement_type'    => 'adjustment',
    'quantity_delta'   => -2,
    'before_quantity'  => 10,
    'after_quantity'   => 8,
    'reason'           => 'تسوية مخزنية لعجز مسجل',
    'idempotency_key'  => 'idem_sm_adj_' . uniqid('', true),
    'created_by'       => $wh_user_id,
    'created_at'       => $today_date . ' 11:00:00'
));
$sm_adj_id = $db->insert_id();

// Count movements by type
$count_in = $CI->Report_model->count_stock_movements(array('movement_type' => 'in', 'warehouse_id' => $wh_a_id), NULL);
assert_test("Filter stock movements by type 'in' returns matching record", $count_in === 1);

$count_adj = $CI->Report_model->count_stock_movements(array('movement_type' => 'adjustment', 'warehouse_id' => $wh_a_id), NULL);
assert_test("Filter stock movements by type 'adjustment' returns matching record", $count_adj === 1);

// Date boundary test: inclusive end-of-day
$count_date_range = $CI->Report_model->count_stock_movements(array(
    'date_from'    => $today_date,
    'date_to'      => $today_date,
    'warehouse_id' => $wh_a_id
), NULL);
assert_test("Movements filter covers inclusive full day (2)", $count_date_range === 2);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 5: SALES SUMMARY & INVOICES REPORT TESTS (3.3 & 3.4)
// -------------------------------------------------------------------------
echo "--- SECTION 5: SALES SUMMARY & INVOICES REPORTS ---\n";

// Create 3 invoices:
// Inv 1: Created by Sales1 in WH A, ACTIVE: Subtotal=200, Discount=20, Total=180
// Inv 2: Created by Sales2 in WH B, ACTIVE: Subtotal=100, Discount=0, Total=100
// Inv 3: Created by Sales1 in WH A, CANCELLED: Subtotal=300, Discount=50, Total=250

$db->insert('sales_invoices', array(
    'invoice_number'      => 'INV-REP-001',
    'customer_id'         => $cust_id,
    'warehouse_id'        => $wh_a_id,
    'created_by'          => $sales1_id,
    'invoice_date'        => $today_date,
    'status'              => 'active',
    'subtotal'            => 200.00,
    'discount_percentage' => 10.00,
    'discount_amount'     => 20.00,
    'total_amount'        => 180.00,
    'currency_code'       => 'SAR',
    'idempotency_key'     => 'idem_rep_001_' . uniqid('', true),
    'created_at'          => $today_date . ' 12:00:00'
));
$inv1_id = $db->insert_id();

$db->insert('sales_invoices', array(
    'invoice_number'      => 'INV-REP-002',
    'customer_id'         => $cust_id,
    'warehouse_id'        => $wh_b_id,
    'created_by'          => $sales2_id,
    'invoice_date'        => $today_date,
    'status'              => 'active',
    'subtotal'            => 100.00,
    'discount_percentage' => 0.00,
    'discount_amount'     => 0.00,
    'total_amount'        => 100.00,
    'currency_code'       => 'SAR',
    'idempotency_key'     => 'idem_rep_002_' . uniqid('', true),
    'created_at'          => $today_date . ' 13:00:00'
));
$inv2_id = $db->insert_id();

$db->insert('sales_invoices', array(
    'invoice_number'      => 'INV-REP-003',
    'customer_id'         => $cust_id,
    'warehouse_id'        => $wh_a_id,
    'created_by'          => $sales1_id,
    'invoice_date'        => $today_date,
    'status'              => 'cancelled',
    'subtotal'            => 300.00,
    'discount_percentage' => 16.67,
    'discount_amount'     => 50.00,
    'total_amount'        => 250.00,
    'currency_code'       => 'SAR',
    'cancelled_at'        => $today_date . ' 14:00:00',
    'cancelled_by'        => $admin_user->id,
    'cancellation_reason' => 'إلغاء لطلب العميل استرجاع الفاتورة بالكامل',
    'idempotency_key'     => 'idem_rep_003_' . uniqid('', true),
    'created_at'          => $today_date . ' 13:30:00'
));
$inv3_id = $db->insert_id();

// Also record corresponding sale movements for Inv 1 and Inv 3
$db->insert('stock_movements', array(
    'warehouse_id'     => $wh_a_id,
    'product_id'       => $p1_id,
    'movement_type'    => 'sale',
    'quantity_delta'   => -4,
    'before_quantity'  => 8,
    'after_quantity'   => 4,
    'sales_invoice_id' => $inv1_id,
    'idempotency_key'  => 'idem_sm_sale1_' . uniqid('', true),
    'created_by'       => $sales1_id,
    'created_at'       => $today_date . ' 12:00:00'
));
$sm_sale1_id = $db->insert_id();

$db->insert('stock_movements', array(
    'warehouse_id'     => $wh_a_id,
    'product_id'       => $p1_id,
    'movement_type'    => 'sale',
    'quantity_delta'   => -6,
    'before_quantity'  => 10,
    'after_quantity'   => 4,
    'sales_invoice_id' => $inv3_id,
    'idempotency_key'  => 'idem_sm_sale3_' . uniqid('', true),
    'created_by'       => $sales1_id,
    'created_at'       => $today_date . ' 13:30:00'
));
$sm_sale3_id = $db->insert_id();

// Reverse movement for Inv 3
$db->insert('stock_movements', array(
    'warehouse_id'            => $wh_a_id,
    'product_id'              => $p1_id,
    'movement_type'           => 'sale_reversal',
    'quantity_delta'          => 6,
    'before_quantity'         => 4,
    'after_quantity'          => 10,
    'sales_invoice_id'        => $inv3_id,
    'reversal_of_movement_id' => $sm_sale3_id,
    'reason'                  => 'إلغاء فاتورة البيع INV-REP-003',
    'idempotency_key'         => 'idem_sm_rev3_' . uniqid('', true),
    'created_by'              => $admin_user->id,
    'created_at'              => $today_date . ' 14:00:00'
));
$sm_rev3_id = $db->insert_id();

// 5.1 FINANCIAL INTEGRITY: Sales Summary MUST strictly exclude Inv 3 (cancelled)!
// Expected Admin active KPIs across test warehouses (WH A + WH B):
// Inv 1: Gross=200, Discount=20, Net=180
// Inv 2: Gross=100, Discount=0, Net=100
// Inv 3: EXCLUDED!
// Total Gross = 300, Total Discounts = 20, Total Net = 280, Count = 2
$admin_kpis = $CI->Report_model->get_sales_kpis(array('warehouse_id' => $wh_a_id), NULL, NULL);
assert_test("Sales summary for WH A has active count = 1 (Inv 1 only, Inv 3 cancelled excluded)", $admin_kpis->invoices_count === 1);
assert_test("Sales summary for WH A Gross Sales = 200.00", (float)$admin_kpis->gross_sales === 200.00);
assert_test("Sales summary for WH A Discounts = 20.00", (float)$admin_kpis->total_discounts === 20.00);
assert_test("Sales summary for WH A Net Sales = 180.00", (float)$admin_kpis->net_sales === 180.00);

// 5.2 Overall active sales across all warehouses for admin
$all_kpis = $CI->Report_model->get_sales_kpis(array('date_from' => $today_date, 'date_to' => $today_date), NULL, array($wh_a_id, $wh_b_id));
assert_test("Total active sales across WH A & B Gross = 300.00", (float)$all_kpis->gross_sales === 300.00);
assert_test("Total active sales across WH A & B Net = 280.00", (float)$all_kpis->net_sales === 280.00);
assert_test("Total active sales count = 2", $all_kpis->invoices_count === 2);

// 5.3 Sales User 1 Scoping: Sales1 sees only Inv 1 (Net=180)
$sales1_kpis = $CI->Report_model->get_sales_kpis(array(), $sales1_id, array($wh_a_id));
assert_test("Sales User 1 sees only own active sales count (1)", $sales1_kpis->invoices_count === 1);
assert_test("Sales User 1 Net Sales = 180.00", (float)$sales1_kpis->net_sales === 180.00);

// 5.4 Invoices Report: Displays BOTH active and cancelled invoices!
$admin_inv_count = $CI->Report_model->count_invoices(array('warehouse_id' => $wh_a_id), NULL, NULL);
assert_test("Invoices report shows all invoices in WH A (2: Inv 1 and Inv 3)", $admin_inv_count === 2);

$active_only_count = $CI->Report_model->count_invoices(array('warehouse_id' => $wh_a_id, 'status' => 'active'), NULL, NULL);
assert_test("Invoices report filtered by status 'active' = 1", $active_only_count === 1);

$cancelled_only_count = $CI->Report_model->count_invoices(array('warehouse_id' => $wh_a_id, 'status' => 'cancelled'), NULL, NULL);
assert_test("Invoices report filtered by status 'cancelled' = 1", $cancelled_only_count === 1);

// Verify cancelled invoice historical preservation in report rows
$inv_rows = $CI->Report_model->get_invoices_paged(array('status' => 'cancelled'), 10, 0, NULL, array($wh_a_id));
assert_test("Cancelled invoice row has status = 'cancelled'", count($inv_rows) === 1 && $inv_rows[0]->status === 'cancelled');
assert_test("Cancelled invoice preserves historical total (250.00)", (float)$inv_rows[0]->total_amount === 250.00);
assert_test("Cancelled invoice preserves cancellation reason", strpos($inv_rows[0]->cancellation_reason, 'إلغاء لطلب العميل') !== false);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 6: CSV SANITIZATION & FORMULA INJECTION SECURITY
// -------------------------------------------------------------------------
echo "--- SECTION 6: CSV FORMULA INJECTION & EXPORT SANITIZATION ---\n";

require_once APPPATH . 'controllers/Reports.php';

// Test 6.1: Formula triggers (=, @) must be prefixed with '
assert_test("Sanitize cell '=1+1' prefixes with single quote", Reports::sanitize_csv_cell('=1+1') === "'=1+1");
assert_test("Sanitize cell '=cmd|' /C calc'!A0' prefixes with single quote", Reports::sanitize_csv_cell("=cmd|' /C calc'!A0") === "'=cmd|' /C calc'!A0");
assert_test("Sanitize cell '@SUM(A1:A10)' prefixes with single quote", Reports::sanitize_csv_cell('@SUM(A1:A10)') === "'@SUM(A1:A10)");

// Test 6.2: Non-numeric strings starting with + or - prefixed with '
assert_test("Sanitize non-numeric string '+cmd' prefixes with '", Reports::sanitize_csv_cell('+cmd') === "'+cmd");
assert_test("Sanitize non-numeric string '-2+3' prefixes with '", Reports::sanitize_csv_cell('-2+3') === "'-2+3");

// Test 6.3: Valid numeric values starting with - or + must NOT be mangled
assert_test("Sanitize valid signed negative integer '-5' remains '-5'", Reports::sanitize_csv_cell('-5') === '-5');
assert_test("Sanitize valid signed positive integer '+10' remains '+10'", Reports::sanitize_csv_cell('+10') === '+10');
assert_test("Sanitize valid decimal '-12.50' remains '-12.50'", Reports::sanitize_csv_cell('-12.50') === '-12.50');

// Test 6.4: Regular Arabic and English text untouched
assert_test("Sanitize standard Arabic text untouched", Reports::sanitize_csv_cell('مستودع الرياض') === 'مستودع الرياض');
assert_test("Sanitize empty string remains empty", Reports::sanitize_csv_cell('') === '');
assert_test("Sanitize NULL returns empty string", Reports::sanitize_csv_cell(NULL) === '');

echo "\n";

// -------------------------------------------------------------------------
// SECTION 7: HTTP CONTROLLER ENDPOINTS & ROLE AUTHORIZATION
// -------------------------------------------------------------------------
echo "--- SECTION 7: HTTP CONTROLLER ENDPOINTS & ROLE AUTHORIZATION ---\n";

$base_url = 'http://localhost/codeigniter-projects/mini-erp-stock-sales';

// 7.1 Unauthenticated guest redirected to login
$guest_client = new HttpTestClient($base_url);
$guest_res = $guest_client->request('index.php/reports');
assert_test("Unauthenticated access to /reports redirects to login (302)", is_redirect_status($guest_res['status']) && strpos($guest_res['location'], 'login') !== false);

// 7.2 Warehouse user accesses reports center
$wh_client = new HttpTestClient($base_url);
assert_test("Warehouse user logs in", login_client($wh_client, 'wh_rep_user'));

$wh_hub_res = $wh_client->request('index.php/reports');
assert_test("Warehouse user can access reports hub (200 OK)", $wh_hub_res['status'] === 200);
assert_test("Warehouse user sees Stock Balances card", strpos($wh_hub_res['body'], 'تقرير أرصدة المخزون') !== false);
assert_test("Warehouse user DOES NOT see Sales Summary card", strpos($wh_hub_res['body'], 'تقرير ملخص المبيعات الفعلية') === false);

// 7.3 Warehouse user strictly blocked from Sales reports
$wh_sales_res = $wh_client->request('index.php/reports/sales');
assert_test("Warehouse user accessing /reports/sales is BLOCKED (redirected 302)", is_redirect_status($wh_sales_res['status']));

$wh_inv_res = $wh_client->request('index.php/reports/invoices');
assert_test("Warehouse user accessing /reports/invoices is BLOCKED (redirected 302)", is_redirect_status($wh_inv_res['status']));

// 7.4 Warehouse user accesses stock report
$wh_stock_res = $wh_client->request('index.php/reports/stock');
assert_test("Warehouse user accesses /reports/stock (200 OK)", $wh_stock_res['status'] === 200);
assert_test("Warehouse user sees assigned WH A in HTML", strpos($wh_stock_res['body'], 'مستودع تقارير أ') !== false);
assert_test("Warehouse user CANNOT see unassigned WH B in HTML", strpos($wh_stock_res['body'], 'مستودع تقارير ب') === false);

// 7.5 Sales User accesses reports
$sales_client = new HttpTestClient($base_url);
assert_test("Sales User 1 logs in", login_client($sales_client, 'sales_rep_1'));
$sales_hub_res = $sales_client->request('index.php/reports');
assert_test("Sales user accesses reports hub (200 OK)", $sales_hub_res['status'] === 200);
assert_test("Sales user sees Sales Summary card", strpos($sales_hub_res['body'], 'تقرير ملخص المبيعات الفعلية') !== false);

$sales_summary_res = $sales_client->request('index.php/reports/sales');
assert_test("Sales user accesses /reports/sales (200 OK)", $sales_summary_res['status'] === 200);
assert_test("Sales user sees own invoice INV-REP-001 in HTML", strpos($sales_summary_res['body'], 'INV-REP-001') !== false);
assert_test("Sales user CANNOT see Sales 2 invoice INV-REP-002 in HTML", strpos($sales_summary_res['body'], 'INV-REP-002') === false);

// 7.6 Admin accesses all reports
$admin_client = new HttpTestClient($base_url);
assert_test("Admin logs in", login_client($admin_client, 'admin', 'Admin@123456'));
$admin_hub_res = $admin_client->request('index.php/reports');
assert_test("Admin accesses reports hub (200 OK)", $admin_hub_res['status'] === 200);

$admin_sales_res = $admin_client->request('index.php/reports/sales');
assert_test("Admin accesses /reports/sales (200 OK)", $admin_sales_res['status'] === 200);
assert_test("Admin sees both INV-REP-001 and INV-REP-002 in HTML", 
    strpos($admin_sales_res['body'], 'INV-REP-001') !== false && strpos($admin_sales_res['body'], 'INV-REP-002') !== false);

// 7.7 CSV Export via HTTP includes UTF-8 BOM
$csv_export_res = $admin_client->request('index.php/reports/stock?export=csv');
assert_test("Stock CSV export returns 200 OK", $csv_export_res['status'] === 200);
assert_test("Stock CSV export header Content-Type is text/csv", strpos($csv_export_res['header'], 'text/csv') !== false);
assert_test("Stock CSV export header Content-Disposition has attachment", strpos($csv_export_res['header'], 'attachment; filename="report_stock_balances_') !== false);

// Verify BOM (\xEF\xBB\xBF) at start of CSV body
$bom = substr($csv_export_res['body'], 0, 3);
assert_test("Stock CSV export body begins with UTF-8 BOM", $bom === "\xEF\xBB\xBF");
assert_test("Stock CSV export contains Arabic headers", strpos($csv_export_res['body'], 'اسم المنتج') !== false);

echo "\n";

// -------------------------------------------------------------------------
// SECTION 8: CLEAN TEARDOWN & RECOVERY
// -------------------------------------------------------------------------
echo "--- SECTION 8: CLEAN TEARDOWN & RECOVERY ---\n";

// 1. Delete stock movements created in test
// Note: delete reversal movement first, then sale movement due to FK fk_sm_reversal_of
$db->where_in('id', array($sm_rev3_id))->delete('stock_movements');
$db->where_in('id', array($sm_in_id, $sm_adj_id, $sm_sale1_id, $sm_sale3_id))->delete('stock_movements');

// 2. Delete test invoices
$db->where_in('id', array($inv1_id, $inv2_id, $inv3_id))->delete('sales_invoices');

// 3. Delete test stock
$db->where_in('warehouse_id', array($wh_a_id, $wh_b_id))->delete('warehouse_stock');

// 4. Delete user warehouse assignments
$db->where_in('user_id', array($wh_user_id, $sales1_id, $sales2_id))->delete('user_warehouses');

// 5. Delete test users
$db->where_in('id', array($wh_user_id, $sales1_id, $sales2_id))->delete('users');

// 6. Delete test customer
$db->where('id', $cust_id)->delete('customers');

// 7. Delete test products & category
$db->where_in('id', array($p1_id, $p2_id, $p3_id))->delete('products');
$db->where('id', $test_cat_id)->delete('categories');

// 8. Delete test warehouses
$db->where_in('id', array($wh_a_id, $wh_b_id))->delete('warehouses');

// Final check against baseline counts
$final_stock_count     = $db->count_all('warehouse_stock');
$final_movements_count = $db->count_all('stock_movements');
$final_invoices_count  = $db->count_all('sales_invoices');
$final_items_count     = $db->count_all('sales_invoice_items');
$final_customers_count = $db->count_all('customers');
$final_users_count     = $db->count_all('users');
$final_sequences_count = $db->count_all('invoice_sequences');

assert_test("Zero collateral damage on warehouse_stock ({$final_stock_count} == {$initial_stock_count})", $final_stock_count === $initial_stock_count);
assert_test("Zero collateral damage on stock_movements ({$final_movements_count} == {$initial_movements_count})", $final_movements_count === $initial_movements_count);
assert_test("Zero collateral damage on sales_invoices ({$final_invoices_count} == {$initial_invoices_count})", $final_invoices_count === $initial_invoices_count);
assert_test("Zero collateral damage on sales_invoice_items ({$final_items_count} == {$initial_items_count})", $final_items_count === $initial_items_count);
assert_test("Zero collateral damage on customers ({$final_customers_count} == {$initial_customers_count})", $final_customers_count === $initial_customers_count);
assert_test("Zero collateral damage on users ({$final_users_count} == {$initial_users_count})", $final_users_count === $initial_users_count);
assert_test("Zero collateral damage on invoice_sequences ({$final_sequences_count} == {$initial_sequences_count})", $final_sequences_count === $initial_sequences_count);

echo "\n====================================================================\n";
echo " TEST SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
