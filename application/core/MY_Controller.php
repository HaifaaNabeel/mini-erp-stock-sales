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

    /**
     * Validate and retrieve requested per-page limit (Show entries)
     * Allowed values: 10, 20, 50, 100
     *
     * @param int $default
     * @return int
     */
    protected function get_per_page_limit($default = 10)
    {
        $allowed = array(10, 20, 50, 100);
        $limit = (int)$this->input->get('limit');
        return in_array($limit, $allowed, true) ? $limit : $default;
    }

    /**
     * Standardized Server-Side Pagination Setup
     *
     * @param string $base_url Route path (e.g. 'products', 'users')
     * @param int $total_rows Total matched records
     * @param int $per_page Records per page
     * @param string $query_segment Query string key for page number
     * @return array
     */
    protected function setup_pagination($base_url, $total_rows, $per_page, $query_segment = 'page')
    {
        $this->load->library('pagination');

        $config['base_url']             = site_url($base_url);
        $config['total_rows']           = (int)$total_rows;
        $config['per_page']             = (int)$per_page;
        $config['page_query_string']    = TRUE;
        $config['query_string_segment'] = $query_segment;
        $config['reuse_query_string']   = TRUE;
        $config['use_page_numbers']     = TRUE;

        // Unified Arabic RTL Styling
        $config['full_tag_open']   = '<ul class="pagination">';
        $config['full_tag_close']  = '</ul>';
        $config['first_link']      = '&laquo; الأولى';
        $config['first_tag_open']  = '<li class="page-item">';
        $config['first_tag_close'] = '</li>';
        $config['last_link']       = 'الأخيرة &raquo;';
        $config['last_tag_open']  = '<li class="page-item">';
        $config['last_tag_close'] = '</li>';
        $config['next_link']       = 'التالي &rsaquo;';
        $config['next_tag_open']   = '<li class="page-item">';
        $config['next_tag_close']  = '</li>';
        $config['prev_link']       = '&lsaquo; السابق';
        $config['prev_tag_open']   = '<li class="page-item">';
        $config['prev_tag_close']  = '</li>';
        $config['cur_tag_open']    = '<li class="page-item active"><span class="page-link">';
        $config['cur_tag_close']   = '</span></li>';
        $config['num_tag_open']    = '<li class="page-item">';
        $config['num_tag_close']   = '</li>';
        $config['attributes']      = array('class' => 'page-link');

        $this->pagination->initialize($config);

        $page = (int)$this->input->get($query_segment);
        $current_page = ($page > 0) ? $page : 1;
        $offset = ($current_page - 1) * $per_page;

        $from = ($total_rows > 0) ? ($offset + 1) : 0;
        $to   = min($offset + $per_page, $total_rows);

        return array(
            'links'        => $this->pagination->create_links(),
            'total_rows'   => (int)$total_rows,
            'per_page'     => (int)$per_page,
            'current_page' => $current_page,
            'offset'       => $offset,
            'from'         => $from,
            'to'           => $to,
        );
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
