<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
    }

    /**
     * User login action
     *
     * @return void
     */
    public function login()
    {
        // If already logged in, redirect to dashboard
        if ($this->session->userdata('user_id')) {
            redirect('dashboard');
            return;
        }

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('username', 'اسم المستخدم', 'required|trim', array(
                'required' => 'حقل %s مطلوب.',
            ));
            $this->form_validation->set_rules('password', 'كلمة المرور', 'required', array(
                'required' => 'حقل %s مطلوب.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $raw_username = $this->input->post('username', TRUE);
                $password = $this->input->post('password');

                $normalized_username = $this->User_model->normalize_username($raw_username);
                $user = $this->User_model->get_by_username_for_auth($normalized_username);

                if (!$user || !password_verify($password, $user->password_hash)) {
                    $this->session->set_flashdata('error', 'اسم المستخدم أو كلمة المرور غير صحيحة.');
                    redirect('login');
                    return;
                }

                if ((int)$user->is_active !== 1) {
                    $this->session->set_flashdata('error', 'هذا الحساب معطل. يرجى مراجعة مدير النظام.');
                    redirect('login');
                    return;
                }

                // Regenerate session ID to prevent session fixation
                $this->session->sess_regenerate(TRUE);

                // Record last login timestamp
                $this->User_model->update_last_login($user->id);

                // Store session data
                $session_data = array(
                    'user_id'           => (int)$user->id,
                    'username'          => $user->username,
                    'full_name'         => $user->full_name,
                    'email'             => $user->email,
                    'role_id'           => (int)$user->role_id,
                    'role_name'         => $user->role_name,
                    'role_display_name' => $user->role_display_name,
                    'logged_in'         => TRUE,
                );
                $this->session->set_userdata($session_data);
                $this->session->set_flashdata('success', 'تم تسجيل الدخول بنجاح. أهلاً بك ' . html_escape($user->full_name) . '!');

                redirect('dashboard');
                return;
            }
        }

        $data['page_title'] = 'تسجيل الدخول — Mini ERP';
        $this->load->view('auth/login', $data);
    }

    /**
     * User logout action
     *
     * @return void
     */
    public function logout()
    {
        $this->session->sess_destroy();
        redirect('login');
    }
}

