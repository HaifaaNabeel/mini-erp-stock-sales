<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Warehouses Controller
 *
 * Admin methods extend Admin_Controller (enforced in constructor guard).
 * Warehouse/Sales users may access my() which is Auth_Controller protected.
 */
class Warehouses extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Warehouse_model');
        $this->load->model('User_warehouse_model');
        $this->load->library('form_validation');
    }

    /**
     * Enforce admin-only access for management actions
     *
     * @return void
     */
    private function _require_admin()
    {
        if (!$this->is_admin()) {
            $this->session->set_flashdata('error', 'ليس لديك الصلاحية الكافية للوصول إلى هذه الصفحة.');
            redirect('dashboard');
        }
    }

    /**
     * List all warehouses (Admin only)
     *
     * @return void
     */
    public function index()
    {
        $this->_require_admin();

        $data['page_title'] = 'إدارة المستودعات';

        $search = $this->input->get('search', TRUE);
        $status = $this->input->get('status', TRUE);
        $search = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;
        $status = ($status !== NULL && $status !== '') ? (int)$status : NULL;

        $per_page   = $this->get_per_page_limit(10);
        $total_rows = $this->Warehouse_model->count_warehouses($search, $status);
        $pagination = $this->setup_pagination('warehouses', $total_rows, $per_page);

        $data['warehouses']      = $this->Warehouse_model->get_warehouses_paged($per_page, $pagination['offset'], $search, $status);
        $data['pagination']      = $pagination;
        $data['current_search']  = $search;
        $data['current_status']  = $status;

        $this->render_view('warehouses/index', $data);
    }

    /**
     * My assigned warehouses (Warehouse & Sales users)
     *
     * @return void
     */
    public function my()
    {
        if ($this->is_admin()) {
            redirect('warehouses');
            return;
        }

        $data['page_title'] = 'مستودعاتي';
        $data['warehouses'] = $this->User_warehouse_model->get_warehouses_by_user($this->current_user->id);
        $this->render_view('warehouses/my', $data);
    }

    /**
     * Create a new warehouse
     *
     * @return void
     */
    public function create()
    {
        $this->_require_admin();
        $data['page_title'] = 'إضافة مستودع جديد';

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('name', 'اسم المستودع', 'required|trim|max_length[150]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 150 حرفاً.',
            ));
            $this->form_validation->set_rules('code', 'رمز المستودع (Code)', 'required|trim|max_length[50]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 50 حرفاً.',
            ));
            $this->form_validation->set_rules('location', 'الموقع / العنوان', 'trim|max_length[255]', array(
                'max_length' => 'يجب ألا يزيد %s عن 255 حرفاً.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $code = $this->Warehouse_model->normalize_code($this->input->post('code', TRUE));

                if ($this->Warehouse_model->code_exists($code)) {
                    $this->session->set_flashdata('error', 'رمز المستودع (Code) مسجل مسبقاً، يرجى اختيار رمز آخر.');
                    $this->render_view('warehouses/create', $data);
                    return;
                }

                $warehouse_data = array(
                    'name'      => trim($this->input->post('name', TRUE)),
                    'code'      => $code,
                    'location'  => $this->input->post('location', TRUE),
                    'is_active' => $this->input->post('is_active') ? 1 : 0,
                );

                $this->Warehouse_model->create($warehouse_data);
                $this->session->set_flashdata('success', 'تم إنشاء المستودع بنجاح.');
                redirect('warehouses');
                return;
            }
        }

        $this->render_view('warehouses/create', $data);
    }

    /**
     * Edit existing warehouse
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        $this->_require_admin();
        $warehouse = $this->Warehouse_model->get_by_id((int)$id);
        if (!$warehouse) {
            $this->session->set_flashdata('error', 'المستودع المطلوب غير موجود.');
            redirect('warehouses');
            return;
        }

        $data['page_title'] = 'تعديل المستودع: ' . html_escape($warehouse->name);
        $data['warehouse']  = $warehouse;

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('name', 'اسم المستودع', 'required|trim|max_length[150]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 150 حرفاً.',
            ));
            $this->form_validation->set_rules('code', 'رمز المستودع (Code)', 'required|trim|max_length[50]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 50 حرفاً.',
            ));
            $this->form_validation->set_rules('location', 'الموقع / العنوان', 'trim|max_length[255]', array(
                'max_length' => 'يجب ألا يزيد %s عن 255 حرفاً.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $code = $this->Warehouse_model->normalize_code($this->input->post('code', TRUE));

                if ($this->Warehouse_model->code_exists($code, $warehouse->id)) {
                    $this->session->set_flashdata('error', 'رمز المستودع (Code) مسجل مسبقاً لمستودع آخر.');
                    $this->render_view('warehouses/edit', $data);
                    return;
                }

                $update_data = array(
                    'name'      => trim($this->input->post('name', TRUE)),
                    'code'      => $code,
                    'location'  => $this->input->post('location', TRUE),
                    'is_active' => $this->input->post('is_active') ? 1 : 0,
                );

                $this->Warehouse_model->update($warehouse->id, $update_data);
                $this->session->set_flashdata('success', 'تم تحديث بيانات المستودع بنجاح.');
                redirect('warehouses');
                return;
            }
        }

        $this->render_view('warehouses/edit', $data);
    }

    /**
     * Toggle warehouse active/inactive status (Soft toggle)
     *
     * @param int $id
     * @return void
     */
    public function toggle_status($id)
    {
        $this->_require_admin();
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }

        $warehouse = $this->Warehouse_model->get_by_id((int)$id);
        if (!$warehouse) {
            $this->session->set_flashdata('error', 'المستودع المطلوب غير موجود.');
            redirect('warehouses');
            return;
        }

        $this->Warehouse_model->toggle_status($warehouse->id);
        $new_status_text = ((int)$warehouse->is_active === 1) ? 'تعطيل' : 'تفعيل';
        $this->session->set_flashdata('success', "تم {$new_status_text} المستودع بنجاح.");
        redirect('warehouses');
    }

    /**
     * Manage user assignments for this warehouse
     *
     * @param int $id
     * @return void
     */
    public function assignments($id)
    {
        $this->_require_admin();
        $warehouse = $this->Warehouse_model->get_by_id((int)$id);
        if (!$warehouse) {
            $this->session->set_flashdata('error', 'المستودع المطلوب غير موجود.');
            redirect('warehouses');
            return;
        }

        $data['page_title']     = 'ربط المستخدمين بالمستودع: ' . html_escape($warehouse->name);
        $data['warehouse']      = $warehouse;
        $data['eligible_users'] = $this->User_warehouse_model->get_eligible_users();
        $assigned_users         = $this->User_warehouse_model->get_users_by_warehouse($warehouse->id);
        $data['assigned_user_ids'] = array_map(function($u) { return (int)$u->id; }, $assigned_users);

        if ($this->input->method() === 'post') {
            $submitted_user_ids = $this->input->post('user_ids');
            if (!is_array($submitted_user_ids)) {
                $submitted_user_ids = array();
            }

            $this->User_warehouse_model->sync_warehouse_users($warehouse->id, $submitted_user_ids);
            $this->session->set_flashdata('success', 'تم تحديث ارتباطات المستخدمين بالمستودع بنجاح.');
            redirect('warehouses');
            return;
        }

        $this->render_view('warehouses/assignments', $data);
    }
}

