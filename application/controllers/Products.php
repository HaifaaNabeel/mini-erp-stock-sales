<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Products extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Product_model');
        $this->load->model('Category_model');
        $this->load->library('form_validation');
        $this->load->library('pagination');
    }

    /**
     * List all products with search, category filter, and pagination
     *
     * @return void
     */
    public function index()
    {
        $data['page_title'] = 'إدارة المنتجات';

        // Retrieve filters from GET
        $search      = $this->input->get('search', TRUE);
        $category_id = $this->input->get('category_id', TRUE);
        $status      = $this->input->get('status', TRUE);

        $search      = ($search !== NULL && trim($search) !== '') ? trim($search) : NULL;
        $category_id = ($category_id !== NULL && (int)$category_id > 0) ? (int)$category_id : NULL;
        $status      = ($status !== NULL && $status !== '') ? (int)$status : NULL;

        $per_page   = $this->get_per_page_limit(10);
        $total_rows = $this->Product_model->count_products($search, $category_id, $status);
        $pagination = $this->setup_pagination('products', $total_rows, $per_page);

        $data['products']         = $this->Product_model->get_products($per_page, $pagination['offset'], $search, $category_id, $status);
        $data['categories']       = $this->Category_model->get_all(TRUE);
        $data['pagination']       = $pagination;
        $data['total_count']      = $total_rows;
        $data['current_search']   = $search;
        $data['current_category'] = $category_id;
        $data['current_status']   = $status;

        $this->render_view('products/index', $data);
    }

    /**
     * Create a new product
     *
     * @return void
     */
    public function create()
    {
        $data['page_title'] = 'إضافة منتج جديد';
        // Only active categories are allowed for assignment
        $data['categories'] = $this->Category_model->get_active();

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('sku', 'رمز المنتج (SKU)', 'required|trim|max_length[64]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 64 حرفاً.',
            ));
            $this->form_validation->set_rules('name', 'اسم المنتج', 'required|trim|max_length[200]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 200 حرف.',
            ));
            $this->form_validation->set_rules('category_id', 'التصنيف', 'required|is_natural_no_zero', array(
                'required'            => 'يرجى اختيار %s.',
                'is_natural_no_zero' => 'التصنيف المختار غير صالح.',
            ));
            $this->form_validation->set_rules('sale_price', 'سعر البيع', 'required|numeric|greater_than_equal_to[0]', array(
                'required'               => 'حقل %s مطلوب.',
                'numeric'                => 'يجب أن يكون %s قيمة رقمية.',
                'greater_than_equal_to' => 'يجب ألا يكون %s أقل من الصفر (0.00).',
            ));
            $this->form_validation->set_rules('reorder_level', 'حد إعادة الطلب', 'required|is_natural', array(
                'required'   => 'حقل %s مطلوب.',
                'is_natural' => 'يجب أن يكون %s رقماً صحيحاً غير سالب (0 أو أكبر).',
            ));

            if ($this->form_validation->run() === TRUE) {
                $sku = $this->Product_model->normalize_sku($this->input->post('sku', TRUE));

                // 1. Check SKU Uniqueness
                if ($this->Product_model->sku_exists($sku)) {
                    $this->session->set_flashdata('error', 'رمز المنتج (SKU) مسجل مسبقاً، يرجى اختيار رمز فريد.');
                    $this->render_view('products/create', $data);
                    return;
                }

                // 2. Prevent binding product to an inactive category
                $category_id = (int)$this->input->post('category_id');
                $category = $this->Category_model->get_by_id($category_id);
                if (!$category || (int)$category->is_active !== 1) {
                    $this->session->set_flashdata('error', 'لا يمكن ربط المنتج بتصنيف معطل أو غير موجود. يرجى اختيار تصنيف نشط.');
                    $this->render_view('products/create', $data);
                    return;
                }

                $product_data = array(
                    'sku'           => $sku,
                    'name'          => trim($this->input->post('name', TRUE)),
                    'description'   => $this->input->post('description', TRUE),
                    'category_id'   => $category_id,
                    'sale_price'    => (float)$this->input->post('sale_price'),
                    'reorder_level' => (int)$this->input->post('reorder_level'),
                    'is_active'     => $this->input->post('is_active') ? 1 : 0,
                );

                $this->Product_model->create($product_data);
                $this->session->set_flashdata('success', 'تم إنشاء المنتج بنجاح.');
                redirect('products');
                return;
            }
        }

        $this->render_view('products/create', $data);
    }

    /**
     * Edit existing product
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        $product = $this->Product_model->get_by_id((int)$id);
        if (!$product) {
            $this->session->set_flashdata('error', 'المنتج المطلوب غير موجود.');
            redirect('products');
            return;
        }

        $data['page_title'] = 'تعديل المنتج: ' . html_escape($product->name);
        $data['product']    = $product;
        // Available categories for selection (active categories)
        $data['categories'] = $this->Category_model->get_active();

        if ($this->input->method() === 'post') {
            $this->form_validation->set_rules('sku', 'رمز المنتج (SKU)', 'required|trim|max_length[64]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 64 حرفاً.',
            ));
            $this->form_validation->set_rules('name', 'اسم المنتج', 'required|trim|max_length[200]', array(
                'required'   => 'حقل %s مطلوب.',
                'max_length' => 'يجب ألا يزيد %s عن 200 حرف.',
            ));
            $this->form_validation->set_rules('category_id', 'التصنيف', 'required|is_natural_no_zero', array(
                'required'            => 'يرجى اختيار %s.',
                'is_natural_no_zero' => 'التصنيف المختار غير صالح.',
            ));
            $this->form_validation->set_rules('sale_price', 'سعر البيع', 'required|numeric|greater_than_equal_to[0]', array(
                'required'               => 'حقل %s مطلوب.',
                'numeric'                => 'يجب أن يكون %s قيمة رقمية.',
                'greater_than_equal_to' => 'يجب ألا يكون %s أقل من الصفر (0.00).',
            ));
            $this->form_validation->set_rules('reorder_level', 'حد إعادة الطلب', 'required|is_natural', array(
                'required'   => 'حقل %s مطلوب.',
                'is_natural' => 'يجب أن يكون %s رقماً صحيحاً غير سالب (0 أو أكبر).',
            ));

            if ($this->form_validation->run() === TRUE) {
                $sku = $this->Product_model->normalize_sku($this->input->post('sku', TRUE));

                // 1. Check SKU Uniqueness excluding current product
                if ($this->Product_model->sku_exists($sku, $product->id)) {
                    $this->session->set_flashdata('error', 'رمز المنتج (SKU) مسجل مسبقاً لمنتج آخر.');
                    $this->render_view('products/edit', $data);
                    return;
                }

                // 2. Prevent binding product to an inactive category
                $category_id = (int)$this->input->post('category_id');
                $category = $this->Category_model->get_by_id($category_id);
                if (!$category || (int)$category->is_active !== 1) {
                    $this->session->set_flashdata('error', 'لا يمكن ربط المنتج بتصنيف معطل أو غير موجود. يرجى اختيار تصنيف نشط.');
                    $this->render_view('products/edit', $data);
                    return;
                }

                $update_data = array(
                    'sku'           => $sku,
                    'name'          => trim($this->input->post('name', TRUE)),
                    'description'   => $this->input->post('description', TRUE),
                    'category_id'   => $category_id,
                    'sale_price'    => (float)$this->input->post('sale_price'),
                    'reorder_level' => (int)$this->input->post('reorder_level'),
                    'is_active'     => $this->input->post('is_active') ? 1 : 0,
                );

                $this->Product_model->update($product->id, $update_data);
                $this->session->set_flashdata('success', 'تم تحديث بيانات المنتج بنجاح.');
                redirect('products');
                return;
            }
        }

        $this->render_view('products/edit', $data);
    }

    /**
     * Toggle product active/inactive status (Soft toggle)
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

        $product = $this->Product_model->get_by_id((int)$id);
        if (!$product) {
            $this->session->set_flashdata('error', 'المنتج المطلوب غير موجود.');
            redirect('products');
            return;
        }

        $this->Product_model->toggle_status($product->id);
        $new_status_text = ((int)$product->is_active === 1) ? 'تعطيل' : 'تفعيل';
        $this->session->set_flashdata('success', "تم {$new_status_text} المنتج بنجاح.");
        redirect('products');
    }
}

