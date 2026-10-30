<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
    }

    /**
     * List all users
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'إدارة المستخدمين';

        $search = $this->input->get('search', TRUE);
        $role   = $this->input->get('role', TRUE);
        $status = $this->input->get('status', TRUE);
        $search = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;
        $role   = ($role !== NULL && trim($role) !== '') ? trim($role) : NULL;
        $status = ($status !== NULL && $status !== '') ? (int)$status : NULL;

        $per_page   = $this->get_per_page_limit(10);
        $total_rows = $this->User_model->count_users($search, NULL, $status, $role);
        $pagination = $this->setup_pagination('users', $total_rows, $per_page);

        $data['users']          = $this->User_model->get_users_paged($per_page, $pagination['offset'], $search, NULL, $status, $role);
        $data['roles']          = $this->Role_model->get_all();
        $data['pagination']     = $pagination;
        $data['current_search'] = $search;
        $data['current_role']   = $role;
        $data['current_status'] = $status;

        $this->render_view('users/index', $data);
    }

    /**
     * Create a new user
     *
     * @return void
     */
    public function create()
    {
        $data['page_title'] = 'إضافة مستخدم جديد';
        $data['roles'] = $this->Role_model->get_all();

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('username', 'اسم المستخدم', 'required|trim|min_length[3]|max_length[50]', array(
                'required'   => 'حقل %s مطلوب.',
                'min_length' => 'يجب ألا يقل %s عن 3 أحرف.',
                'max_length' => 'يجب ألا يزيد %s عن 50 حرفاً.',
            ));
            $this->form_validation->set_rules('full_name', 'الاسم الكامل', 'required|trim|max_length[150]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 150 حرفاً.',
            ));
            $this->form_validation->set_rules('email', 'البريد الإلكتروني', 'trim|valid_email|max_length[191]', array(
                'valid_email' => 'البريد الإلكتروني المدخل غير صالح.',
                'max_length'  => 'يجب ألا يزيد %s عن 191 حرفاً.',
            ));
            $this->form_validation->set_rules('password', 'كلمة المرور', 'required|min_length[6]', array(
                'required'   => 'حقل %s مطلوب.',
                'min_length' => 'يجب ألا تقل %s عن 6 أحرف.',
            ));
            $this->form_validation->set_rules('password_confirm', 'تأكيد كلمة المرور', 'required|matches[password]', array(
                'required' => 'حقل %s مطلوب.',
                'matches'  => 'تأكيد كلمة المرور غير متطابق مع كلمة المرور.',
            ));
            $this->form_validation->set_rules('role_id', 'الدور الوظيفي', 'required|is_natural_no_zero', array(
                'required'            => 'يرجى اختيار %s.',
                'is_natural_no_zero' => 'الدور المختار غير صالح.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $username = $this->User_model->normalize_username($this->input->post('username', TRUE));
                $email = $this->User_model->normalize_email($this->input->post('email', TRUE));

                // Check username uniqueness
                if ($this->User_model->username_exists($username)) {
                    $this->session->set_flashdata('error', 'اسم المستخدم مسجل مسبقاً، يرجى اختيار اسم مستخدم آخر.');
                    $this->render_view('users/create', $data);
                    return;
                }

                // Check email uniqueness if provided
                if ($email !== null && $this->User_model->email_exists($email)) {
                    $this->session->set_flashdata('error', 'البريد الإلكتروني مسجل مسبقاً لمستخدم آخر.');
                    $this->render_view('users/create', $data);
                    return;
                }

                $role_id = (int)$this->input->post('role_id');
                $role = $this->Role_model->get_by_id($role_id);
                if (!$role) {
                    $this->session->set_flashdata('error', 'الدور المختار غير موجود.');
                    $this->render_view('users/create', $data);
                    return;
                }

                $user_data = array(
                    'username'  => $username,
                    'full_name' => $this->input->post('full_name', TRUE),
                    'email'     => $email,
                    'password'  => $this->input->post('password'),
                    'role_id'   => $role_id,
                    'is_active' => $this->input->post('is_active') ? 1 : 0,
                );

                $this->User_model->create_user($user_data);
                $this->session->set_flashdata('success', 'تم إنشاء المستخدم بنجاح.');
                redirect('users');
                return;
            }
        }

        $this->render_view('users/create', $data);
    }

    /**
     * Edit existing user
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        $user = $this->User_model->get_by_id((int)$id);
        if (!$user) {
            $this->session->set_flashdata('error', 'المستخدم المطلوب غير موجود.');
            redirect('users');
            return;
        }

        $data['page_title'] = 'تعديل بيانات المستخدم: ' . html_escape($user->username);
        $data['user'] = $user;
        $data['roles'] = $this->Role_model->get_all();

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('full_name', 'الاسم الكامل', 'required|trim|max_length[150]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 150 حرفاً.',
            ));
            $this->form_validation->set_rules('email', 'البريد الإلكتروني', 'trim|valid_email|max_length[191]', array(
                'valid_email' => 'البريد الإلكتروني المدخل غير صالح.',
                'max_length'  => 'يجب ألا يزيد %s عن 191 حرفاً.',
            ));
            $this->form_validation->set_rules('role_id', 'الدور الوظيفي', 'required|is_natural_no_zero', array(
                'required'            => 'يرجى اختيار %s.',
                'is_natural_no_zero' => 'الدور المختار غير صالح.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $email = $this->User_model->normalize_email($this->input->post('email', TRUE));
                $new_role_id = (int)$this->input->post('role_id');
                $new_is_active = $this->input->post('is_active') ? 1 : 0;

                // Validate email uniqueness excluding current user
                if ($email !== null && $this->User_model->email_exists($email, $user->id)) {
                    $this->session->set_flashdata('error', 'البريد الإلكتروني مسجل مسبقاً لمستخدم آخر.');
                    $this->render_view('users/edit', $data);
                    return;
                }

                // Verify valid role
                $new_role = $this->Role_model->get_by_id($new_role_id);
                if (!$new_role) {
                    $this->session->set_flashdata('error', 'الدور المختار غير موجود.');
                    $this->render_view('users/edit', $data);
                    return;
                }

                // Business Rule 1: Admin cannot change their own role
                if ((int)$user->id === (int)$this->current_user->id && $new_role_id !== (int)$user->role_id) {
                    $this->session->set_flashdata('error', 'لا يمكن لمدير النظام تغيير دوره بنفسه.');
                    $this->render_view('users/edit', $data);
                    return;
                }

                // Business Rule 2: Admin cannot deactivate themselves
                if ((int)$user->id === (int)$this->current_user->id && $new_is_active === 0) {
                    $this->session->set_flashdata('error', 'لا يمكن لمدير النظام تعطيل حسابه الخاص.');
                    $this->render_view('users/edit', $data);
                    return;
                }

                // Business Rule 3: Cannot deactivate or demote the last active Admin
                if ($user->role_name === 'admin' && (int)$user->is_active === 1) {
                    $will_deactivate = ($new_is_active === 0);
                    $will_demote = ($new_role->name !== 'admin');

                    if ($will_deactivate || $will_demote) {
                        $active_admins = $this->User_model->count_active_admins();
                        if ($active_admins <= 1) {
                            $this->session->set_flashdata('error', 'لا يمكن تعطيل أو تغيير دور آخر مدير نظام نشط في النظام.');
                            $this->render_view('users/edit', $data);
                            return;
                        }
                    }
                }

                $update_data = array(
                    'full_name' => $this->input->post('full_name', TRUE),
                    'email'     => $email,
                    'role_id'   => $new_role_id,
                    'is_active' => $new_is_active,
                );

                $this->User_model->update_user($user->id, $update_data);
                $this->session->set_flashdata('success', 'تم تحديث بيانات المستخدم بنجاح.');
                redirect('users');
                return;
            }
        }

        $this->render_view('users/edit', $data);
    }

    /**
     * Change user password (admin action)
     *
     * @param int $id
     * @return void
     */
    public function change_password($id)
    {
        $user = $this->User_model->get_by_id((int)$id);
        if (!$user) {
            $this->session->set_flashdata('error', 'المستخدم المطلوب غير موجود.');
            redirect('users');
            return;
        }

        $data['page_title'] = 'تغيير كلمة المرور: ' . html_escape($user->username);
        $data['user'] = $user;

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('new_password', 'كلمة المرور الجديدة', 'required|min_length[6]', array(
                'required'   => 'حقل %s مطلوب.',
                'min_length' => 'يجب ألا تقل %s عن 6 أحرف.',
            ));
            $this->form_validation->set_rules('confirm_password', 'تأكيد كلمة المرور الجديدة', 'required|matches[new_password]', array(
                'required' => 'حقل %s مطلوب.',
                'matches'  => 'تأكيد كلمة المرور غير متطابق مع كلمة المرور الجديدة.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $new_password = $this->input->post('new_password');
                $this->User_model->update_password($user->id, $new_password);
                $this->session->set_flashdata('success', 'تم تغيير كلمة مرور المستخدم بنجاح.');
                redirect('users');
                return;
            }
        }

        $this->render_view('users/change_password', $data);
    }

    /**
     * Toggle user active/inactive status
     *
     * @param int $id
     * @return void
     */
    public function toggle_status($id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }

        $user = $this->User_model->get_by_id((int)$id);
        if (!$user) {
            $this->session->set_flashdata('error', 'المستخدم المطلوب غير موجود.');
            redirect('users');
            return;
        }

        $new_status = ((int)$user->is_active === 1) ? 0 : 1;

        // If deactivating, enforce admin security rules
        if ($new_status === 0) {
            // Business Rule 1: Admin cannot deactivate self
            if ((int)$user->id === (int)$this->current_user->id) {
                $this->session->set_flashdata('error', 'لا يمكن لمدير النظام تعطيل حسابه الخاص.');
                redirect('users');
                return;
            }

            // Business Rule 3: Cannot deactivate last active Admin
            if ($user->role_name === 'admin') {
                $active_admins = $this->User_model->count_active_admins();
                if ($active_admins <= 1) {
                    $this->session->set_flashdata('error', 'لا يمكن تعطيل آخر مدير نظام نشط في النظام.');
                    redirect('users');
                    return;
                }
            }
        }

        $this->User_model->update_user($user->id, array('is_active' => $new_status));
        $status_text = ($new_status === 1) ? 'تفعيل' : 'تعطيل';
        $this->session->set_flashdata('success', "تم {$status_text} المستخدم بنجاح.");
        redirect('users');
    }

    /**
     * Manage assigned warehouses for a specific user
     *
     * @param int $id
     * @return void
     */
    public function warehouses($id)
    {
        $user = $this->User_model->get_by_id((int)$id);
        if (!$user) {
            $this->session->set_flashdata('error', 'المستخدم المطلوب غير موجود.');
            redirect('users');
            return;
        }

        $this->load->model('Warehouse_model');
        $this->load->model('User_warehouse_model');

        $data['page_title']             = 'المستودعات المصرح بها للمستخدم: ' . html_escape($user->full_name);
        $data['user']                   = $user;
        $data['warehouses']             = $this->Warehouse_model->get_active();
        $data['assigned_warehouse_ids'] = $this->User_warehouse_model->get_assigned_warehouse_ids($user->id);

        if ($this->input->method() === 'post') {
            $submitted_ids = $this->input->post('warehouse_ids');
            if (!is_array($submitted_ids)) {
                $submitted_ids = array();
            }

            $this->User_warehouse_model->sync_user_warehouses($user->id, $submitted_ids);
            $this->session->set_flashdata('success', 'تم تحديث المستودعات المصرح بها للمستخدم بنجاح.');
            redirect('users');
            return;
        }

        $this->render_view('users/warehouses', $data);
    }
}
