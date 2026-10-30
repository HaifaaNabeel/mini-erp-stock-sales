<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Dashboard home page
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'لوحة التحكم الرئيسية';
        $data['current_user'] = $this->current_user;

        // Stats for admin
        if ($this->is_admin()) {
            $data['total_users'] = count($this->User_model->get_all_users());
            $data['active_admins'] = $this->User_model->count_active_admins();
        }

        $this->render_view('dashboard/index', $data);
    }
}

