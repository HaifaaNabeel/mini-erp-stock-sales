<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Categories extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Category_model');
        $this->load->library('form_validation');
    }

    /**
     * List all categories
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'إدارة التصنيفات';

        $search = $this->input->get('search', TRUE);
        $status = $this->input->get('status', TRUE);
        $search = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;
        $status = ($status !== NULL && $status !== '') ? (int)$status : NULL;

        $per_page   = $this->get_per_page_limit(10);
        $total_rows = $this->Category_model->count_categories($search, $status);
        $pagination = $this->setup_pagination('categories', $total_rows, $per_page);

        $data['categories']     = $this->Category_model->get_categories_paged($per_page, $pagination['offset'], $search, $status);
        $data['pagination']     = $pagination;
        $data['current_search'] = $search;
        $data['current_status'] = $status;

        $this->render_view('categories/index', $data);
    }

    /**
     * Create a new category
     *
     * @return void
     */
    public function create()
    {
        $data['page_title'] = 'إضافة تصنيف جديد';

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('name', 'اسم التصنيف', 'required|trim|max_length[100]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 100 حرف.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $name = trim($this->input->post('name', TRUE));

                if ($this->Category_model->name_exists($name)) {
                    $this->session->set_flashdata('error', 'اسم التصنيف مسجل مسبقاً، يرجى اختيار اسم آخر.');
                    $this->render_view('categories/create', $data);
                    return;
                }

                $category_data = array(
                    'name'        => $name,
                    'description' => $this->input->post('description', TRUE),
                    'is_active'   => $this->input->post('is_active') ? 1 : 0,
                );

                $this->Category_model->create($category_data);
                $this->session->set_flashdata('success', 'تم إنشاء التصنيف بنجاح.');
                redirect('categories');
                return;
            }
        }

        $this->render_view('categories/create', $data);
    }

    /**
     * Edit existing category
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        $category = $this->Category_model->get_by_id((int)$id);
        if (!$category) {
            $this->session->set_flashdata('error', 'التصنيف المطلوب غير موجود.');
            redirect('categories');
            return;
        }

        $data['page_title'] = 'تعديل التصنيف: ' . html_escape($category->name);
        $data['category']   = $category;

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('name', 'اسم التصنيف', 'required|trim|max_length[100]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 100 حرف.',
            ));

            if ($this->form_validation->run() === TRUE) {
                $name = trim($this->input->post('name', TRUE));

                if ($this->Category_model->name_exists($name, $category->id)) {
                    $this->session->set_flashdata('error', 'اسم التصنيف مسجل مسبقاً لتصنيف آخر.');
                    $this->render_view('categories/edit', $data);
                    return;
                }

                $update_data = array(
                    'name'        => $name,
                    'description' => $this->input->post('description', TRUE),
                    'is_active'   => $this->input->post('is_active') ? 1 : 0,
                );

                $this->Category_model->update($category->id, $update_data);
                $this->session->set_flashdata('success', 'تم تحديث بيانات التصنيف بنجاح.');
                redirect('categories');
                return;
            }
        }

        $this->render_view('categories/edit', $data);
    }

    /**
     * Toggle category active/inactive status (Soft toggle)
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

        $category = $this->Category_model->get_by_id((int)$id);
        if (!$category) {
            $this->session->set_flashdata('error', 'التصنيف المطلوب غير موجود.');
            redirect('categories');
            return;
        }

        $this->Category_model->toggle_status($category->id);
        $new_status_text = ((int)$category->is_active === 1) ? 'تعطيل' : 'تفعيل';
        $this->session->set_flashdata('success', "تم {$new_status_text} التصنيف بنجاح.");
        redirect('categories');
    }
}

