<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sales_invoices Controller — F07 Sales Invoices Management
 *
 * Implements Invoice listing, multi-criteria filtering, pagination,
 * and comprehensive invoice detail view.
 *
 * Role permissions:
 * - Admin: Full access to all invoices across all warehouses.
 * - Sales: Scoped access strictly to own created invoices within assigned warehouses.
 * - Warehouse: Forbidden.
 *
 * PHP Compatibility: 7.4+
 */
class Sales_invoices extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role(array('admin', 'sales'));
        $this->load->model('Sales_invoice_model');
        $this->load->model('Customer_model');
        $this->load->model('Warehouse_model');
        $this->load->model('User_warehouse_model');
    }

    /**
     * Get allowed warehouse IDs for current user
     * - Admin: NULL (all warehouses)
     * - Sales: Array of assigned warehouse IDs
     *
     * @return array|null
     */
    protected function get_user_warehouse_ids()
    {
        if ($this->is_admin()) {
            return NULL;
        }

        return $this->User_warehouse_model->get_assigned_warehouse_ids($this->current_user->id);
    }

    /**
     * List sales invoices with search, filters, and pagination
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'فواتير المبيعات';

        // Extract and sanitize query filters
        $filters = array(
            'search'       => $this->input->get('search', TRUE),
            'customer_id'  => $this->input->get('customer_id', TRUE),
            'warehouse_id' => $this->input->get('warehouse_id', TRUE),
            'status'       => $this->input->get('status', TRUE),
            'start_date'   => $this->input->get('start_date', TRUE),
            'end_date'     => $this->input->get('end_date', TRUE),
        );

        // Normalize filters
        $filters['search']       = ($filters['search'] !== NULL && trim($filters['search']) !== '') ? trim($filters['search']) : NULL;
        $filters['customer_id']  = ($filters['customer_id'] !== NULL && $filters['customer_id'] !== '') ? (int)$filters['customer_id'] : NULL;
        $filters['warehouse_id'] = ($filters['warehouse_id'] !== NULL && $filters['warehouse_id'] !== '') ? (int)$filters['warehouse_id'] : NULL;
        $filters['status']       = (in_array($filters['status'], array('active', 'cancelled'), TRUE)) ? $filters['status'] : NULL;
        $filters['start_date']   = (!empty($filters['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['start_date'])) ? $filters['start_date'] : NULL;
        $filters['end_date']     = (!empty($filters['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['end_date'])) ? $filters['end_date'] : NULL;

        $user_id   = $this->current_user->id;
        $role_name = $this->current_user->role_name;
        $allowed_warehouse_ids = $this->get_user_warehouse_ids();

        $per_page   = $this->get_per_page_limit(10);
        $total_rows = $this->Sales_invoice_model->count_invoices($filters, $user_id, $role_name, $allowed_warehouse_ids);
        if ($total_rows === FALSE) {
            $total_rows = 0;
        }

        $pagination = $this->setup_pagination('sales/invoices', $total_rows, $per_page);
        $invoices   = $this->Sales_invoice_model->get_invoices_paged($per_page, $pagination['offset'], $filters, $user_id, $role_name, $allowed_warehouse_ids);

        // Load reference data for filter dropdowns
        $data['customers'] = $this->Customer_model->get_all(TRUE);

        if ($this->is_admin()) {
            $data['warehouses'] = $this->Warehouse_model->get_all(TRUE);
        } else {
            $data['warehouses'] = $this->User_warehouse_model->get_warehouses_by_user($user_id);
        }

        $data['invoices']   = ($invoices !== FALSE) ? $invoices : array();
        $data['pagination'] = $pagination;
        $data['filters']    = $filters;

        $this->render_view('sales_invoices/index', $data);
    }

    /**
     * View sales invoice details
     *
     * @param int $id
     * @return void
     */
    public function view($id)
    {
        $valid_id = filter_var($id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1)
        ));

        if ($valid_id === FALSE) {
            $this->session->set_flashdata('error', 'معرف الفاتورة غير صالح.');
            redirect('sales/invoices');
            return;
        }

        $invoice = $this->Sales_invoice_model->get_by_id($valid_id);
        if (!$invoice) {
            $this->session->set_flashdata('error', 'الفاتورة المطلوبة غير موجودة.');
            redirect('sales/invoices');
            return;
        }

        // Enforce Server-Side Authorization for Sales role
        if ($this->is_sales()) {
            // Check 1: User must be the creator of this invoice
            if ((int)$invoice->created_by !== (int)$this->current_user->id) {
                $this->session->set_flashdata('error', 'ليس لديك صلاحية لعرض هذه الفاتورة.');
                redirect('sales/invoices');
                return;
            }

            // Check 2: Warehouse must be currently assigned to this user
            if (!$this->User_warehouse_model->is_assigned($this->current_user->id, $invoice->warehouse_id)) {
                $this->session->set_flashdata('error', 'ليس لديك صلاحية على المستودع المرتبط بهذه الفاتورة.');
                redirect('sales/invoices');
                return;
            }
        }

        $items = $this->Sales_invoice_model->get_items_by_invoice_id($invoice->id);

        $data['page_title'] = 'تفاصيل الفاتورة: ' . html_escape($invoice->invoice_number);
        $data['invoice']    = $invoice;
        $data['items']      = ($items !== FALSE) ? $items : array();

        $this->render_view('sales_invoices/view', $data);
    }

    /**
     * Create new sales invoice interface and POST handler
     *
     * @return void
     */
    public function create()
    {
        $user_id   = $this->current_user->id;
        $role_name = $this->current_user->role_name;

        // Ensure user is authorized for creating sales invoices
        $this->require_role(array('admin', 'sales'));

        // Handle POST submission
        if ($this->input->method() === 'post') {
            $customer_id         = (int)$this->input->post('customer_id');
            $warehouse_id        = (int)$this->input->post('warehouse_id');
            $invoice_date        = $this->input->post('invoice_date', TRUE);
            $discount_percentage = $this->input->post('discount_percentage', TRUE);
            $notes               = $this->input->post('notes', TRUE);
            $idempotency_key     = $this->input->post('idempotency_key', TRUE);

            // Extract items: support either items array or parallel product_id / quantity arrays
            $raw_items   = $this->input->post('items');
            $product_ids = $this->input->post('product_id');
            $quantities  = $this->input->post('quantity');

            $items = array();
            if (is_array($raw_items) && !empty($raw_items)) {
                foreach ($raw_items as $item) {
                    if (isset($item['product_id']) && isset($item['quantity'])) {
                        $items[] = array(
                            'product_id' => (int)$item['product_id'],
                            'quantity'   => (int)$item['quantity'],
                        );
                    }
                }
            } elseif (is_array($product_ids) && is_array($quantities)) {
                foreach ($product_ids as $idx => $pid) {
                    $items[] = array(
                        'product_id' => (int)$pid,
                        'quantity'   => isset($quantities[$idx]) ? (int)$quantities[$idx] : 0,
                    );
                }
            }

            // Quick preliminary validations on controller level before calling model
            if ($customer_id <= 0) {
                $this->session->set_flashdata('error', 'يرجى اختيار العميل.');
            } elseif ($warehouse_id <= 0) {
                $this->session->set_flashdata('error', 'يرجى اختيار المستودع.');
            } elseif (empty($items)) {
                $this->session->set_flashdata('error', 'لا يمكن حفظ فاتورة فارغة. يجب إضافة صنف واحد على الأقل.');
            } else {
                $invoice_data = array(
                    'customer_id'         => $customer_id,
                    'warehouse_id'        => $warehouse_id,
                    'invoice_date'        => (!empty($invoice_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice_date)) ? $invoice_date : date('Y-m-d'),
                    'discount_percentage' => ($discount_percentage !== NULL && $discount_percentage !== '') ? (float)$discount_percentage : 0.00,
                    'notes'               => ($notes !== NULL && trim($notes) !== '') ? trim($notes) : NULL,
                    'idempotency_key'     => trim((string)$idempotency_key),
                );

                $result = $this->Sales_invoice_model->create_invoice($invoice_data, $items, $this->current_user);

                if ($result['success']) {
                    if (!empty($result['is_replay'])) {
                        $this->session->set_flashdata('info', 'تم استلام هذا الطلب مسبقاً بنفس المفتاح الفريد، وتم عرض الفاتورة المسجلة.');
                    } else {
                        $this->session->set_flashdata('success', 'تم إنشاء وتوثيق فاتورة المبيعات بنجاح برقم: ' . $result['invoice_number']);
                    }
                    redirect('sales/invoices/view/' . $result['invoice_id']);
                    return;
                } else {
                    $this->session->set_flashdata('error', $result['error']);
                }
            }
        }

        // Setup form view data
        $data['page_title']      = 'إنشاء فاتورة مبيعات جديدة';
        $data['default_date']    = date('Y-m-d');
        $data['idempotency_key'] = bin2hex(random_bytes(16));

        // Active customers only
        $data['customers'] = $this->Customer_model->get_all(FALSE);

        // Allowed active warehouses based on role
        if ($this->is_admin()) {
            $data['warehouses'] = $this->Warehouse_model->get_all(FALSE);
        } else {
            $user_warehouses = $this->User_warehouse_model->get_warehouses_by_user($user_id);
            $data['warehouses'] = array_values(array_filter($user_warehouses, function($w) {
                return (int)$w->is_active === 1;
            }));
        }

        $this->render_view('sales_invoices/create', $data);
    }

    /**
     * AJAX endpoint to fetch active products with current stock for a warehouse
     * Accessible by Admin and Sales roles only.
     *
     * @return void
     */
    public function ajax_get_products()
    {
        if (!$this->is_admin() && !$this->is_sales()) {
            $this->json_response(array('success' => FALSE, 'error' => 'غير مصرح بالوصول.'), 403);
            return;
        }

        $warehouse_id = (int)$this->input->get_post('warehouse_id');
        if ($warehouse_id <= 0) {
            $this->json_response(array('success' => FALSE, 'error' => 'معرف المستودع غير صالح.'), 400);
            return;
        }

        // Verify warehouse exists and is active
        $warehouse = $this->Warehouse_model->get_by_id($warehouse_id);
        if (!$warehouse || (int)$warehouse->is_active !== 1) {
            $this->json_response(array('success' => FALSE, 'error' => 'المستودع المحدد غير موجود أو غير نشط.'), 400);
            return;
        }

        // Authorization scoping for Sales user
        if ($this->is_sales()) {
            if (!$this->User_warehouse_model->is_assigned($this->current_user->id, $warehouse_id)) {
                $this->json_response(array('success' => FALSE, 'error' => 'ليس لديك صلاحية على هذا المستودع.'), 403);
                return;
            }
        }

        $products = $this->Sales_invoice_model->get_products_with_stock($warehouse_id);

        $this->json_response(array(
            'success'  => TRUE,
            'products' => $products,
        ));
    }

    /**
     * Print dedicated sales invoice view
     *
     * Clean, read-only layout optimized for printing (A4)
     * Free of navigation bars and interactive chrome.
     *
     * @param int $id
     * @return void
     */
    public function print_invoice($id)
    {
        $valid_id = filter_var($id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1)
        ));

        if ($valid_id === FALSE) {
            $this->session->set_flashdata('error', 'معرف الفاتورة غير صالح.');
            redirect('sales/invoices');
            return;
        }

        $invoice = $this->Sales_invoice_model->get_by_id($valid_id);
        if (!$invoice) {
            $this->session->set_flashdata('error', 'الفاتورة المطلوبة غير موجودة.');
            redirect('sales/invoices');
            return;
        }

        // Role scoping for Sales role
        if ($this->is_sales()) {
            if ((int)$invoice->created_by !== (int)$this->current_user->id) {
                $this->session->set_flashdata('error', 'ليس لديك صلاحية لطباعة هذه الفاتورة.');
                redirect('sales/invoices');
                return;
            }

            if (!$this->User_warehouse_model->is_assigned($this->current_user->id, $invoice->warehouse_id)) {
                $this->session->set_flashdata('error', 'ليس لديك صلاحية على المستودع المرتبط بهذه الفاتورة.');
                redirect('sales/invoices');
                return;
            }
        }

        $items = $this->Sales_invoice_model->get_items_by_invoice_id($invoice->id);

        $data['page_title'] = 'طباعة فاتورة مبيعات: ' . html_escape($invoice->invoice_number);
        $data['invoice']    = $invoice;
        $data['items']      = ($items !== FALSE) ? $items : array();

        // Render standalone view without layout chrome
        $this->render_view('sales_invoices/print', $data, FALSE);
    }
}

