<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Category_model extends MY_Model
{
    protected $table = 'categories';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all categories
     *
     * @param bool $include_inactive
     * @return array
     */
    public function get_all($include_inactive = TRUE)
    {
        $this->db->select('c.*, COUNT(p.id) AS products_count')
            ->from($this->table . ' c')
            ->join('products p', 'p.category_id = c.id', 'left')
            ->group_by('c.id')
            ->order_by('c.name', 'ASC');

        if (!$include_inactive) {
            $this->db->where('c.is_active', 1);
        }

        return $this->db->get()->result();
    }

    /**
     * Count categories matching filters
     *
     * @param string|null $search
     * @param int|null $status
     * @return int
     */
    public function count_categories($search = NULL, $status = NULL)
    {
        $this->db->from($this->table);

        if ($search !== NULL && trim($search) !== '') {
            $this->db->group_start();
            $this->db->like('name', trim($search));
            $this->db->or_like('description', trim($search));
            $this->db->group_end();
        }

        if ($status !== NULL && $status !== '') {
            $this->db->where('is_active', (int)$status);
        }

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated categories with product counts and filters
     *
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @param int|null $status
     * @return array
     */
    public function get_categories_paged($limit, $offset, $search = NULL, $status = NULL)
    {
        $this->db->select('c.*, COUNT(p.id) AS products_count')
            ->from($this->table . ' c')
            ->join('products p', 'p.category_id = c.id', 'left')
            ->group_by('c.id')
            ->order_by('c.id', 'DESC');

        if ($search !== NULL && trim($search) !== '') {
            $this->db->group_start();
            $this->db->like('c.name', trim($search));
            $this->db->or_like('c.description', trim($search));
            $this->db->group_end();
        }

        if ($status !== NULL && $status !== '') {
            $this->db->where('c.is_active', (int)$status);
        }

        return $this->db->limit((int)$limit, (int)$offset)->get()->result();
    }

    /**
     * Get only active categories
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
     * Get category by ID
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        return $this->db->select('c.*, COUNT(p.id) AS products_count')
            ->from($this->table . ' c')
            ->join('products p', 'p.category_id = c.id', 'left')
            ->where('c.id', (int)$id)
            ->group_by('c.id')
            ->get()
            ->row();
    }

    /**
     * Check if category name exists
     *
     * @param string $name
     * @param int|null $exclude_id
     * @return bool
     */
    public function name_exists($name, $exclude_id = NULL)
    {
        $clean = trim((string)$name);
        $this->db->from($this->table)->where('name', $clean);

        if ($exclude_id !== NULL) {
            $this->db->where('id !=', (int)$exclude_id);
        }

        return ($this->db->count_all_results() > 0);
    }

    /**
     * Create category
     *
     * @param array $data
     * @return int Insert ID
     */
    public function create(array $data)
    {
        $insert_data = array(
            'name'        => trim($data['name']),
            'description' => isset($data['description']) && trim($data['description']) !== '' ? trim($data['description']) : NULL,
            'is_active'   => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => NULL,
        );

        $this->db->insert($this->table, $insert_data);
        $insert_id = $this->db->insert_id();
        if ($insert_id > 0) {
            if (!isset($this->Audit_model)) {
                $this->load->model('Audit_model');
            }
            $this->Audit_model->log('category_create', 'category', $insert_id, "إضافة تصنيف جديد: {$insert_data['name']}", $insert_data);
        }
        return $insert_id;
    }

    /**
     * Update category
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
        if (array_key_exists('description', $data)) {
            $update_data['description'] = ($data['description'] !== NULL && trim($data['description']) !== '') ? trim($data['description']) : NULL;
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
        }

        $res = $this->db->where('id', (int)$id)->update($this->table, $update_data);
        if ($res) {
            if (!isset($this->Audit_model)) {
                $this->load->model('Audit_model');
            }
            $this->Audit_model->log('category_update', 'category', (int)$id, "تعديل بيانات التصنيف #{$id}", $update_data);
        }
        return $res;
    }

    /**
     * Toggle active/inactive status (Soft disable - no deletion or automatic transfer of products)
     *
     * @param int $id
     * @return bool
     */
    public function toggle_status($id)
    {
        $category = $this->db->where('id', (int)$id)->get($this->table)->row();
        if (!$category) {
            return FALSE;
        }

        $new_status = ((int)$category->is_active === 1) ? 0 : 1;

        $res = $this->db->where('id', (int)$id)->update($this->table, array(
            'is_active'  => $new_status,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
        if ($res) {
            if (!isset($this->Audit_model)) {
                $this->load->model('Audit_model');
            }
            $action = $new_status ? 'category_activate' : 'category_deactivate';
            $details = $new_status ? "تفعيل التصنيف #{$id} ({$category->name})" : "تعطيل التصنيف #{$id} ({$category->name})";
            $this->Audit_model->log($action, 'category', (int)$id, $details, array('is_active' => $new_status));
        }
        return $res;
    }

    /**
     * Count products assigned to this category
     *
     * @param int $category_id
     * @return int
     */
    public function count_products($category_id)
    {
        return (int)$this->db->from('products')
            ->where('category_id', (int)$category_id)
            ->count_all_results();
    }
}
