<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customers Controller — F07 Customer Management
 *
 * Implements listing, search, pagination, creation, editing,
 * and soft disable/enable status toggling.
 *
 * Role permissions:
 * - Admin: Full access.
 * - Sales: Full customer management access (to support direct sales workflows).
 * - Warehouse: Forbidden.
 */
class Customers extends Auth_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role(array('admin', 'sales'));
        $this->load->model('Customer_model');
        $this->load->library('form_validation');
    }

    /**
     * List customers with search, status filter, and pagination
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'إدارة العملاء';

        $search = $this->input->get('search', TRUE);
        $status = $this->input->get('status', TRUE);

        $search = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;
        $status = ($status !== NULL && $status !== '') ? (int)$status : NULL;

        $per_page   = $this->get_per_page_limit(10);
        $total_rows = $this->Customer_model->count_customers($search, $status);
        if ($total_rows === FALSE) {
            $total_rows = 0;
        }

        $pagination = $this->setup_pagination('customers', $total_rows, $per_page);
        $customers  = $this->Customer_model->get_customers_paged($per_page, $pagination['offset'], $search, $status);

        $data['customers']      = ($customers !== FALSE) ? $customers : array();
        $data['pagination']     = $pagination;
        $data['current_search'] = $search;
        $data['current_status'] = $status;

        $this->render_view('customers/index', $data);
    }

    /**
     * Create a new customer
     *
     * @return void
     */
    public function create()
    {
        $data['page_title'] = 'إضافة عميل جديد';

        if ($this->input->method() === 'post') {
            $this->set_customer_validation_rules();

            if ($this->form_validation->run() === TRUE) {
                $customer_data = array(
                    'name'      => $this->input->post('name', TRUE),
                    'phone'     => $this->input->post('phone', TRUE),
                    'email'     => $this->input->post('email', TRUE),
                    'address'   => $this->input->post('address', TRUE),
                    'is_active' => $this->input->post('is_active') ? 1 : 0,
                );

                $result = $this->Customer_model->create($customer_data);

                if ($result['success']) {
                    $this->session->set_flashdata('success', 'تمت إضافة العميل بنجاح.');
                    redirect('customers');
                    return;
                }

                $this->session->set_flashdata('error', $result['error'] ? $result['error'] : 'فشل حفظ بيانات العميل.');
            }
        }

        $this->render_view('customers/create', $data);
    }

    /**
     * Edit existing customer details
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        $customer = $this->Customer_model->get_by_id((int)$id);
        if (!$customer) {
            $this->session->set_flashdata('error', 'العميل المطلوب غير موجود.');
            redirect('customers');
            return;
        }

        $data['page_title'] = 'تعديل بيانات العميل: ' . html_escape($customer->name);
        $data['customer']   = $customer;

        if ($this->input->method() === 'post') {
            $this->set_customer_validation_rules();

            if ($this->form_validation->run() === TRUE) {
                $update_data = array(
                    'name'      => $this->input->post('name', TRUE),
                    'phone'     => $this->input->post('phone', TRUE),
                    'email'     => $this->input->post('email', TRUE),
                    'address'   => $this->input->post('address', TRUE),
                    'is_active' => $this->input->post('is_active') ? 1 : 0,
                );

                $result = $this->Customer_model->update($customer->id, $update_data);

                if ($result['success']) {
                    $this->session->set_flashdata('success', 'تم تحديث بيانات العميل بنجاح.');
                    redirect('customers');
                    return;
                }

                $this->session->set_flashdata('error', $result['error'] ? $result['error'] : 'فشل تحديث بيانات العميل.');
            }
        }

        $this->render_view('customers/edit', $data);
    }

    /**
     * Explicitly activate customer (Idempotent POST)
     *
     * @param int $id
     * @return void
     */
    public function activate($id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }

        $customer = $this->Customer_model->get_by_id((int)$id);
        if (!$customer) {
            $this->session->set_flashdata('error', 'العميل المطلوب غير موجود.');
            redirect('customers');
            return;
        }

        $result = $this->Customer_model->activate($customer->id);

        if ($result['success']) {
            $this->session->set_flashdata('success', 'تم تفعيل حساب العميل بنجاح.');
        } else {
            $this->session->set_flashdata('error', $result['error'] ? $result['error'] : 'فشل تفعيل حساب العميل.');
        }

        redirect('customers');
    }

    /**
     * Explicitly deactivate customer (Idempotent POST)
     *
     * @param int $id
     * @return void
     */
    public function deactivate($id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
            return;
        }

        $customer = $this->Customer_model->get_by_id((int)$id);
        if (!$customer) {
            $this->session->set_flashdata('error', 'العميل المطلوب غير موجود.');
            redirect('customers');
            return;
        }

        $result = $this->Customer_model->deactivate($customer->id);

        if ($result['success']) {
            $this->session->set_flashdata('success', 'تم تعطيل حساب العميل بنجاح.');
        } else {
            $this->session->set_flashdata('error', $result['error'] ? $result['error'] : 'فشل تعطيل حساب العميل.');
        }

        redirect('customers');
    }

    /**
     * Compatibility wrapper for status changes
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

        $target = $this->input->post('status');
        if ($target !== NULL) {
            if ((int)$target === 1) {
                $this->activate($id);
            } else {
                $this->deactivate($id);
            }
            return;
        }

        $customer = $this->Customer_model->get_by_id((int)$id);
        if (!$customer) {
            $this->session->set_flashdata('error', 'العميل المطلوب غير موجود.');
            redirect('customers');
            return;
        }

        if ((int)$customer->is_active === 1) {
            $this->deactivate($id);
        } else {
            $this->activate($id);
        }
    }

    /**
     * Set form validation rules for Customer inputs
     *
     * @return void
     */
    protected function set_customer_validation_rules()
    {
        $this->form_validation->set_rules('name', 'اسم العميل', 'required|trim|min_length[2]|max_length[150]', array(
            'required'   => 'حقل %s مطلوب.',
            'min_length' => 'يجب ألا يقل %s عن حرفين.',
            'max_length' => 'يجب ألا يزيد %s عن 150 حرفاً.',
        ));

        $this->form_validation->set_rules('phone', 'رقم الهاتف', 'trim|max_length[30]', array(
            'max_length' => 'يجب ألا يزيد %s عن 30 حرفاً.',
        ));

        $this->form_validation->set_rules('email', 'البريد الإلكتروني', 'trim|valid_email|max_length[191]', array(
            'valid_email' => '%s المدخل غير صالح.',
            'max_length'  => 'يجب ألا يزيد %s عن 191 حرفاً.',
        ));

        $this->form_validation->set_rules('address', 'العنوان', 'trim|max_length[255]', array(
            'max_length' => 'يجب ألا يزيد %s عن 255 حرفاً.',
        ));
    }
}

