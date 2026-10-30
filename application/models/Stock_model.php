<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stock_model — Centralized Warehouse Stock Data Access & Business Rules
 *
 * Handles warehouse stock records, reorder levels, shortage calculations,
 * and scoped access by user authorization.
 */
class Stock_model extends MY_Model
{
    protected $table = 'warehouse_stock';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get or lazy-initialize stock record for a product in a warehouse
     * Guarantees atomic non-duplicate creation with default 0 values.
     *
     * @param int $warehouse_id
     * @param int $product_id
     * @return object|null
     */
    public function get_or_create($warehouse_id, $product_id)
    {
        $warehouse_id = (int)$warehouse_id;
        $product_id   = (int)$product_id;

        if ($warehouse_id <= 0 || $product_id <= 0) {
            return NULL;
        }

        $row = $this->db->where('warehouse_id', $warehouse_id)
            ->where('product_id', $product_id)
            ->get($this->table)
            ->row();

        if ($row) {
            return $row;
        }

        // Verify product and warehouse exist before creating stock row
        $p_exists = $this->db->where('id', $product_id)->count_all_results('products');
        $w_exists = $this->db->where('id', $warehouse_id)->count_all_results('warehouses');

        if (!$p_exists || !$w_exists) {
            return NULL;
        }

        // Insert new record with 0 quantity and 0 reorder_level
        $data = array(
            'warehouse_id'  => $warehouse_id,
            'product_id'    => $product_id,
            'quantity'      => 0,
            'reorder_level' => 0,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => NULL,
        );

        // Ignore duplicate insertion if another process created it concurrently
        $this->db->insert($this->table, $data);

        return $this->db->where('warehouse_id', $warehouse_id)
            ->where('product_id', $product_id)
            ->get($this->table)
            ->row();
    }

    /**
     * Get stock record for a product in a warehouse (read-only)
     *
     * @param int $warehouse_id
     * @param int $product_id
     * @return object|null
     */
    public function get_stock($warehouse_id, $product_id)
    {
        return $this->db->where('warehouse_id', (int)$warehouse_id)
            ->where('product_id', (int)$product_id)
            ->get($this->table)
            ->row();
    }

    /**
     * Read current stock quantity for product in warehouse
     *
     * @param int $warehouse_id
     * @param int $product_id
     * @return int
     */
    public function get_quantity($warehouse_id, $product_id)
    {
        $stock = $this->get_stock($warehouse_id, $product_id);
        return $stock ? (int)$stock->quantity : 0;
    }

    /**
     * Read current reorder level for product in warehouse
     *
     * @param int $warehouse_id
     * @param int $product_id
     * @return int
     */
    public function get_reorder_level($warehouse_id, $product_id)
    {
        $stock = $this->get_stock($warehouse_id, $product_id);
        return $stock ? (int)$stock->reorder_level : 0;
    }

    /**
     * Calculate stock shortage for a given quantity and reorder level
     * Formula: shortage = max(0, reorder_level - quantity)
     *
     * @param int $quantity
     * @param int $reorder_level
     * @return int
     */
    public function calculate_shortage($quantity, $reorder_level)
    {
        $diff = (int)$reorder_level - (int)$quantity;
        return ($diff > 0) ? $diff : 0;
    }

    /**
     * Update reorder level for a product in a warehouse
     *
     * @param int $warehouse_id
     * @param int $product_id
     * @param int $reorder_level
     * @return bool
     */
    public function update_reorder_level($warehouse_id, $product_id, $reorder_level)
    {
        $warehouse_id  = (int)$warehouse_id;
        $product_id    = (int)$product_id;
        $reorder_level = (int)$reorder_level;

        if ($reorder_level < 0) {
            return FALSE;
        }

        // Ensure record exists
        $this->get_or_create($warehouse_id, $product_id);

        return $this->db->where('warehouse_id', $warehouse_id)
            ->where('product_id', $product_id)
            ->update($this->table, array(
                'reorder_level' => $reorder_level,
                'updated_at'    => date('Y-m-d H:i:s'),
            ));
    }

    /**
     * Apply filter conditions to stock query
     *
     * @param string|null $search Product name or SKU
     * @param int|null $warehouse_id Filter by specific warehouse
     * @param array|null $allowed_warehouse_ids Warehouse scope for non-admin users
     * @return void
     */
    protected function apply_stock_filters($search = NULL, $warehouse_id = NULL, $allowed_warehouse_ids = NULL)
    {
        if ($search !== NULL && trim($search) !== '') {
            $search_clean = trim($search);
            $this->db->group_start();
            $this->db->like('p.name', $search_clean);
            $this->db->or_like('p.sku', $search_clean);
            $this->db->group_end();
        }

        if ($warehouse_id !== NULL && (int)$warehouse_id > 0) {
            $this->db->where('ws.warehouse_id', (int)$warehouse_id);
        }

        // Enforce user warehouse authorization scope if not admin
        if ($allowed_warehouse_ids !== NULL) {
            if (empty($allowed_warehouse_ids)) {
                // User has no assigned warehouses -> match nothing
                $this->db->where('1 = 0', NULL, FALSE);
            } else {
                $this->db->where_in('ws.warehouse_id', array_map('intval', $allowed_warehouse_ids));
            }
        }
    }

    /**
     * Count stock records matching filters (for pagination)
     *
     * @param string|null $search
     * @param int|null $warehouse_id
     * @param array|null $allowed_warehouse_ids
     * @return int
     */
    public function count_stock($search = NULL, $warehouse_id = NULL, $allowed_warehouse_ids = NULL)
    {
        $this->db->from($this->table . ' ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner');

        $this->apply_stock_filters($search, $warehouse_id, $allowed_warehouse_ids);

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated stock records matching filters with calculated shortage
     *
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @param int|null $warehouse_id
     * @param array|null $allowed_warehouse_ids
     * @return array
     */
    public function get_stock_paged($limit, $offset, $search = NULL, $warehouse_id = NULL, $allowed_warehouse_ids = NULL)
    {
        $this->db->select('ws.*, p.name AS product_name, p.sku AS product_sku, p.sale_price, p.is_active AS product_is_active, w.name AS warehouse_name, w.code AS warehouse_code, w.is_active AS warehouse_is_active')
            ->from($this->table . ' ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner');

        $this->apply_stock_filters($search, $warehouse_id, $allowed_warehouse_ids);

        $this->db->order_by('w.name', 'ASC')
            ->order_by('p.name', 'ASC')
            ->limit((int)$limit, (int)$offset);

        $rows = $this->db->get()->result();

        // Calculate dynamic shortage for each record
        foreach ($rows as &$row) {
            $row->shortage = $this->calculate_shortage($row->quantity, $row->reorder_level);
        }

        return $rows;
    }

    /**
     * Check if a low stock condition exists across allowed warehouses
     *
     * @param array|null $allowed_warehouse_ids
     * @return int Count of items where quantity <= reorder_level and reorder_level > 0
     */
    public function count_low_stock($allowed_warehouse_ids = NULL)
    {
        $this->db->from($this->table . ' ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->where('ws.reorder_level >', 0)
            ->where('ws.quantity <= ws.reorder_level', NULL, FALSE);

        if ($allowed_warehouse_ids !== NULL) {
            if (empty($allowed_warehouse_ids)) {
                return 0;
            }
            $this->db->where_in('ws.warehouse_id', array_map('intval', $allowed_warehouse_ids));
        }

        return (int)$this->db->count_all_results();
    }
}

