<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stock_movements Controller — F06 Stock Movements
 *
 * Implements Stock In, Stock Adjustment, and Movements History.
 * Role permissions:
 * - Admin: Full access (all warehouses, view, in, adjustment).
 * - Warehouse: Scoped access to assigned warehouses (view, in, adjustment).
 * - Sales: Read-only access to movements history (scoped to assigned warehouses).
 */
class Stock_movements extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Stock_movement_model');
        $this->load->model('Stock_model');
        $this->load->model('Warehouse_model');
        $this->load->model('Product_model');
        $this->load->model('User_warehouse_model');
        $this->load->library('form_validation');
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
     * Verify whether current user is authorized for a specific warehouse
     *
     * @param int $warehouse_id
     * @return bool
     */
    protected function check_warehouse_access($warehouse_id)
    {
        $wid = (int)$warehouse_id;
        if ($wid <= 0) {
            return FALSE;
        }

        if ($this->is_admin()) {
            return TRUE;
        }

        return $this->User_warehouse_model->is_assigned($this->current_user->id, $wid);
    }

    /**
     * Stock Movements History Index
     * Accessible by Admin, Warehouse, and Sales roles.
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'سجل حركات المخزون';

        $search        = $this->input->get('search', TRUE);
        $warehouse_id  = $this->input->get('warehouse_id', TRUE);
        $movement_type = $this->input->get('movement_type', TRUE);
        $start_date    = $this->input->get('start_date', TRUE);
        $end_date      = $this->input->get('end_date', TRUE);

        $search        = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;
        $warehouse_id  = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;
        $movement_type = in_array($movement_type, array('in', 'adjustment'), true) ? $movement_type : NULL;
        $start_date    = ($start_date !== NULL && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($start_date))) ? trim($start_date) : NULL;
        $end_date      = ($end_date !== NULL && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($end_date))) ? trim($end_date) : NULL;

        $user_scope = $this->get_user_warehouse_scope();

        // Enforce warehouse scoping for non-admin
        if ($warehouse_id !== NULL && $user_scope !== NULL) {
            if (!in_array($warehouse_id, $user_scope, true)) {
                $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى المستودع المحدد.');
                redirect('stock/movements');
                return;
            }
        }

        // Available warehouses for filter dropdown
        if ($this->is_admin()) {
            $data['warehouses'] = $this->Warehouse_model->get_all(TRUE);
        } else {
            $data['warehouses'] = $this->User_warehouse_model->get_warehouses_by_user($this->current_user->id);
        }

        $per_page   = $this->get_per_page_limit(10);
        $total_rows = $this->Stock_movement_model->count_movements($search, $warehouse_id, $movement_type, $start_date, $end_date, $user_scope);

        if ($total_rows === FALSE) {
            $total_rows = 0;
            $data['movements'] = array();
            $this->session->set_flashdata('error', 'تعذر استرجاع حركات المخزون من قاعدة البيانات.');
        }

        $pagination = $this->setup_pagination('stock/movements', $total_rows, $per_page);

        if ($total_rows > 0) {
            $movements = $this->Stock_movement_model->get_movements_paged($per_page, $pagination['offset'], $search, $warehouse_id, $movement_type, $start_date, $end_date, $user_scope);
            $data['movements'] = ($movements !== FALSE) ? $movements : array();
        } else {
            $data['movements'] = array();
        }

        $data['pagination']            = $pagination;
        $data['total_count']           = $total_rows;
        $data['current_search']        = $search;
        $data['current_warehouse']     = $warehouse_id;
        $data['current_movement_type'] = $movement_type;
        $data['current_start_date']    = $start_date;
        $data['current_end_date']      = $end_date;
        $data['user_scope']            = $user_scope;
        $data['can_manage_stock']      = ($this->is_admin() || $this->is_warehouse());

        $this->render_view('stock_movements/index', $data);
    }

    /**
     * Create Stock In Movement
     * Accessible by Admin and Warehouse roles only.
     *
     * @return void
     */
    public function create_in()
    {
        // Enforce role authorization
        if (!$this->is_admin() && !$this->is_warehouse()) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية لتسجيل إدخال مخزون.');
            redirect('stock/movements');
            return;
        }

        $data['page_title'] = 'إدخال مخزون جديد (Stock In)';

        // Available active warehouses for selection
        if ($this->is_admin()) {
            $data['warehouses'] = $this->Warehouse_model->get_all(FALSE);
        } else {
            $assigned = $this->User_warehouse_model->get_warehouses_by_user($this->current_user->id);
            $data['warehouses'] = array_values(array_filter($assigned, function($w) {
                return (int)$w->is_active === 1;
            }));
        }

        if (empty($data['warehouses'])) {
            $this->session->set_flashdata('error', 'لا توجد مستودعات نشطة مصرح لك بالعمل عليها.');
            redirect('stock/movements');
            return;
        }

        // Active products
        $data['products'] = $this->db->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get('products')
            ->result();

        if (empty($data['products'])) {
            $this->session->set_flashdata('error', 'لا توجد منتجات نشطة في النظام لإدخال مخزون لها.');
            redirect('stock/movements');
            return;
        }

        // Fresh idempotency key for form rendering
        $data['idempotency_key'] = bin2hex(random_bytes(16));

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('warehouse_id', 'المستودع', 'trim|required|is_natural_no_zero');
            $this->form_validation->set_rules('product_id', 'المنتج', 'trim|required|is_natural_no_zero');
            $this->form_validation->set_rules('quantity', 'الكمية المضافة', 'trim|required|is_natural_no_zero');
            $this->form_validation->set_rules('idempotency_key', 'رمز العملية', 'trim|required|exact_length[32]|alpha_numeric');
            $this->form_validation->set_rules('reason', 'البيان / ملاحظات', 'trim|max_length[255]');

            if ($this->form_validation->run() === TRUE) {
                $warehouse_id    = (int)$this->input->post('warehouse_id');
                $product_id      = (int)$this->input->post('product_id');
                $quantity        = (int)$this->input->post('quantity');
                $idempotency_key = strtolower(trim($this->input->post('idempotency_key')));
                $reason          = $this->input->post('reason', TRUE);

                // 1. Authorize warehouse access server-side
                if (!$this->check_warehouse_access($warehouse_id)) {
                    $this->session->set_flashdata('error', 'ليس لديك صلاحية على المستودع المحدد.');
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                    $this->render_view('stock_movements/create_in', $data);
                    return;
                }

                // 2. Validate warehouse is active
                $wh = $this->db->where('id', $warehouse_id)->where('is_active', 1)->get('warehouses')->row();
                if (!$wh) {
                    $this->session->set_flashdata('error', 'المستودع المحدد غير نشط أو غير موجود.');
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                    $this->render_view('stock_movements/create_in', $data);
                    return;
                }

                // 3. Validate product is active
                $prod = $this->db->where('id', $product_id)->where('is_active', 1)->get('products')->row();
                if (!$prod) {
                    $this->session->set_flashdata('error', 'المنتج المحدد غير نشط أو غير موجود.');
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                    $this->render_view('stock_movements/create_in', $data);
                    return;
                }

                // 4. Record stock in atomically via model
                $result = $this->Stock_movement_model->record_stock_in(
                    $warehouse_id,
                    $product_id,
                    $quantity,
                    $reason,
                    $idempotency_key,
                    $this->current_user->id
                );

                if ($result['success'] === TRUE) {
                    if (!empty($result['is_replay'])) {
                        $this->session->set_flashdata('info', 'تم استلام هذا الإدخال مسبقاً بنفس المفتاح الفريد، ولم يتم تكرار تحديث الرصيد.');
                    } else {
                        $this->session->set_flashdata('success', 'تم إدخال المخزون بنجاح. الرصيد السابق: ' . $result['before_quantity'] . '، الرصيد الجديد: ' . $result['after_quantity'] . '.');
                    }
                    redirect('stock/movements');
                    return;
                } else {
                    $this->session->set_flashdata('error', $result['error']);
                    // Generate a new idempotency key for retry
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                }
            }
        }

        $this->render_view('stock_movements/create_in', $data);
    }

    /**
     * Create Stock Adjustment Movement
     * Accessible by Admin and Warehouse roles only.
     *
     * @return void
     */
    public function create_adjustment()
    {
        // Enforce role authorization
        if (!$this->is_admin() && !$this->is_warehouse()) {
            $this->session->set_flashdata('error', 'ليس لديك صلاحية لتسجيل تسوية مخزون.');
            redirect('stock/movements');
            return;
        }

        $data['page_title'] = 'تسوية مخزون (Stock Adjustment)';

        // Available active warehouses for selection
        if ($this->is_admin()) {
            $data['warehouses'] = $this->Warehouse_model->get_all(FALSE);
        } else {
            $assigned = $this->User_warehouse_model->get_warehouses_by_user($this->current_user->id);
            $data['warehouses'] = array_values(array_filter($assigned, function($w) {
                return (int)$w->is_active === 1;
            }));
        }

        if (empty($data['warehouses'])) {
            $this->session->set_flashdata('error', 'لا توجد مستودعات نشطة مصرح لك بالعمل عليها.');
            redirect('stock/movements');
            return;
        }

        // Active products
        $data['products'] = $this->db->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get('products')
            ->result();

        if (empty($data['products'])) {
            $this->session->set_flashdata('error', 'لا توجد منتجات نشطة في النظام لتسوية مخزونها.');
            redirect('stock/movements');
            return;
        }

        // Fresh idempotency key for form rendering
        $data['idempotency_key'] = bin2hex(random_bytes(16));

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('warehouse_id', 'المستودع', 'trim|required|is_natural_no_zero');
            $this->form_validation->set_rules('product_id', 'المنتج', 'trim|required|is_natural_no_zero');
            $this->form_validation->set_rules('adjustment_type', 'نوع التسوية', 'trim|required|in_list[add,subtract]');
            $this->form_validation->set_rules('quantity', 'كمية التسوية', 'trim|required|is_natural_no_zero');
            $this->form_validation->set_rules('reason', 'سبب التسوية', 'trim|required|min_length[5]|max_length[255]');
            $this->form_validation->set_rules('idempotency_key', 'رمز العملية', 'trim|required|exact_length[32]|alpha_numeric');

            if ($this->form_validation->run() === TRUE) {
                $warehouse_id    = (int)$this->input->post('warehouse_id');
                $product_id      = (int)$this->input->post('product_id');
                $adjustment_type = $this->input->post('adjustment_type', TRUE);
                $quantity        = (int)$this->input->post('quantity');
                $idempotency_key = strtolower(trim($this->input->post('idempotency_key')));
                $reason          = $this->input->post('reason', TRUE);

                // 1. Authorize warehouse access server-side
                if (!$this->check_warehouse_access($warehouse_id)) {
                    $this->session->set_flashdata('error', 'ليس لديك صلاحية على المستودع المحدد.');
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                    $this->render_view('stock_movements/create_adjustment', $data);
                    return;
                }

                // 2. Validate warehouse is active
                $wh = $this->db->where('id', $warehouse_id)->where('is_active', 1)->get('warehouses')->row();
                if (!$wh) {
                    $this->session->set_flashdata('error', 'المستودع المحدد غير نشط أو غير موجود.');
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                    $this->render_view('stock_movements/create_adjustment', $data);
                    return;
                }

                // 3. Validate product is active
                $prod = $this->db->where('id', $product_id)->where('is_active', 1)->get('products')->row();
                if (!$prod) {
                    $this->session->set_flashdata('error', 'المنتج المحدد غير نشط أو غير موجود.');
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                    $this->render_view('stock_movements/create_adjustment', $data);
                    return;
                }

                // 4. Compute signed quantity delta
                $quantity_delta = ($adjustment_type === 'add') ? $quantity : -$quantity;

                // 5. Record adjustment atomically via model
                $result = $this->Stock_movement_model->record_adjustment(
                    $warehouse_id,
                    $product_id,
                    $quantity_delta,
                    $reason,
                    $idempotency_key,
                    $this->current_user->id
                );

                if ($result['success'] === TRUE) {
                    if (!empty($result['is_replay'])) {
                        $this->session->set_flashdata('info', 'تم استلام هذه التسوية مسبقاً بنفس المفتاح الفريد، ولم يتم تكرار تعديل الرصيد.');
                    } else {
                        $this->session->set_flashdata('success', 'تم تسجيل تسوية المخزون بنجاح. الرصيد السابق: ' . $result['before_quantity'] . '، الرصيد الجديد: ' . $result['after_quantity'] . '.');
                    }
                    redirect('stock/movements');
                    return;
                } else {
                    $this->session->set_flashdata('error', $result['error']);
                    // Generate a new idempotency key for retry
                    $data['idempotency_key'] = bin2hex(random_bytes(16));
                }
            }
        }

        $this->render_view('stock_movements/create_adjustment', $data);
    }

    /**
     * AJAX endpoint to fetch current stock for a warehouse and product
     * Accessible by Admin and Warehouse roles.
     *
     * @return void
     */
    public function ajax_get_stock()
    {
        if (!$this->is_admin() && !$this->is_warehouse()) {
            $this->json_response(array('success' => FALSE, 'error' => 'غير مصرح.'), 403);
            return;
        }

        $warehouse_id = (int)$this->input->get_post('warehouse_id');
        $product_id   = (int)$this->input->get_post('product_id');

        if ($warehouse_id <= 0 || $product_id <= 0) {
            $this->json_response(array('success' => FALSE, 'error' => 'معرفات غير صالحة.'), 400);
            return;
        }

        if (!$this->check_warehouse_access($warehouse_id)) {
            $this->json_response(array('success' => FALSE, 'error' => 'ليس لديك صلاحية على هذا المستودع.'), 403);
            return;
        }

        $current_quantity = $this->Stock_model->get_quantity($warehouse_id, $product_id);

        $this->json_response(array(
            'success'  => TRUE,
            'quantity' => $current_quantity,
        ));
    }
}

