<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Audit Logs Controller
 *
 * Read-only administrative viewer for system audit logs.
 * Strictly restricted to Admin role.
 * Modifying or deleting audit logs is strictly prohibited.
 */
class Audit_logs extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Audit_model');
        $this->load->model('User_model');
    }

    /**
     * Audit logs list with filtering and pagination
     *
     * @return void
     */
    public function index()
    {
        $action      = $this->input->get('action', TRUE);
        $entity_type = $this->input->get('entity_type', TRUE);
        $user_id     = $this->input->get('user_id', TRUE);
        $date_from   = $this->input->get('date_from', TRUE);
        $date_to     = $this->input->get('date_to', TRUE);
        $search      = $this->input->get('search', TRUE);

        $filters = array(
            'action'      => ($action !== NULL && trim($action) !== '') ? trim($action) : NULL,
            'entity_type' => ($entity_type !== NULL && trim($entity_type) !== '') ? trim($entity_type) : NULL,
            'user_id'     => ($user_id !== NULL && (int)$user_id > 0) ? (int)$user_id : NULL,
            'date_from'   => ($date_from !== NULL && trim($date_from) !== '') ? trim($date_from) : NULL,
            'date_to'     => ($date_to !== NULL && trim($date_to) !== '') ? trim($date_to) : NULL,
            'search'      => ($search !== NULL && trim($search) !== '') ? trim($search) : NULL,
        );

        $per_page   = $this->get_per_page_limit(25);
        $total_rows = $this->Audit_model->count_logs($filters);
        $pagination = $this->setup_pagination('audit_logs', $total_rows, $per_page);

        $data['page_title']    = 'سجل التدقيق المركزي للعمليات';
        $data['logs']          = $this->Audit_model->get_logs_paged($filters, $per_page, $pagination['offset']);
        $data['pagination']    = $pagination;
        $data['total_count']   = $total_rows;
        $data['filters']       = $filters;
        $data['actions']       = $this->Audit_model->get_distinct_actions();
        $data['entity_types']  = $this->Audit_model->get_distinct_entity_types();
        $data['users']         = $this->User_model->get_all_users();

        $this->render_view('audit_logs/index', $data);
    }

    /**
     * View audit log details (AJAX / JSON or view)
     *
     * @param int $id
     * @return void
     */
    public function view($id)
    {
        $log = $this->Audit_model->get_by_id((int)$id);
        if (!$log) {
            $this->session->set_flashdata('error', 'سجل التدقيق غير موجود.');
            redirect('audit_logs');
            return;
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'success' => TRUE,
                    'data'    => $log
                )));
            return;
        }

        $data['page_title'] = 'تفاصيل سجل التدقيق #' . $log->id;
        $data['log']        = $log;

        $this->render_view('audit_logs/view', $data);
    }
}

