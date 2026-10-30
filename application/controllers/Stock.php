<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stock Controller — F05 Warehouse Stock Management
 *
 * Scoped access for Admin, Warehouse, and Sales roles.
 */
class Stock extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Stock_model');
        $this->load->model('Warehouse_model');
        $this->load->model('User_warehouse_model');
        $this->load->model('Product_model');
        $this->load->library('form_validation');
    }

    /**
     * Get authorized warehouse IDs for current user
     * - Admin: NULL (all warehouses)
     * - Warehouse / Sales: array of assigned warehouse IDs
     *
     * @return array|null
     */
    protected function get_user_warehouse_scope()
    {
        if ($this->is_admin()) {
            return NULL; // All warehouses
        }

        return $this->User_warehouse_model->get_assigned_warehouse_ids($this->current_user->id);
    }

    /**
     * List warehouse stock with search, warehouse filter, and pagination
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'أرصدة المخزون بالمستودعات';

        $search       = $this->input->get('search', TRUE);
        $warehouse_id = $this->input->get('warehouse_id', TRUE);

        $search       = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;
        $warehouse_id = ($warehouse_id !== NULL && (int)$warehouse_id > 0) ? (int)$warehouse_id : NULL;

        $user_scope = $this->get_user_warehouse_scope();

        // Security: If non-admin specifies a warehouse_id, ensure they are assigned to it
        if ($warehouse_id !== NULL && $user_scope !== NULL) {
            if (!in_array($warehouse_id, $user_scope, true)) {
                $this->session->set_flashdata('error', 'ليس لديك صلاحية للوصول إلى المستودع المحدد.');
                redirect('stock');
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
        $total_rows = $this->Stock_model->count_stock($search, $warehouse_id, $user_scope);
        $pagination = $this->setup_pagination('stock', $total_rows, $per_page);

        $data['stock_items']        = $this->Stock_model->get_stock_paged($per_page, $pagination['offset'], $search, $warehouse_id, $user_scope);
        $data['pagination']         = $pagination;
        $data['total_count']        = $total_rows;
        $data['current_search']     = $search;
        $data['current_warehouse']  = $warehouse_id;
        $data['user_scope']         = $user_scope;
        $data['can_edit_reorder']   = ($this->is_admin() || $this->is_warehouse());

        $this->render_view('stock/index', $data);
    }

    /**
     * Update reorder level for product in warehouse (Admin & Warehouse roles only)
     *
     * @param int $id warehouse_stock record id
     * @return void
     */
    public function edit_reorder($id)
    {
        // Sales role cannot edit reorder level
        if (!$this->is_admin() && !$this->is_warehouse()) {
            $this->session->set_flashdata('error', 'ليس لديك الصلاحية لتعديل حد إعادة الطلب.');
            redirect('stock');
            return;
        }

        $stock = $this->db->select('ws.*, p.name AS product_name, p.sku AS product_sku, w.name AS warehouse_name')
            ->from('warehouse_stock ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->where('ws.id', (int)$id)
            ->get()
            ->row();

        if (!$stock) {
            $this->session->set_flashdata('error', 'سجل المخزون المطلوب غير موجود.');
            redirect('stock');
            return;
        }

        // Authorization: Warehouse users can only edit for their assigned warehouses
        $user_scope = $this->get_user_warehouse_scope();
        if ($user_scope !== NULL && !in_array((int)$stock->warehouse_id, $user_scope, true)) {
            $this->session->set_flashdata('error', 'ليس لديك الصلاحية لتعديل بيانات هذا المستودع.');
            redirect('stock');
            return;
        }

        $data['page_title'] = 'تحديد حد إعادة الطلب: ' . html_escape($stock->product_name);
        $data['stock']      = $stock;

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('reorder_level', 'حد إعادة الطلب', 'required|is_natural', array(
                'required'   => 'حقل %s مطلوب.',
                'is_natural' => 'يجب أن يكون %s رقماً صحيحاً غير سالب (0 أو أكبر).',
            ));

            if ($this->form_validation->run() === TRUE) {
                $new_level = (int)$this->input->post('reorder_level');
                $this->Stock_model->update_reorder_level($stock->warehouse_id, $stock->product_id, $new_level);

                $this->session->set_flashdata('success', 'تم تحديث حد إعادة الطلب بنجاح.');
                redirect('stock');
                return;
            }
        }

        $this->render_view('stock/edit_reorder', $data);
    }
}

