<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reports Controller — F09 Reports, Business Insights & CSV Export
 *
 * Implements 5 operational and financial reports:
 * 1. Warehouse Stock Balances Report (reports/stock)
 * 2. Stock Movements Report (reports/stock_movements)
 * 3. Sales Summary Report (reports/sales) — Excludes cancelled invoices
 * 4. Invoices Report (reports/invoices) — Active and cancelled with historical accuracy
 * 5. Low Stock Alert Report (reports/low_stock) — quantity <= reorder_level
 *
 * Role Authorization Scoping:
 * - Admin: Unrestricted access to all reports across all warehouses.
 * - Warehouse: Scoped to assigned warehouses for stock, movements, low stock. Strictly FORBIDDEN from sales & invoices reports.
 * - Sales: Scoped to assigned warehouses and own created records for sales & invoices. Read-only stock balances.
 *
 * CSV Protection: Formula Injection Sanitization + UTF-8 BOM for Microsoft Excel Arabic support.
 * Compatibility: PHP 7.4+ / PHP 8.2, CodeIgniter 3.1.13
 */
#[\AllowDynamicProperties]
class Reports extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Report_model');
        $this->load->model('Warehouse_model');
        $this->load->model('Product_model');
        $this->load->model('Category_model');
        $this->load->model('Customer_model');
        $this->load->model('User_model');
        $this->load->model('User_warehouse_model');
    }

    /**
     * Get authorized warehouse IDs for current user
     * - Admin: NULL (unrestricted)
     * - Warehouse / Sales: array of assigned warehouse IDs
     *
     * @return array|null
     */
    protected function get_user_warehouse_scope()
    {
        if ($this->is_admin()) {
            return NULL;
        }

        return $this->User_warehouse_model->get_assigned_warehouse_ids($this->current_user->id);
    }

    /**
     * Get user ID scope for sales and invoices
     * - Admin: NULL (all sales / users)
     * - Sales: current user ID (own records only)
     *
     * @return int|null
     */
    protected function get_user_sales_scope()
    {
        if ($this->is_admin()) {
            return NULL;
        }

        return (int)$this->current_user->id;
    }

    /**
     * Enforce role authorization for sales and invoices reports
     * Warehouse role is strictly prohibited from accessing sales/invoices reports.
     *
     * @return void
     */
    protected function require_sales_reports_access()
    {
        if ($this->is_warehouse()) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى تقارير المبيعات والفواتير.');
            redirect('reports');
            exit;
        }
    }

    /**
     * Get list of authorized warehouses for dropdowns
     *
     * @return array
     */
    protected function get_accessible_warehouses()
    {
        $scope = $this->get_user_warehouse_scope();
        if ($scope === NULL) {
            return $this->Warehouse_model->get_active();
        }

        if (empty($scope)) {
            return array();
        }

        return $this->User_warehouse_model->get_warehouses_by_user($this->current_user->id);
    }

    /**
     * Check if requested warehouse_id is within user's allowed scope
     *
     * @param int|null $warehouse_id
     * @return bool
     */
    protected function check_warehouse_scope_permission($warehouse_id)
    {
        if ($warehouse_id === NULL || (int)$warehouse_id <= 0) {
            return TRUE;
        }

        $scope = $this->get_user_warehouse_scope();
        if ($scope === NULL) {
            return TRUE;
        }

        return in_array((int)$warehouse_id, array_map('intval', $scope), TRUE);
    }

    // =========================================================================
    // REPORTS HUB / OVERVIEW
    // =========================================================================

    /**
     * Reports Center landing page
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'مركز التقارير التشغيلية';
        $data['can_view_sales'] = ($this->is_admin() || ($this->current_user && $this->current_user->role_name === 'sales'));
        $data['can_view_stock'] = TRUE; // All roles can view stock operational reports within scope

        $this->render_view('reports/index', $data);
    }

    // =========================================================================
    // 1. STOCK BALANCES REPORT (3.1)
    // =========================================================================

    /**
     * Stock Balances Report view & export
     *
     * @return void
     */
    public function stock_balances()
    {
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $category_id  = $this->input->get('category_id', TRUE);
        $search       = $this->input->get('search', TRUE);
        $export       = $this->input->get('export', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $category_id  = ($category_id !== NULL && (int)$category_id > 0) ? (int)$category_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        // Security: Validate warehouse scope
        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/stock');
            return;
        }

        $user_wh_scope = $this->get_user_warehouse_scope();
        $filters = array(
            'warehouse_id' => $warehouse_id,
            'category_id'  => $category_id,
            'search'       => $search,
        );

        // CSV Export Handler
        if ($export === 'csv') {
            $this->export_stock_csv_internal($filters, $user_wh_scope);
            return;
        }

        $per_page   = $this->get_per_page_limit(20);
        $total_rows = $this->Report_model->count_stock_balances($filters, $user_wh_scope);
        $pagination = $this->setup_pagination('reports/stock', $total_rows, $per_page);

        $data['page_title']    = 'تقرير أرصدة المخزون بالمستودعات';
        $data['rows']          = $this->Report_model->get_stock_balances_paged($filters, $per_page, $pagination['offset'], $user_wh_scope);
        $data['pagination']    = $pagination;
        $data['total_count']   = $total_rows;
        $data['filters']       = $filters;
        $data['warehouses']    = $this->get_accessible_warehouses();
        $data['categories']    = $this->Category_model->get_active();

        $this->render_view('reports/stock_balances', $data);
    }

    /**
     * Dedicated route for exporting stock balances CSV
     *
     * @return void
     */
    public function export_stock_csv()
    {
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $category_id  = $this->input->get('category_id', TRUE);
        $search       = $this->input->get('search', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $category_id  = ($category_id !== NULL && (int)$category_id > 0) ? (int)$category_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/stock');
            return;
        }

        $user_wh_scope = $this->get_user_warehouse_scope();
        $filters = array(
            'warehouse_id' => $warehouse_id,
            'category_id'  => $category_id,
            'search'       => $search,
        );

        $this->export_stock_csv_internal($filters, $user_wh_scope);
    }

    /**
     * Internal stock CSV streamer
     */
    protected function export_stock_csv_internal(array $filters, $user_wh_scope)
    {
        $records = $this->Report_model->get_all_stock_balances($filters, $user_wh_scope);

        $headers = array('الرقم', 'اسم المنتج', 'رمز SKU', 'التصنيف', 'المستودع', 'رمز المستودع', 'الكمية الحالية', 'حد التنبيه', 'مقدار النقص', 'حالة الرصيد');
        $csv_rows = array();
        $i = 1;

        foreach ($records as $r) {
            $status_text = ((int)$r->reorder_level > 0 && (int)$r->quantity <= (int)$r->reorder_level) ? 'مخزون منخفض' : 'متوفر';
            if ((int)$r->quantity === 0) {
                $status_text = 'نافد';
            }

            $csv_rows[] = array(
                $i++,
                $r->product_name,
                $r->product_sku,
                $r->category_name ? $r->category_name : 'بدون تصنيف',
                $r->warehouse_name,
                $r->warehouse_code,
                (int)$r->quantity,
                (int)$r->reorder_level,
                (int)$r->shortage,
                $status_text,
            );
        }

        $filename = 'report_stock_balances_' . date('Y-m-d_His') . '.csv';
        $this->output_csv($filename, $headers, $csv_rows);
    }

    // =========================================================================
    // 2. STOCK MOVEMENTS REPORT (3.2)
    // =========================================================================

    /**
     * Stock Movements Report view & export
     *
     * @return void
     */
    public function stock_movements()
    {
        $date_from     = $this->input->get('date_from', TRUE);
        $date_to       = $this->input->get('date_to', TRUE);
        $warehouse_id  = $this->input->get('warehouse_id', TRUE);
        $product_id    = $this->input->get('product_id', TRUE);
        $movement_type = $this->input->get('movement_type', TRUE);
        $search        = $this->input->get('search', TRUE);
        $export        = $this->input->get('export', TRUE);

        $date_from    = $this->Report_model->validate_date($date_from);
        $date_to      = $this->Report_model->validate_date($date_to);
        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $product_id   = ($product_id !== NULL && (int)$product_id > 0) ? (int)$product_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        $allowed_types = array('in', 'adjustment', 'sale', 'sale_reversal');
        $movement_type = in_array($movement_type, $allowed_types, TRUE) ? $movement_type : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/stock_movements');
            return;
        }

        $user_wh_scope = $this->get_user_warehouse_scope();
        $filters = array(
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'warehouse_id'  => $warehouse_id,
            'product_id'    => $product_id,
            'movement_type' => $movement_type,
            'search'        => $search,
        );

        if ($export === 'csv') {
            $this->export_stock_movements_csv_internal($filters, $user_wh_scope);
            return;
        }

        $per_page   = $this->get_per_page_limit(20);
        $total_rows = $this->Report_model->count_stock_movements($filters, $user_wh_scope);
        $pagination = $this->setup_pagination('reports/stock_movements', $total_rows, $per_page);

        $data['page_title']    = 'تقرير حركات المخزون';
        $data['rows']          = $this->Report_model->get_stock_movements_paged($filters, $per_page, $pagination['offset'], $user_wh_scope);
        $data['pagination']    = $pagination;
        $data['total_count']   = $total_rows;
        $data['filters']       = $filters;
        $data['warehouses']    = $this->get_accessible_warehouses();
        $data['products']      = $this->Product_model->get_products(1000, 0, NULL, NULL, 1);

        $this->render_view('reports/stock_movements', $data);
    }

    /**
     * Dedicated route for exporting stock movements CSV
     *
     * @return void
     */
    public function export_stock_movements_csv()
    {
        $date_from     = $this->Report_model->validate_date($this->input->get('date_from', TRUE));
        $date_to       = $this->Report_model->validate_date($this->input->get('date_to', TRUE));
        $warehouse_id  = $this->input->get('warehouse_id', TRUE);
        $product_id    = $this->input->get('product_id', TRUE);
        $movement_type = $this->input->get('movement_type', TRUE);
        $search        = $this->input->get('search', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $product_id   = ($product_id !== NULL && (int)$product_id > 0) ? (int)$product_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        $allowed_types = array('in', 'adjustment', 'sale', 'sale_reversal');
        $movement_type = in_array($movement_type, $allowed_types, TRUE) ? $movement_type : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/stock_movements');
            return;
        }

        $user_wh_scope = $this->get_user_warehouse_scope();
        $filters = array(
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'warehouse_id'  => $warehouse_id,
            'product_id'    => $product_id,
            'movement_type' => $movement_type,
            'search'        => $search,
        );

        $this->export_stock_movements_csv_internal($filters, $user_wh_scope);
    }

    /**
     * Internal movements CSV streamer
     */
    protected function export_stock_movements_csv_internal(array $filters, $user_wh_scope)
    {
        $records = $this->Report_model->get_all_stock_movements($filters, $user_wh_scope);

        $headers = array('الرقم', 'تاريخ ووقت الحركة', 'اسم المنتج', 'رمز SKU', 'المستودع', 'نوع الحركة', 'الكمية المتغيرة', 'الرصيد قبل', 'الرصيد بعد', 'المستخدم المنفذ', 'السبب أو المرجع');
        $csv_rows = array();
        $i = 1;

        $type_labels = array(
            'in'            => 'إدخال مخزون',
            'adjustment'    => 'تسوية مخزنية',
            'sale'          => 'بيع',
            'sale_reversal' => 'عكس بيع (إلغاء فاتورة)',
        );

        foreach ($records as $r) {
            $label = isset($type_labels[$r->movement_type]) ? $type_labels[$r->movement_type] : $r->movement_type;
            $ref = $r->reason;
            if ($r->invoice_number) {
                $ref = ($ref ? $ref . ' | ' : '') . 'فاتورة: ' . $r->invoice_number;
            }

            $csv_rows[] = array(
                $i++,
                $r->created_at,
                $r->product_name,
                $r->product_sku,
                $r->warehouse_name,
                $label,
                (int)$r->quantity_delta,
                (int)$r->before_quantity,
                (int)$r->after_quantity,
                $r->user_name ? $r->user_name : $r->username,
                $ref ? $ref : '—',
            );
        }

        $filename = 'report_stock_movements_' . date('Y-m-d_His') . '.csv';
        $this->output_csv($filename, $headers, $csv_rows);
    }

    // =========================================================================
    // 3. SALES SUMMARY REPORT (3.3)
    // =========================================================================

    /**
     * Sales Summary Report view & export
     *
     * @return void
     */
    public function sales_summary()
    {
        $this->require_sales_reports_access();

        $date_from    = $this->Report_model->validate_date($this->input->get('date_from', TRUE));
        $date_to      = $this->Report_model->validate_date($this->input->get('date_to', TRUE));
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $customer_id  = $this->input->get('customer_id', TRUE);
        $search       = $this->input->get('search', TRUE);
        $export       = $this->input->get('export', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $customer_id  = ($customer_id !== NULL && (int)$customer_id > 0) ? (int)$customer_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/sales');
            return;
        }

        $user_id_scope = $this->get_user_sales_scope();
        $user_wh_scope = $this->get_user_warehouse_scope();

        $filters = array(
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'warehouse_id' => $warehouse_id,
            'customer_id'  => $customer_id,
            'search'       => $search,
        );

        if ($export === 'csv') {
            $this->export_sales_csv_internal($filters, $user_id_scope, $user_wh_scope);
            return;
        }

        $per_page   = $this->get_per_page_limit(20);
        $total_rows = $this->Report_model->count_sales($filters, $user_id_scope, $user_wh_scope);
        $pagination = $this->setup_pagination('reports/sales', $total_rows, $per_page);

        $data['page_title']    = 'تقرير ملخص المبيعات الفعلية';
        $data['kpis']          = $this->Report_model->get_sales_kpis($filters, $user_id_scope, $user_wh_scope);
        $data['rows']          = $this->Report_model->get_sales_paged($filters, $per_page, $pagination['offset'], $user_id_scope, $user_wh_scope);
        $data['pagination']    = $pagination;
        $data['total_count']   = $total_rows;
        $data['filters']       = $filters;
        $data['warehouses']    = $this->get_accessible_warehouses();
        $data['customers']     = $this->Customer_model->get_all(FALSE);

        $this->render_view('reports/sales_summary', $data);
    }

    /**
     * Dedicated route for exporting sales summary CSV
     *
     * @return void
     */
    public function export_sales_csv()
    {
        $this->require_sales_reports_access();

        $date_from    = $this->Report_model->validate_date($this->input->get('date_from', TRUE));
        $date_to      = $this->Report_model->validate_date($this->input->get('date_to', TRUE));
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $customer_id  = $this->input->get('customer_id', TRUE);
        $search       = $this->input->get('search', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $customer_id  = ($customer_id !== NULL && (int)$customer_id > 0) ? (int)$customer_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/sales');
            return;
        }

        $user_id_scope = $this->get_user_sales_scope();
        $user_wh_scope = $this->get_user_warehouse_scope();

        $filters = array(
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'warehouse_id' => $warehouse_id,
            'customer_id'  => $customer_id,
            'search'       => $search,
        );

        $this->export_sales_csv_internal($filters, $user_id_scope, $user_wh_scope);
    }

    /**
     * Internal sales CSV streamer
     */
    protected function export_sales_csv_internal(array $filters, $user_id_scope, $user_wh_scope)
    {
        $kpis    = $this->Report_model->get_sales_kpis($filters, $user_id_scope, $user_wh_scope);
        $records = $this->Report_model->get_all_sales($filters, $user_id_scope, $user_wh_scope);

        $headers = array('الرقم', 'رقم الفاتورة', 'تاريخ الفاتورة', 'العميل', 'المستودع', 'المجموع قبل الخصم (SAR)', 'الخصم (SAR)', 'صافي المبيعات (SAR)', 'العملة', 'البائع / المنشئ');
        $csv_rows = array();
        $i = 1;

        foreach ($records as $r) {
            $csv_rows[] = array(
                $i++,
                $r->invoice_number,
                $r->invoice_date,
                $r->customer_name,
                $r->warehouse_name,
                number_format((float)$r->subtotal, 2, '.', ''),
                number_format((float)$r->discount_amount, 2, '.', ''),
                number_format((float)$r->total_amount, 2, '.', ''),
                'SAR',
                $r->sales_user_name ? $r->sales_user_name : $r->username,
            );
        }

        // Add summary totals row at bottom
        $csv_rows[] = array(
            'الإجمالي',
            'عدد الفواتير النشطة: ' . $kpis->invoices_count,
            '—',
            '—',
            '—',
            number_format((float)$kpis->gross_sales, 2, '.', ''),
            number_format((float)$kpis->total_discounts, 2, '.', ''),
            number_format((float)$kpis->net_sales, 2, '.', ''),
            'SAR',
            '—',
        );

        $filename = 'report_sales_summary_' . date('Y-m-d_His') . '.csv';
        $this->output_csv($filename, $headers, $csv_rows);
    }

    // =========================================================================
    // 4. INVOICES REPORT (3.4)
    // =========================================================================

    /**
     * Invoices Report view & export
     *
     * @return void
     */
    public function invoices()
    {
        $this->require_sales_reports_access();

        $date_from    = $this->Report_model->validate_date($this->input->get('date_from', TRUE));
        $date_to      = $this->Report_model->validate_date($this->input->get('date_to', TRUE));
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $customer_id  = $this->input->get('customer_id', TRUE);
        $status       = $this->input->get('status', TRUE);
        $created_by   = $this->input->get('created_by', TRUE);
        $search       = $this->input->get('search', TRUE);
        $export       = $this->input->get('export', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $customer_id  = ($customer_id !== NULL && (int)$customer_id > 0) ? (int)$customer_id : NULL;
        $created_by   = ($created_by !== NULL && (int)$created_by > 0) ? (int)$created_by : NULL;
        $status       = in_array($status, array('active', 'cancelled'), TRUE) ? $status : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/invoices');
            return;
        }

        $user_id_scope = $this->get_user_sales_scope();
        $user_wh_scope = $this->get_user_warehouse_scope();

        $filters = array(
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'warehouse_id' => $warehouse_id,
            'customer_id'  => $customer_id,
            'status'       => $status,
            'created_by'   => $created_by,
            'search'       => $search,
        );

        if ($export === 'csv') {
            $this->export_invoices_csv_internal($filters, $user_id_scope, $user_wh_scope);
            return;
        }

        $per_page   = $this->get_per_page_limit(20);
        $total_rows = $this->Report_model->count_invoices($filters, $user_id_scope, $user_wh_scope);
        $pagination = $this->setup_pagination('reports/invoices', $total_rows, $per_page);

        $data['page_title']    = 'تقرير الفواتير';
        $data['rows']          = $this->Report_model->get_invoices_paged($filters, $per_page, $pagination['offset'], $user_id_scope, $user_wh_scope);
        $data['pagination']    = $pagination;
        $data['total_count']   = $total_rows;
        $data['filters']       = $filters;
        $data['warehouses']    = $this->get_accessible_warehouses();
        $data['customers']     = $this->Customer_model->get_all(TRUE);
        $data['users']         = $this->is_admin() ? $this->User_model->get_all_users() : array();

        $this->render_view('reports/invoices', $data);
    }

    /**
     * Dedicated route for exporting invoices CSV
     *
     * @return void
     */
    public function export_invoices_csv()
    {
        $this->require_sales_reports_access();

        $date_from    = $this->Report_model->validate_date($this->input->get('date_from', TRUE));
        $date_to      = $this->Report_model->validate_date($this->input->get('date_to', TRUE));
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $customer_id  = $this->input->get('customer_id', TRUE);
        $status       = $this->input->get('status', TRUE);
        $created_by   = $this->input->get('created_by', TRUE);
        $search       = $this->input->get('search', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $customer_id  = ($customer_id !== NULL && (int)$customer_id > 0) ? (int)$customer_id : NULL;
        $created_by   = ($created_by !== NULL && (int)$created_by > 0) ? (int)$created_by : NULL;
        $status       = in_array($status, array('active', 'cancelled'), TRUE) ? $status : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/invoices');
            return;
        }

        $user_id_scope = $this->get_user_sales_scope();
        $user_wh_scope = $this->get_user_warehouse_scope();

        $filters = array(
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'warehouse_id' => $warehouse_id,
            'customer_id'  => $customer_id,
            'status'       => $status,
            'created_by'   => $created_by,
            'search'       => $search,
        );

        $this->export_invoices_csv_internal($filters, $user_id_scope, $user_wh_scope);
    }

    /**
     * Internal invoices CSV streamer
     */
    protected function export_invoices_csv_internal(array $filters, $user_id_scope, $user_wh_scope)
    {
        $records = $this->Report_model->get_all_invoices($filters, $user_id_scope, $user_wh_scope);

        $headers = array('الرقم', 'رقم الفاتورة', 'تاريخ الفاتورة', 'العميل', 'المستودع', 'الحالة', 'المجموع قبل الخصم (SAR)', 'الخصم (SAR)', 'صافي الإجمالي (SAR)', 'العملة', 'المنشئ', 'تاريخ الإلغاء', 'المنفذ للإلغاء', 'سبب الإلغاء');
        $csv_rows = array();
        $i = 1;

        foreach ($records as $r) {
            $status_label = ($r->status === 'cancelled') ? 'ملغاة' : 'نشطة';

            $csv_rows[] = array(
                $i++,
                $r->invoice_number,
                $r->invoice_date,
                $r->customer_name,
                $r->warehouse_name,
                $status_label,
                number_format((float)$r->subtotal, 2, '.', ''),
                number_format((float)$r->discount_amount, 2, '.', ''),
                number_format((float)$r->total_amount, 2, '.', ''),
                'SAR',
                $r->created_by_name ? $r->created_by_name : $r->username,
                $r->cancelled_at ? $r->cancelled_at : '—',
                $r->cancelled_by_name ? $r->cancelled_by_name : '—',
                $r->cancellation_reason ? $r->cancellation_reason : '—',
            );
        }

        $filename = 'report_invoices_' . date('Y-m-d_His') . '.csv';
        $this->output_csv($filename, $headers, $csv_rows);
    }

    // =========================================================================
    // 5. LOW STOCK ALERT REPORT (3.5)
    // =========================================================================

    /**
     * Low Stock Alert Report view & export
     *
     * @return void
     */
    public function low_stock()
    {
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $search       = $this->input->get('search', TRUE);
        $export       = $this->input->get('export', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/low_stock');
            return;
        }

        $user_wh_scope = $this->get_user_warehouse_scope();
        $filters = array(
            'warehouse_id' => $warehouse_id,
            'search'       => $search,
        );

        if ($export === 'csv') {
            $this->export_low_stock_csv_internal($filters, $user_wh_scope);
            return;
        }

        $per_page   = $this->get_per_page_limit(20);
        $total_rows = $this->Report_model->count_low_stock($filters, $user_wh_scope);
        $pagination = $this->setup_pagination('reports/low_stock', $total_rows, $per_page);

        $data['page_title']    = 'تقرير المخزون المنخفض وتنبيهات إعادة الطلب';
        $data['rows']          = $this->Report_model->get_low_stock_paged($filters, $per_page, $pagination['offset'], $user_wh_scope);
        $data['pagination']    = $pagination;
        $data['total_count']   = $total_rows;
        $data['filters']       = $filters;
        $data['warehouses']    = $this->get_accessible_warehouses();

        $this->render_view('reports/low_stock', $data);
    }

    /**
     * Dedicated route for exporting low stock CSV
     *
     * @return void
     */
    public function export_low_stock_csv()
    {
        $warehouse_id = $this->input->get('warehouse_id', TRUE);
        $search       = $this->input->get('search', TRUE);

        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;

        if (!$this->check_warehouse_scope_permission($warehouse_id)) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى بيانات المستودع المحدد.');
            redirect('reports/low_stock');
            return;
        }

        $user_wh_scope = $this->get_user_warehouse_scope();
        $filters = array(
            'warehouse_id' => $warehouse_id,
            'search'       => $search,
        );

        $this->export_low_stock_csv_internal($filters, $user_wh_scope);
    }

    /**
     * Internal low stock CSV streamer
     */
    protected function export_low_stock_csv_internal(array $filters, $user_wh_scope)
    {
        $records = $this->Report_model->get_all_low_stock($filters, $user_wh_scope);

        $headers = array('الرقم', 'اسم المنتج', 'رمز SKU', 'التصنيف', 'المستودع', 'رمز المستودع', 'الكمية الحالية', 'حد التنبيه', 'مقدار النقص', 'الحالة');
        $csv_rows = array();
        $i = 1;

        foreach ($records as $r) {
            $status_text = ((int)$r->quantity === 0) ? 'نافد تماماً' : 'منخفض';

            $csv_rows[] = array(
                $i++,
                $r->product_name,
                $r->product_sku,
                $r->category_name ? $r->category_name : 'بدون تصنيف',
                $r->warehouse_name,
                $r->warehouse_code,
                (int)$r->quantity,
                (int)$r->reorder_level,
                (int)$r->shortage,
                $status_text,
            );
        }

        $filename = 'report_low_stock_' . date('Y-m-d_His') . '.csv';
        $this->output_csv($filename, $headers, $csv_rows);
    }

    // =========================================================================
    // CSV OUTPUT STREAMER & FORMULA INJECTION SANITIZATION
    // =========================================================================

    /**
     * Sanitize cell value to prevent CSV Formula Injection (DDE attacks)
     *
     * Prefixes malicious triggers (=, +, -, @) with a single quote if string is not numeric.
     *
     * @param mixed $val
     * @return string
     */
    public static function sanitize_csv_cell($val)
    {
        if ($val === NULL) {
            return '';
        }

        $str = (string)$val;

        if (strlen($str) > 0) {
            $first = $str[0];
            if ($first === '=' || $first === '@') {
                $str = "'" . $str;
            } elseif ($first === '+' || $first === '-') {
                if (!is_numeric($str)) {
                    $str = "'" . $str;
                }
            }
        }

        return $str;
    }

    /**
     * Determine CSV delimiter based on regional settings and optional query parameter
     *
     * In Arabic RTL / Saudi Arabia and European locales, Excel uses semicolon (;)
     * as the list separator (sList in Windows Regional Settings). Defaults to ';'
     * for seamless double-click opening in Excel, while supporting explicit comma via ?delimiter=comma.
     *
     * @return string
     */
    protected function get_csv_delimiter()
    {
        $delim = $this->input->get('delimiter', TRUE);
        if ($delim === 'comma' || $delim === ',') {
            return ',';
        }
        return ';';
    }

    /**
     * Stream CSV file directly to client with UTF-8 BOM for Arabic Excel compatibility
     *
     * @param string $filename
     * @param array $headers
     * @param array $rows
     * @param string|null $delimiter Semicolon by default for Excel regional compatibility
     * @return void
     */
    protected function output_csv($filename, array $headers, array $rows, $delimiter = NULL)
    {
        if ($delimiter === NULL) {
            $delimiter = $this->get_csv_delimiter();
        }

        // Discard any previous buffer to avoid corrupting CSV
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        // Output UTF-8 BOM so Microsoft Excel renders Arabic text flawlessly
        fwrite($out, "\xEF\xBB\xBF");

        // Write sanitized headers
        $clean_headers = array();
        foreach ($headers as $h) {
            $clean_headers[] = self::sanitize_csv_cell($h);
        }
        fputcsv($out, $clean_headers, $delimiter, '"', "\\");

        // Write sanitized rows
        foreach ($rows as $row) {
            $clean_row = array();
            foreach ($row as $cell) {
                $clean_row[] = self::sanitize_csv_cell($cell);
            }
            fputcsv($out, $clean_row, $delimiter, '"', "\\");
        }

        fclose($out);
        exit;
    }
}
