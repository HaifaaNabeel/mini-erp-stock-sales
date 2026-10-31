<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customer_model — F07 Customer Master Data Model
 *
 * Handles customer retrieval, search, pagination, creation, update,
 * and soft-disable/enable status toggling.
 *
 * All database operations isolate db_debug using try/finally blocks
 * to ensure errors are captured cleanly rather than halting execution.
 *
 * PHP Compatibility: 7.4+
 */
class Customer_model extends MY_Model
{
    protected $table = 'customers';

    const MAX_UNSIGNED_INT = 4294967295;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all customers (optionally filter by active status)
     *
     * @param bool $include_inactive Whether to include inactive customers
     * @return array Array of customer objects, or empty array on failure
     */
    public function get_all($include_inactive = FALSE)
    {
        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->from($this->table);

            if (!$include_inactive) {
                $this->db->where('is_active', 1);
            }

            $this->db->order_by('name', 'ASC');
            $query = $this->db->get();

            $db_error = $this->db->error();
            if ($query === FALSE || !empty($db_error['code'])) {
                return array();
            }

            return $query->result();
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Get single customer by ID
     *
     * @param mixed $id Customer ID
     * @return object|null|false Customer object, NULL if not found, FALSE on invalid ID/query failure
     */
    public function get_by_id($id)
    {
        $valid_id = filter_var($id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
        ));

        if ($valid_id === FALSE) {
            return FALSE;
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $query = $this->db->from($this->table)
                ->where('id', $valid_id)
                ->get();

            $db_error = $this->db->error();
            if ($query === FALSE || !empty($db_error['code'])) {
                return FALSE;
            }

            $row = $query->row();
            return $row ? $row : NULL;
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Get active customer by ID
     * Returns the customer object ONLY if found and is_active == 1.
     * Returns NULL if inactive or not found, FALSE on query/validation error.
     *
     * @param mixed $id Customer ID
     * @return object|null|false
     */
    public function get_active_customer($id)
    {
        $customer = $this->get_by_id($id);

        if ($customer === FALSE) {
            return FALSE;
        }

        if ($customer === NULL) {
            return NULL;
        }

        if ((int)$customer->is_active !== 1) {
            return NULL;
        }

        return $customer;
    }

    /**
     * Count customers matching filters for pagination
     *
     * @param string|null $search Search term (name or phone)
     * @param int|null $is_active Filter by status (1 = active, 0 = inactive, NULL = all)
     * @return int|false Count of matching customers, or FALSE on query failure
     */
    public function count_customers($search = NULL, $is_active = NULL)
    {
        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->from($this->table);
            $this->apply_search_and_filter($search, $is_active);

            $count = $this->db->count_all_results();
            $db_error = $this->db->error();

            if (!empty($db_error['code'])) {
                return FALSE;
            }

            return (int)$count;
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Get paginated customers list
     *
     * @param int $limit Number of records to return
     * @param int $offset Offset
     * @param string|null $search Search term (name or phone)
     * @param int|null $is_active Filter by status (1 = active, 0 = inactive, NULL = all)
     * @return array|false Array of customer objects, or FALSE on query failure
     */
    public function get_customers_paged($limit, $offset, $search = NULL, $is_active = NULL)
    {
        $limit_val  = max(1, (int)$limit);
        $offset_val = max(0, (int)$offset);

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->from($this->table);
            $this->apply_search_and_filter($search, $is_active);

            $this->db->order_by('id', 'DESC');
            $this->db->limit($limit_val, $offset_val);

            $query = $this->db->get();
            $db_error = $this->db->error();

            if ($query === FALSE || !empty($db_error['code'])) {
                return FALSE;
            }

            return $query->result();
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Create a new customer
     *
     * @param array $data Input data (name, phone, email, address, is_active)
     * @return array Result array with success (bool), customer_id (int|null), error (string|null)
     */
    public function create($data)
    {
        $validation = $this->validate_customer_data($data, TRUE);
        if (!$validation['valid']) {
            return array(
                'success'    => FALSE,
                'error'      => $validation['error'],
                'error_code' => 'VALIDATION_FAILED',
                'customer_id'=> NULL
            );
        }

        $insert_data = array(
            'name'       => $validation['clean_data']['name'],
            'phone'      => $validation['clean_data']['phone'],
            'email'      => $validation['clean_data']['email'],
            'address'    => $validation['clean_data']['address'],
            'is_active'  => isset($data['is_active']) ? ((int)$data['is_active'] === 1 ? 1 : 0) : 1,
            'created_at' => date('Y-m-d H:i:s'),
        );

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $result = $this->db->insert($this->table, $insert_data);
            $db_error = $this->db->error();

            if ($result === FALSE || !empty($db_error['code'])) {
                return array(
                    'success'       => FALSE,
                    'error'         => 'فشل إضافة العميل في قاعدة البيانات.',
                    'error_code'    => 'DB_INSERT_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL,
                    'customer_id'   => NULL
                );
            }

            $customer_id = (int)$this->db->insert_id();
            if ($customer_id > 0) {
                if (!isset($this->Audit_model)) {
                    $this->load->model('Audit_model');
                }
                $this->Audit_model->log('customer_create', 'customer', $customer_id, "إضافة عميل جديد: {$insert_data['name']}", $insert_data);
            }

            return array(
                'success'     => TRUE,
                'customer_id' => $customer_id,
                'error'       => NULL
            );
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Update customer details
     *
     * @param mixed $id Customer ID
     * @param array $data Data to update
     * @return array Result array with success (bool), error (string|null)
     */
    public function update($id, $data)
    {
        $valid_id = filter_var($id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
        ));

        if ($valid_id === FALSE) {
            return array(
                'success'    => FALSE,
                'error'      => 'معرف العميل غير صالح.',
                'error_code' => 'INVALID_ID'
            );
        }

        $validation = $this->validate_customer_data($data, FALSE);
        if (!$validation['valid']) {
            return array(
                'success'    => FALSE,
                'error'      => $validation['error'],
                'error_code' => 'VALIDATION_FAILED'
            );
        }

        $update_data = $validation['clean_data'];
        if (isset($data['is_active'])) {
            $update_data['is_active'] = ((int)$data['is_active'] === 1) ? 1 : 0;
        }
        $update_data['updated_at'] = date('Y-m-d H:i:s');

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $result = $this->db->where('id', $valid_id)->update($this->table, $update_data);
            $db_error = $this->db->error();

            if ($result === FALSE || !empty($db_error['code'])) {
                return array(
                    'success'       => FALSE,
                    'error'         => 'فشل تحديث بيانات العميل في قاعدة البيانات.',
                    'error_code'    => 'DB_UPDATE_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            return array(
                'success' => TRUE,
                'error'   => NULL
            );
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Toggle or set customer active status (Soft Enable / Soft Disable)
     *
     * @param mixed $id Customer ID
     * @param int|null $status Explicit status (1 or 0), or NULL to toggle
     * @return array Result array
     */
    public function set_status($id, $status = NULL)
    {
        $valid_id = filter_var($id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
        ));

        if ($valid_id === FALSE) {
            return array('success' => FALSE, 'error' => 'معرف العميل غير صالح.', 'error_code' => 'INVALID_ID');
        }

        $customer = $this->get_by_id($valid_id);
        if (!$customer) {
            return array('success' => FALSE, 'error' => 'العميل غير موجود.', 'error_code' => 'CUSTOMER_NOT_FOUND');
        }

        $target_status = ($status !== NULL) ? (((int)$status === 1) ? 1 : 0) : (((int)$customer->is_active === 1) ? 0 : 1);

        // Idempotency: if customer is already in target status, return success immediately
        if ($status !== NULL && (int)$customer->is_active === $target_status) {
            return array(
                'success'    => TRUE,
                'new_status' => $target_status,
                'error'      => NULL,
                'no_change'  => TRUE
            );
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $result = $this->db->where('id', $valid_id)->update($this->table, array(
                'is_active'  => $target_status,
                'updated_at' => date('Y-m-d H:i:s')
            ));
            $db_error = $this->db->error();

            if ($result === FALSE || !empty($db_error['code'])) {
                return array(
                    'success'    => FALSE,
                    'error'      => 'فشل تحديث حالة العميل.',
                    'error_code' => 'DB_UPDATE_FAILED'
                );
            }

            if (!isset($this->Audit_model)) {
                $this->load->model('Audit_model');
            }
            $action = ($target_status === 1) ? 'customer_activate' : 'customer_deactivate';
            $details = ($target_status === 1) ? "تفعيل العميل #{$valid_id} ({$customer->name})" : "تعطيل العميل #{$valid_id} ({$customer->name})";
            $this->Audit_model->log($action, 'customer', $valid_id, $details, array('is_active' => $target_status));

            return array(
                'success'    => TRUE,
                'new_status' => $target_status,
                'error'      => NULL,
                'no_change'  => FALSE
            );
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Explicitly activate customer (Idempotent: sets is_active = 1)
     *
     * @param mixed $id Customer ID
     * @return array
     */
    public function activate($id)
    {
        return $this->set_status($id, 1);
    }

    /**
     * Explicitly deactivate customer (Idempotent: sets is_active = 0)
     *
     * @param mixed $id Customer ID
     * @return array
     */
    public function deactivate($id)
    {
        return $this->set_status($id, 0);
    }

    /**
     * Toggle customer active status alias (fallback)
     *
     * @param mixed $id Customer ID
     * @return array
     */
    public function toggle_status($id)
    {
        return $this->set_status($id, NULL);
    }

    /**
     * Apply search and filter to active query
     *
     * @param string|null $search
     * @param int|null $is_active
     * @return void
     */
    protected function apply_search_and_filter($search = NULL, $is_active = NULL)
    {
        if ($search !== NULL && trim($search) !== '') {
            $clean_search = trim($search);
            $this->db->group_start();
            $this->db->like('name', $clean_search);
            $this->db->or_like('phone', $clean_search);
            $this->db->group_end();
        }

        if ($is_active !== NULL && ($is_active === 1 || $is_active === 0 || $is_active === '1' || $is_active === '0')) {
            $this->db->where('is_active', (int)$is_active);
        }
    }

    /**
     * Validate customer input data
     *
     * @param array $data
     * @param bool $is_create
     * @return array
     */
    protected function validate_customer_data($data, $is_create = TRUE)
    {
        $clean = array();

        // Name validation
        if ($is_create || isset($data['name'])) {
            $name = isset($data['name']) ? trim((string)$data['name']) : '';
            $len = mb_strlen($name, 'UTF-8');
            if ($len < 2 || $len > 150) {
                return array('valid' => FALSE, 'error' => 'اسم العميل مطلوب ويجب أن يكون بين حرفين و150 حرفاً.');
            }
            $clean['name'] = $name;
        }

        // Phone validation
        if ($is_create || array_key_exists('phone', $data)) {
            $phone = isset($data['phone']) ? trim((string)$data['phone']) : '';
            if ($phone !== '') {
                if (mb_strlen($phone, 'UTF-8') > 30) {
                    return array('valid' => FALSE, 'error' => 'رقم الهاتف لا يجب أن يتجاوز 30 حرفاً.');
                }
                $clean['phone'] = $phone;
            } else {
                $clean['phone'] = NULL;
            }
        }

        // Email validation
        if ($is_create || array_key_exists('email', $data)) {
            $email = isset($data['email']) ? trim((string)$data['email']) : '';
            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 191) {
                    return array('valid' => FALSE, 'error' => 'البريد الإلكتروني المدخل غير صالح أو يتجاوز 191 حرفاً.');
                }
                $clean['email'] = $email;
            } else {
                $clean['email'] = NULL;
            }
        }

        // Address validation
        if ($is_create || array_key_exists('address', $data)) {
            $address = isset($data['address']) ? trim((string)$data['address']) : '';
            if ($address !== '') {
                if (mb_strlen($address, 'UTF-8') > 255) {
                    return array('valid' => FALSE, 'error' => 'العنوان لا يجب أن يتجاوز 255 حرفاً.');
                }
                $clean['address'] = $address;
            } else {
                $clean['address'] = NULL;
            }
        }

        return array('valid' => TRUE, 'clean_data' => $clean);
    }
}

