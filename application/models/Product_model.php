<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Product_model extends MY_Model
{
    protected $table = 'products';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Normalize product SKU (trim and uppercase)
     *
     * @param string $sku
     * @return string
     */
    public function normalize_sku($sku)
    {
        return strtoupper(trim((string)$sku));
    }

    /**
     * Check if SKU exists
     *
     * @param string $sku
     * @param int|null $exclude_id
     * @return bool
     */
    public function sku_exists($sku, $exclude_id = NULL)
    {
        $clean = $this->normalize_sku($sku);
        if ($clean === '') {
            return FALSE;
        }

        $this->db->from($this->table)->where('sku', $clean);

        if ($exclude_id !== NULL) {
            $this->db->where('id !=', (int)$exclude_id);
        }

        return ($this->db->count_all_results() > 0);
    }

    /**
     * Get product by ID with category details
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        return $this->db->select('p.*, c.name AS category_name, c.is_active AS category_is_active')
            ->from($this->table . ' p')
            ->join('categories c', 'c.id = p.category_id', 'inner')
            ->where('p.id', (int)$id)
            ->get()
            ->row();
    }

    /**
     * Get product by SKU with category details
     *
     * @param string $sku
     * @return object|null
     */
    public function get_by_sku($sku)
    {
        $clean = $this->normalize_sku($sku);
        if ($clean === '') {
            return NULL;
        }

        return $this->db->select('p.*, c.name AS category_name, c.is_active AS category_is_active')
            ->from($this->table . ' p')
            ->join('categories c', 'c.id = p.category_id', 'inner')
            ->where('p.sku', $clean)
            ->get()
            ->row();
    }

    /**
     * Build search & filter conditions for query
     *
     * @param string|null $search
     * @param int|null $category_id
     * @param int|null $status
     * @return void
     */
    protected function apply_filters($search = NULL, $category_id = NULL, $status = NULL)
    {
        if ($search !== NULL && trim($search) !== '') {
            $search_clean = trim($search);
            $this->db->group_start();
            $this->db->like('p.name', $search_clean);
            $this->db->or_like('p.sku', $search_clean);
            $this->db->group_end();
        }

        if ($category_id !== NULL && (int)$category_id > 0) {
            $this->db->where('p.category_id', (int)$category_id);
        }

        if ($status !== NULL && $status !== '') {
            $this->db->where('p.is_active', (int)$status);
        }
    }

    /**
     * Count products matching filters (for pagination)
     *
     * @param string|null $search
     * @param int|null $category_id
     * @param int|null $status
     * @return int
     */
    public function count_products($search = NULL, $category_id = NULL, $status = NULL)
    {
        $this->db->from($this->table . ' p')
            ->join('categories c', 'c.id = p.category_id', 'inner');

        $this->apply_filters($search, $category_id, $status);

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated products matching filters
     *
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @param int|null $category_id
     * @param int|null $status
     * @return array
     */
    public function get_products($limit = 10, $offset = 0, $search = NULL, $category_id = NULL, $status = NULL)
    {
        $this->db->select('p.*, c.name AS category_name, c.is_active AS category_is_active')
            ->from($this->table . ' p')
            ->join('categories c', 'c.id = p.category_id', 'inner');

        $this->apply_filters($search, $category_id, $status);

        $this->db->order_by('p.id', 'DESC')
            ->limit((int)$limit, (int)$offset);

        return $this->db->get()->result();
    }

    /**
     * Create product
     *
     * @param array $data
     * @return int Insert ID
     */
    public function create(array $data)
    {
        $sale_price = isset($data['sale_price']) ? (float)$data['sale_price'] : 0.00;
        if ($sale_price < 0) {
            $sale_price = 0.00;
        }

        $reorder_level = isset($data['reorder_level']) ? (int)$data['reorder_level'] : 0;
        if ($reorder_level < 0) {
            $reorder_level = 0;
        }

        $insert_data = array(
            'sku'           => $this->normalize_sku($data['sku']),
            'name'          => trim($data['name']),
            'description'   => isset($data['description']) && trim($data['description']) !== '' ? trim($data['description']) : NULL,
            'category_id'   => (int)$data['category_id'],
            'sale_price'    => number_format($sale_price, 2, '.', ''),
            'reorder_level' => $reorder_level,
            'is_active'     => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => NULL,
        );

        $this->db->insert($this->table, $insert_data);
        return $this->db->insert_id();
    }

    /**
     * Update product
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

        if (isset($data['sku'])) {
            $update_data['sku'] = $this->normalize_sku($data['sku']);
        }
        if (isset($data['name'])) {
            $update_data['name'] = trim($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $update_data['description'] = ($data['description'] !== NULL && trim($data['description']) !== '') ? trim($data['description']) : NULL;
        }
        if (isset($data['category_id'])) {
            $update_data['category_id'] = (int)$data['category_id'];
        }
        if (isset($data['sale_price'])) {
            $price = (float)$data['sale_price'];
            $update_data['sale_price'] = number_format(($price < 0 ? 0.00 : $price), 2, '.', '');
        }
        if (isset($data['reorder_level'])) {
            $level = (int)$data['reorder_level'];
            $update_data['reorder_level'] = ($level < 0 ? 0 : $level);
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
        $product = $this->db->where('id', (int)$id)->get($this->table)->row();
        if (!$product) {
            return FALSE;
        }

        $new_status = ((int)$product->is_active === 1) ? 0 : 1;

        return $this->db->where('id', (int)$id)->update($this->table, array(
            'is_active'  => $new_status,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }
}

