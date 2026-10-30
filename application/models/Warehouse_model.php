<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Warehouse_model extends MY_Model
{
    protected $table = 'warehouses';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Normalize warehouse code (trim and uppercase)
     *
     * @param string $code
     * @return string
     */
    public function normalize_code($code)
    {
        return strtoupper(trim((string)$code));
    }

    /**
     * Get all warehouses
     *
     * @param bool $include_inactive
     * @return array
     */
    public function get_all($include_inactive = TRUE)
    {
        $this->db->select('w.*, COUNT(uw.user_id) AS assigned_users_count')
            ->from($this->table . ' w')
            ->join('user_warehouses uw', 'uw.warehouse_id = w.id', 'left')
            ->group_by('w.id')
            ->order_by('w.name', 'ASC');

        if (!$include_inactive) {
            $this->db->where('w.is_active', 1);
        }

        return $this->db->get()->result();
    }

    /**
     * Count warehouses matching filters
     *
     * @param string|null $search
     * @param int|null $status
     * @return int
     */
    public function count_warehouses($search = NULL, $status = NULL)
    {
        $this->db->from($this->table);

        if ($search !== NULL && trim($search) !== '') {
            $this->db->group_start();
            $this->db->like('name', trim($search));
            $this->db->or_like('code', trim($search));
            $this->db->or_like('location', trim($search));
            $this->db->group_end();
        }

        if ($status !== NULL && $status !== '') {
            $this->db->where('is_active', (int)$status);
        }

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated warehouses with assigned users count
     *
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @param int|null $status
     * @return array
     */
    public function get_warehouses_paged($limit, $offset, $search = NULL, $status = NULL)
    {
        $this->db->select('w.*, COUNT(uw.user_id) AS assigned_users_count')
            ->from($this->table . ' w')
            ->join('user_warehouses uw', 'uw.warehouse_id = w.id', 'left')
            ->group_by('w.id')
            ->order_by('w.id', 'DESC');

        if ($search !== NULL && trim($search) !== '') {
            $this->db->group_start();
            $this->db->like('w.name', trim($search));
            $this->db->or_like('w.code', trim($search));
            $this->db->or_like('w.location', trim($search));
            $this->db->group_end();
        }

        if ($status !== NULL && $status !== '') {
            $this->db->where('w.is_active', (int)$status);
        }

        return $this->db->limit((int)$limit, (int)$offset)->get()->result();
    }

    /**
     * Get active warehouses
     *
     * @return array
     */
    public function get_active()
    {
        return $this->db->from($this->table)
            ->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get()
            ->result();
    }

    /**
     * Get warehouse by ID
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        return $this->db->select('w.*, COUNT(uw.user_id) AS assigned_users_count')
            ->from($this->table . ' w')
            ->join('user_warehouses uw', 'uw.warehouse_id = w.id', 'left')
            ->where('w.id', (int)$id)
            ->group_by('w.id')
            ->get()
            ->row();
    }

    /**
     * Get warehouse by code
     *
     * @param string $code
     * @return object|null
     */
    public function get_by_code($code)
    {
        $clean = $this->normalize_code($code);
        return $this->db->from($this->table)
            ->where('code', $clean)
            ->get()
            ->row();
    }

    /**
     * Check if warehouse code exists
     *
     * @param string $code
     * @param int|null $exclude_id
     * @return bool
     */
    public function code_exists($code, $exclude_id = NULL)
    {
        $clean = $this->normalize_code($code);
        if ($clean === '') {
            return FALSE;
        }

        $this->db->from($this->table)->where('code', $clean);

        if ($exclude_id !== NULL) {
            $this->db->where('id !=', (int)$exclude_id);
        }

        return ($this->db->count_all_results() > 0);
    }

    /**
     * Create warehouse
     *
     * @param array $data
     * @return int Insert ID
     */
    public function create(array $data)
    {
        $insert_data = array(
            'name'        => trim($data['name']),
            'code'        => $this->normalize_code($data['code']),
            'location'    => isset($data['location']) && trim($data['location']) !== '' ? trim($data['location']) : NULL,
            'is_active'   => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => NULL,
        );

        $this->db->insert($this->table, $insert_data);
        return $this->db->insert_id();
    }

    /**
     * Update warehouse
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data)
    {
        $update_data = array(
            'updated_at' => date('Y-m-d H:i:s'),
        );

        if (isset($data['name'])) {
            $update_data['name'] = trim($data['name']);
        }
        if (isset($data['code'])) {
            $update_data['code'] = $this->normalize_code($data['code']);
        }
        if (array_key_exists('location', $data)) {
            $update_data['location'] = ($data['location'] !== NULL && trim($data['location']) !== '') ? trim($data['location']) : NULL;
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
        }

        return $this->db->where('id', (int)$id)->update($this->table, $update_data);
    }

    /**
     * Toggle active/inactive status (Soft disable)
     *
     * @param int $id
     * @return bool
     */
    public function toggle_status($id)
    {
        $warehouse = $this->db->where('id', (int)$id)->get($this->table)->row();
        if (!$warehouse) {
            return FALSE;
        }

        $new_status = ((int)$warehouse->is_active === 1) ? 0 : 1;

        return $this->db->where('id', (int)$id)->update($this->table, array(
            'is_active'  => $new_status,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }
}
