<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Audit Model & Central Logging Service
 *
 * Provides centralized audit trail logging for all critical system operations:
 * - User management & authorizations
 * - Master data changes (Categories, Products, Warehouses, Customers)
 * - Inventory operations (Stock in, adjustments)
 * - Sales operations (Invoice creation, cancellation, reversals)
 *
 * Guarantees:
 * - Strict exclusion of sensitive data (passwords, tokens, secrets)
 * - Atomic participation in active database transactions
 * - Read-only integrity: No update or delete operations are exposed
 *
 * PHP Compatibility: 7.4+
 */
class Audit_model extends CI_Model
{
    protected $table = 'audit_logs';

    /**
     * Forbidden sensitive metadata keys that must never be written to audit logs
     */
    protected static $sensitive_keys = array(
        'password',
        'password_hash',
        'password_confirm',
        'old_password',
        'new_password',
        'token',
        'csrf_erp_token',
        'csrf_test_name',
        'session_id',
        'encryption_key',
        'secret',
        'api_key',
        'credit_card'
    );

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Recursively sanitize metadata array by removing sensitive keys and values
     *
     * @param array $data
     * @return array
     */
    public static function sanitize_metadata(array $data)
    {
        $clean = array();
        foreach ($data as $key => $value) {
            $key_lower = strtolower((string)$key);
            $is_sensitive = false;
            foreach (self::$sensitive_keys as $sens) {
                if ($key_lower === $sens || strpos($key_lower, $sens) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive) {
                continue; // Omit sensitive field completely
            }

            if (is_array($value)) {
                $clean[$key] = self::sanitize_metadata($value);
            } elseif (is_scalar($value) || $value === NULL) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = (string)$value;
            }
        }
        return $clean;
    }

    /**
     * Record an audit log entry
     *
     * Can be invoked inside an active transaction or standalone.
     *
     * @param string $action Action code (e.g. 'user_create', 'stock_in', 'invoice_create')
     * @param string $entity_type Affected entity ('user', 'product', 'warehouse', 'sales_invoice', etc.)
     * @param int|null $entity_id Entity identifier
     * @param string $details Human-readable Arabic description of the event
     * @param array $metadata Additional context array (will be strictly sanitized)
     * @param int|null $user_id Performing user ID (defaults to active session user if NULL)
     * @return int|bool Insert ID on success, or FALSE on failure
     */
    public function log($action, $entity_type, $entity_id, $details, array $metadata = array(), $user_id = NULL)
    {
        // 1. Resolve user ID
        if ($user_id === NULL) {
            if (isset($this->session) && $this->session->userdata('user_id')) {
                $user_id = (int)$this->session->userdata('user_id');
            }
        } else {
            $user_id = (int)$user_id > 0 ? (int)$user_id : NULL;
        }

        // 2. Sanitize metadata
        $safe_metadata = self::sanitize_metadata($metadata);
        $metadata_json = !empty($safe_metadata) ? json_encode($safe_metadata, JSON_UNESCAPED_UNICODE) : NULL;

        // 3. Resolve IP and user agent
        $ip_address = NULL;
        $user_agent = NULL;
        if (isset($this->input)) {
            $ip_address = $this->input->ip_address();
            $user_agent = substr((string)$this->input->user_agent(), 0, 255);
        }

        // 4. Prepare insert row
        $insert_data = array(
            'user_id'     => $user_id,
            'action'      => substr(trim((string)$action), 0, 50),
            'entity_type' => substr(trim((string)$entity_type), 0, 50),
            'entity_id'   => ($entity_id !== NULL && (int)$entity_id > 0) ? (int)$entity_id : NULL,
            'details'     => trim((string)$details),
            'metadata'    => $metadata_json,
            'ip_address'  => $ip_address,
            'user_agent'  => $user_agent,
            'created_at'  => date('Y-m-d H:i:s')
        );

        // 5. Insert row into database
        $success = $this->db->insert($this->table, $insert_data);
        if ($success === FALSE || $this->db->affected_rows() <= 0) {
            return FALSE;
        }

        return $this->db->insert_id();
    }

    /**
     * Query audit logs with pagination and filters (Read-only for Admin)
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_logs_paged(array $filters = array(), $limit = 20, $offset = 0)
    {
        $this->db->select('al.*, u.username, u.full_name, r.name as role_name');
        $this->db->from($this->table . ' al');
        $this->db->join('users u', 'u.id = al.user_id', 'left');
        $this->db->join('roles r', 'r.id = u.role_id', 'left');

        $this->apply_filters($filters);

        $this->db->order_by('al.id', 'DESC');
        $this->db->limit((int)$limit, (int)$offset);

        return $this->db->get()->result();
    }

    /**
     * Count total audit logs matching filters
     *
     * @param array $filters
     * @return int
     */
    public function count_logs(array $filters = array())
    {
        $this->db->from($this->table . ' al');
        $this->db->join('users u', 'u.id = al.user_id', 'left');

        $this->apply_filters($filters);

        return (int)$this->db->count_all_results();
    }

    /**
     * Retrieve single audit log entry by ID
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        $this->db->select('al.*, u.username, u.full_name, r.name as role_name');
        $this->db->from($this->table . ' al');
        $this->db->join('users u', 'u.id = al.user_id', 'left');
        $this->db->join('roles r', 'r.id = u.role_id', 'left');
        $this->db->where('al.id', (int)$id);

        return $this->db->get()->row();
    }

    /**
     * Apply filter criteria to query builder
     *
     * @param array $filters
     * @return void
     */
    protected function apply_filters(array $filters)
    {
        if (!empty($filters['action'])) {
            $this->db->where('al.action', $filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $this->db->where('al.entity_type', $filters['entity_type']);
        }

        if (!empty($filters['user_id'])) {
            $this->db->where('al.user_id', (int)$filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $this->db->where('al.created_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $this->db->where('al.created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('al.details', $term);
            $this->db->or_like('u.username', $term);
            $this->db->or_like('u.full_name', $term);
            $this->db->or_where('al.entity_id', (int)$term);
            $this->db->group_end();
        }
    }

    /**
     * Get distinct actions currently logged
     *
     * @return array
     */
    public function get_distinct_actions()
    {
        $query = $this->db->select('action')
            ->distinct()
            ->from($this->table)
            ->order_by('action', 'ASC')
            ->get();
        return array_column($query->result_array(), 'action');
    }

    /**
     * Get distinct entity types currently logged
     *
     * @return array
     */
    public function get_distinct_entity_types()
    {
        $query = $this->db->select('entity_type')
            ->distinct()
            ->from($this->table)
            ->order_by('entity_type', 'ASC')
            ->get();
        return array_column($query->result_array(), 'entity_type');
    }
}

