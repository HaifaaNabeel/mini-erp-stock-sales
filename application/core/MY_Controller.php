<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PHP 8.2+ compatibility: base controller.
 * Application controllers should extend MY_Controller, Auth_Controller, or Admin_Controller.
 */
#[\AllowDynamicProperties]
class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->model('Role_model');
    }

    /**
     * Render a view wrapped in a standard layout
     *
     * @param string $view
     * @param array $data
     * @param string|bool $layout
     * @return void
     */
    protected function render_view($view, array $data = array(), $layout = 'layouts/main')
    {
        if ($layout === false) {
            $this->load->view($view, $data);
            return;
        }

        $data['_view_content'] = $this->load->view($view, $data, true);
        $this->load->view($layout, $data);
    }

    /**
     * Send JSON response and exit
     *
     * @param mixed $data
     * @param int $status_code
     * @return void
     */
    protected function json_response($data, $status_code = 200)
    {
        $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE))
            ->_display();
        exit;
    }
}

/**
 * Controller requiring active authenticated user.
 */
#[\AllowDynamicProperties]
class Auth_Controller extends MY_Controller
{
    /**
     * Authenticated user object
     *
     * @var object|null
     */
    public $current_user = null;

    public function __construct()
    {
        parent::__construct();

        $user_id = $this->session->userdata('user_id');

        if (!$user_id) {
            if ($this->input->is_ajax_request()) {
                $this->json_response(array('status' => 'error', 'message' => 'يرجى تسجيل الدخول للمتابعة.'), 401);
            }
            redirect('login');
            return;
        }

        $user = $this->User_model->get_by_id($user_id);

        // Reject disabled or deleted user immediately
        if (!$user || (int)$user->is_active !== 1) {
            $this->session->sess_destroy();
            if ($this->input->is_ajax_request()) {
                $this->json_response(array('status' => 'error', 'message' => 'تم تعطيل الحساب أو إنهاء الجلسة.'), 403);
            }
            $this->session->set_flashdata('error', 'تم تعطيل حسابك أو إنهاء جلستك. يرجى مراجعة مدير النظام.');
            redirect('login');
            return;
        }

        $this->current_user = $user;
        $this->load->vars(array('current_user' => $user));
    }

    /**
     * Enforce role check on the server
     *
     * @param string|array $roles
     * @return void
     */
    protected function require_role($roles)
    {
        $roles = (array)$roles;
        if (!in_array($this->current_user->role_name, $roles, true)) {
            if ($this->input->is_ajax_request()) {
                $this->json_response(array('status' => 'error', 'message' => 'ليس لديك الصلاحية الكافية لتنفيذ هذا الإجراء.'), 403);
            }
            $this->session->set_flashdata('error', 'ليس لديك الصلاحية الكافية للوصول إلى هذه الصفحة أو تنفيذ الإجراء.');
            redirect('dashboard');
        }
    }

    public function is_admin()
    {
        return ($this->current_user && $this->current_user->role_name === 'admin');
    }

    public function is_warehouse()
    {
        return ($this->current_user && $this->current_user->role_name === 'warehouse');
    }

    public function is_sales()
    {
        return ($this->current_user && $this->current_user->role_name === 'sales');
    }
}

/**
 * Controller requiring Admin role.
 */
#[\AllowDynamicProperties]
class Admin_Controller extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role('admin');
    }
}
