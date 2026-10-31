<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Report_model — F09 Reports, Business Insights & CSV Export
 *
 * Provides optimized, scoped queries and aggregations for:
 * 1. Warehouse Stock Balances Report
 * 2. Stock Movements Report
 * 3. Sales Summary Report (excluding cancelled invoices)
 * 4. Invoices Report (including active and cancelled with historical details)
 * 5. Low Stock Alert Report (quantity <= reorder_level)
 *
 * Compatibility: PHP 7.4+ / PHP 8.2, CodeIgniter 3.1.13, MariaDB 10.4.32
 */
class Report_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Validate date string format (YYYY-MM-DD)
     *
     * @param string|null $date_str
     * @return string|null Validated date or NULL
     */
    public function validate_date($date_str)
    {
        if ($date_str === NULL || trim((string)$date_str) === '') {
            return NULL;
        }

        $clean = trim((string)$date_str);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
            return NULL;
        }

        $parts = explode('-', $clean);
        if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            return NULL;
        }

        return $clean;
    }

    /**
     * Helper to apply warehouse scope constraint to query
     *
     * @param string $column (e.g. 'ws.warehouse_id' or 'sm.warehouse_id')
     * @param array|null $allowed_warehouse_ids
     * @return void
     */
    protected function apply_warehouse_scope($column, $allowed_warehouse_ids)
    {
        if ($allowed_warehouse_ids !== NULL) {
            if (empty($allowed_warehouse_ids)) {
                // User has no assigned warehouses -> guarantee zero records returned
                $this->db->where('1 = 0', NULL, FALSE);
            } else {
                $this->db->where_in($column, array_map('intval', $allowed_warehouse_ids));
            }
        }
    }

    /**
     * Helper to apply sales user scope constraint to query
     *
     * @param string $column (e.g. 'si.created_by')
     * @param int|null $user_id_scope
     * @return void
     */
    protected function apply_user_scope($column, $user_id_scope)
    {
        if ($user_id_scope !== NULL && (int)$user_id_scope > 0) {
            $this->db->where($column, (int)$user_id_scope);
        }
    }

    // =========================================================================
    // 1. STOCK BALANCES REPORT (3.1)
    // =========================================================================

    /**
     * Apply stock balance filters
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return void
     */
    protected function apply_stock_balances_filters(array $filters, $user_warehouse_scope)
    {
        $this->apply_warehouse_scope('ws.warehouse_id', $user_warehouse_scope);

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('ws.warehouse_id', (int)$filters['warehouse_id']);
        }

        if (!empty($filters['category_id'])) {
            $this->db->where('p.category_id', (int)$filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('p.name', $term);
            $this->db->or_like('p.sku', $term);
            $this->db->group_end();
        }
    }

    /**
     * Count total stock balances records
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return int
     */
    public function count_stock_balances(array $filters = array(), $user_warehouse_scope = NULL)
    {
        $this->db->from('warehouse_stock ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->join('categories c', 'c.id = p.category_id', 'left');

        $this->apply_stock_balances_filters($filters, $user_warehouse_scope);

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated stock balances records
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_stock_balances_paged(array $filters, $limit, $offset, $user_warehouse_scope = NULL)
    {
        $this->db->select("ws.id, ws.warehouse_id, ws.product_id, ws.quantity,
                           p.name AS product_name, p.sku AS product_sku, p.sale_price,
                           c.name AS category_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) AS reorder_level,
                           GREATEST(0, (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) - ws.quantity) AS shortage", FALSE)
            ->from('warehouse_stock ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->join('categories c', 'c.id = p.category_id', 'left');

        $this->apply_stock_balances_filters($filters, $user_warehouse_scope);

        $this->db->order_by('w.name', 'ASC')
            ->order_by('p.name', 'ASC')
            ->limit((int)$limit, (int)$offset);

        return $this->db->get()->result();
    }

    /**
     * Get all stock balances records matching filters (for CSV export)
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_all_stock_balances(array $filters = array(), $user_warehouse_scope = NULL)
    {
        $this->db->select("ws.id, ws.warehouse_id, ws.product_id, ws.quantity,
                           p.name AS product_name, p.sku AS product_sku, p.sale_price,
                           c.name AS category_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) AS reorder_level,
                           GREATEST(0, (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) - ws.quantity) AS shortage", FALSE)
            ->from('warehouse_stock ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->join('categories c', 'c.id = p.category_id', 'left');

        $this->apply_stock_balances_filters($filters, $user_warehouse_scope);

        $this->db->order_by('w.name', 'ASC')
            ->order_by('p.name', 'ASC');

        return $this->db->get()->result();
    }

    // =========================================================================
    // 2. STOCK MOVEMENTS REPORT (3.2)
    // =========================================================================

    /**
     * Apply stock movements filters
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return void
     */
    protected function apply_stock_movements_filters(array $filters, $user_warehouse_scope)
    {
        $this->apply_warehouse_scope('sm.warehouse_id', $user_warehouse_scope);

        if (!empty($filters['date_from'])) {
            $from = $this->validate_date($filters['date_from']);
            if ($from) {
                $this->db->where('sm.created_at >=', $from . ' 00:00:00');
            }
        }

        if (!empty($filters['date_to'])) {
            $to = $this->validate_date($filters['date_to']);
            if ($to) {
                $this->db->where('sm.created_at <=', $to . ' 23:59:59');
            }
        }

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('sm.warehouse_id', (int)$filters['warehouse_id']);
        }

        if (!empty($filters['product_id'])) {
            $this->db->where('sm.product_id', (int)$filters['product_id']);
        }

        if (!empty($filters['movement_type'])) {
            $allowed_types = array('in', 'adjustment', 'sale', 'sale_reversal');
            if (in_array($filters['movement_type'], $allowed_types, TRUE)) {
                $this->db->where('sm.movement_type', $filters['movement_type']);
            }
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('p.name', $term);
            $this->db->or_like('p.sku', $term);
            $this->db->group_end();
        }
    }

    /**
     * Count total stock movements matching filters
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return int
     */
    public function count_stock_movements(array $filters = array(), $user_warehouse_scope = NULL)
    {
        $this->db->from('stock_movements sm')
            ->join('products p', 'p.id = sm.product_id', 'inner')
            ->join('warehouses w', 'w.id = sm.warehouse_id', 'inner')
            ->join('users u', 'u.id = sm.created_by', 'left');

        $this->apply_stock_movements_filters($filters, $user_warehouse_scope);

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated stock movements records
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_stock_movements_paged(array $filters, $limit, $offset, $user_warehouse_scope = NULL)
    {
        $this->db->select("sm.id, sm.created_at, sm.movement_type, sm.quantity_delta,
                           sm.before_quantity, sm.after_quantity, sm.reason,
                           sm.sales_invoice_id, sm.reversal_of_movement_id,
                           p.name AS product_name, p.sku AS product_sku,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           u.full_name AS user_name, u.username,
                           si.invoice_number")
            ->from('stock_movements sm')
            ->join('products p', 'p.id = sm.product_id', 'inner')
            ->join('warehouses w', 'w.id = sm.warehouse_id', 'inner')
            ->join('users u', 'u.id = sm.created_by', 'left')
            ->join('sales_invoices si', 'si.id = sm.sales_invoice_id', 'left');

        $this->apply_stock_movements_filters($filters, $user_warehouse_scope);

        $this->db->order_by('sm.created_at', 'DESC')
            ->order_by('sm.id', 'DESC')
            ->limit((int)$limit, (int)$offset);

        return $this->db->get()->result();
    }

    /**
     * Get all stock movements matching filters (for CSV export)
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_all_stock_movements(array $filters = array(), $user_warehouse_scope = NULL)
    {
        $this->db->select("sm.id, sm.created_at, sm.movement_type, sm.quantity_delta,
                           sm.before_quantity, sm.after_quantity, sm.reason,
                           sm.sales_invoice_id, sm.reversal_of_movement_id,
                           p.name AS product_name, p.sku AS product_sku,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           u.full_name AS user_name, u.username,
                           si.invoice_number")
            ->from('stock_movements sm')
            ->join('products p', 'p.id = sm.product_id', 'inner')
            ->join('warehouses w', 'w.id = sm.warehouse_id', 'inner')
            ->join('users u', 'u.id = sm.created_by', 'left')
            ->join('sales_invoices si', 'si.id = sm.sales_invoice_id', 'left');

        $this->apply_stock_movements_filters($filters, $user_warehouse_scope);

        $this->db->order_by('sm.created_at', 'DESC')
            ->order_by('sm.id', 'DESC');

        return $this->db->get()->result();
    }

    // =========================================================================
    // 3. SALES SUMMARY REPORT (3.3)
    // Strictly excludes cancelled invoices and does not count reversals as sales.
    // =========================================================================

    /**
     * Apply active sales filters
     *
     * @param array $filters
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return void
     */
    protected function apply_sales_filters(array $filters, $user_id_scope, $user_warehouse_scope)
    {
        // STRICT: Exclude cancelled invoices from sales summary
        $this->db->where('si.status', 'active');

        $this->apply_warehouse_scope('si.warehouse_id', $user_warehouse_scope);
        $this->apply_user_scope('si.created_by', $user_id_scope);

        if (!empty($filters['date_from'])) {
            $from = $this->validate_date($filters['date_from']);
            if ($from) {
                $this->db->where('si.invoice_date >=', $from);
            }
        }

        if (!empty($filters['date_to'])) {
            $to = $this->validate_date($filters['date_to']);
            if ($to) {
                $this->db->where('si.invoice_date <=', $to);
            }
        }

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('si.warehouse_id', (int)$filters['warehouse_id']);
        }

        if (!empty($filters['customer_id'])) {
            $this->db->where('si.customer_id', (int)$filters['customer_id']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('si.invoice_number', $term);
            $this->db->or_like('c.name', $term);
            $this->db->group_end();
        }
    }

    /**
     * Get aggregate KPIs for sales summary (Gross Sales, Discounts, Net Sales, Count)
     *
     * @param array $filters
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return object
     */
    public function get_sales_kpis(array $filters = array(), $user_id_scope = NULL, $user_warehouse_scope = NULL)
    {
        $this->db->select("
            COUNT(si.id) AS invoices_count,
            COALESCE(SUM(si.subtotal), 0.00) AS gross_sales,
            COALESCE(SUM(si.discount_amount), 0.00) AS total_discounts,
            COALESCE(SUM(si.total_amount), 0.00) AS net_sales
        ", FALSE)
            ->from('sales_invoices si')
            ->join('customers c', 'c.id = si.customer_id', 'inner')
            ->join('warehouses w', 'w.id = si.warehouse_id', 'inner');

        $this->apply_sales_filters($filters, $user_id_scope, $user_warehouse_scope);

        $row = $this->db->get()->row();

        return (object)array(
            'invoices_count'  => $row ? (int)$row->invoices_count : 0,
            'gross_sales'     => $row ? (float)$row->gross_sales : 0.00,
            'total_discounts' => $row ? (float)$row->total_discounts : 0.00,
            'net_sales'       => $row ? (float)$row->net_sales : 0.00,
            'currency'        => 'SAR'
        );
    }

    /**
     * Count sales invoice records matching filters
     *
     * @param array $filters
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return int
     */
    public function count_sales(array $filters = array(), $user_id_scope = NULL, $user_warehouse_scope = NULL)
    {
        $this->db->from('sales_invoices si')
            ->join('customers c', 'c.id = si.customer_id', 'inner')
            ->join('warehouses w', 'w.id = si.warehouse_id', 'inner');

        $this->apply_sales_filters($filters, $user_id_scope, $user_warehouse_scope);

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated sales transactions for detailed breakdown
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_sales_paged(array $filters, $limit, $offset, $user_id_scope = NULL, $user_warehouse_scope = NULL)
    {
        $this->db->select("si.id, si.invoice_number, si.invoice_date, si.created_at,
                           si.subtotal, si.discount_amount, si.total_amount, si.currency_code,
                           c.name AS customer_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           u.full_name AS sales_user_name, u.username")
            ->from('sales_invoices si')
            ->join('customers c', 'c.id = si.customer_id', 'inner')
            ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
            ->join('users u', 'u.id = si.created_by', 'left');

        $this->apply_sales_filters($filters, $user_id_scope, $user_warehouse_scope);

        $this->db->order_by('si.invoice_date', 'DESC')
            ->order_by('si.id', 'DESC')
            ->limit((int)$limit, (int)$offset);

        return $this->db->get()->result();
    }

    /**
     * Get all sales transactions matching filters (for CSV export)
     *
     * @param array $filters
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_all_sales(array $filters = array(), $user_id_scope = NULL, $user_warehouse_scope = NULL)
    {
        $this->db->select("si.id, si.invoice_number, si.invoice_date, si.created_at,
                           si.subtotal, si.discount_amount, si.total_amount, si.currency_code,
                           c.name AS customer_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           u.full_name AS sales_user_name, u.username")
            ->from('sales_invoices si')
            ->join('customers c', 'c.id = si.customer_id', 'inner')
            ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
            ->join('users u', 'u.id = si.created_by', 'left');

        $this->apply_sales_filters($filters, $user_id_scope, $user_warehouse_scope);

        $this->db->order_by('si.invoice_date', 'DESC')
            ->order_by('si.id', 'DESC');

        return $this->db->get()->result();
    }

    // =========================================================================
    // 4. INVOICES REPORT (3.4)
    // Displays all invoices (active and cancelled) with full historical accuracy.
    // =========================================================================

    /**
     * Apply invoices report filters
     *
     * @param array $filters
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return void
     */
    protected function apply_invoices_filters(array $filters, $user_id_scope, $user_warehouse_scope)
    {
        $this->apply_warehouse_scope('si.warehouse_id', $user_warehouse_scope);
        $this->apply_user_scope('si.created_by', $user_id_scope);

        if (!empty($filters['date_from'])) {
            $from = $this->validate_date($filters['date_from']);
            if ($from) {
                $this->db->where('si.invoice_date >=', $from);
            }
        }

        if (!empty($filters['date_to'])) {
            $to = $this->validate_date($filters['date_to']);
            if ($to) {
                $this->db->where('si.invoice_date <=', $to);
            }
        }

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('si.warehouse_id', (int)$filters['warehouse_id']);
        }

        if (!empty($filters['customer_id'])) {
            $this->db->where('si.customer_id', (int)$filters['customer_id']);
        }

        if (!empty($filters['status']) && in_array($filters['status'], array('active', 'cancelled'), TRUE)) {
            $this->db->where('si.status', $filters['status']);
        }

        if (!empty($filters['created_by']) && (int)$filters['created_by'] > 0) {
            $this->db->where('si.created_by', (int)$filters['created_by']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('si.invoice_number', $term);
            $this->db->or_like('c.name', $term);
            $this->db->group_end();
        }
    }

    /**
     * Count invoices matching filters
     *
     * @param array $filters
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return int
     */
    public function count_invoices(array $filters = array(), $user_id_scope = NULL, $user_warehouse_scope = NULL)
    {
        $this->db->from('sales_invoices si')
            ->join('customers c', 'c.id = si.customer_id', 'inner')
            ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
            ->join('users u', 'u.id = si.created_by', 'left');

        $this->apply_invoices_filters($filters, $user_id_scope, $user_warehouse_scope);

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated invoices report records
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_invoices_paged(array $filters, $limit, $offset, $user_id_scope = NULL, $user_warehouse_scope = NULL)
    {
        $this->db->select("si.id, si.invoice_number, si.invoice_date, si.created_at,
                           si.status, si.subtotal, si.discount_amount, si.total_amount, si.currency_code,
                           si.cancelled_at, si.cancellation_reason,
                           c.name AS customer_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           u.full_name AS created_by_name, u.username,
                           cu.full_name AS cancelled_by_name")
            ->from('sales_invoices si')
            ->join('customers c', 'c.id = si.customer_id', 'inner')
            ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
            ->join('users u', 'u.id = si.created_by', 'left')
            ->join('users cu', 'cu.id = si.cancelled_by', 'left');

        $this->apply_invoices_filters($filters, $user_id_scope, $user_warehouse_scope);

        $this->db->order_by('si.invoice_date', 'DESC')
            ->order_by('si.id', 'DESC')
            ->limit((int)$limit, (int)$offset);

        return $this->db->get()->result();
    }

    /**
     * Get all invoices matching filters (for CSV export)
     *
     * @param array $filters
     * @param int|null $user_id_scope
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_all_invoices(array $filters = array(), $user_id_scope = NULL, $user_warehouse_scope = NULL)
    {
        $this->db->select("si.id, si.invoice_number, si.invoice_date, si.created_at,
                           si.status, si.subtotal, si.discount_amount, si.total_amount, si.currency_code,
                           si.cancelled_at, si.cancellation_reason,
                           c.name AS customer_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           u.full_name AS created_by_name, u.username,
                           cu.full_name AS cancelled_by_name")
            ->from('sales_invoices si')
            ->join('customers c', 'c.id = si.customer_id', 'inner')
            ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
            ->join('users u', 'u.id = si.created_by', 'left')
            ->join('users cu', 'cu.id = si.cancelled_by', 'left');

        $this->apply_invoices_filters($filters, $user_id_scope, $user_warehouse_scope);

        $this->db->order_by('si.invoice_date', 'DESC')
            ->order_by('si.id', 'DESC');

        return $this->db->get()->result();
    }

    // =========================================================================
    // 5. LOW STOCK ALERT REPORT (3.5)
    // Products where quantity <= reorder_level
    // =========================================================================

    /**
     * Apply low stock filters
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return void
     */
    protected function apply_low_stock_filters(array $filters, $user_warehouse_scope)
    {
        $this->apply_warehouse_scope('ws.warehouse_id', $user_warehouse_scope);

        // Core Low Stock condition: quantity <= reorder_level AND reorder_level > 0
        $effective_reorder = '(CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END)';
        $this->db->where("ws.quantity <= {$effective_reorder}", NULL, FALSE);
        $this->db->where("{$effective_reorder} > 0", NULL, FALSE);

        if (!empty($filters['warehouse_id'])) {
            $this->db->where('ws.warehouse_id', (int)$filters['warehouse_id']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $this->db->group_start();
            $this->db->like('p.name', $term);
            $this->db->or_like('p.sku', $term);
            $this->db->group_end();
        }
    }

    /**
     * Count low stock records
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return int
     */
    public function count_low_stock(array $filters = array(), $user_warehouse_scope = NULL)
    {
        $this->db->from('warehouse_stock ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->join('categories c', 'c.id = p.category_id', 'left');

        $this->apply_low_stock_filters($filters, $user_warehouse_scope);

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated low stock records
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_low_stock_paged(array $filters, $limit, $offset, $user_warehouse_scope = NULL)
    {
        $this->db->select("ws.id, ws.warehouse_id, ws.product_id, ws.quantity,
                           p.name AS product_name, p.sku AS product_sku, p.sale_price,
                           c.name AS category_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) AS reorder_level,
                           GREATEST(0, (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) - ws.quantity) AS shortage", FALSE)
            ->from('warehouse_stock ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->join('categories c', 'c.id = p.category_id', 'left');

        $this->apply_low_stock_filters($filters, $user_warehouse_scope);

        $this->db->order_by('shortage', 'DESC')
            ->order_by('ws.quantity', 'ASC')
            ->order_by('p.name', 'ASC')
            ->limit((int)$limit, (int)$offset);

        return $this->db->get()->result();
    }

    /**
     * Get all low stock records matching filters (for CSV export)
     *
     * @param array $filters
     * @param array|null $user_warehouse_scope
     * @return array
     */
    public function get_all_low_stock(array $filters = array(), $user_warehouse_scope = NULL)
    {
        $this->db->select("ws.id, ws.warehouse_id, ws.product_id, ws.quantity,
                           p.name AS product_name, p.sku AS product_sku, p.sale_price,
                           c.name AS category_name,
                           w.name AS warehouse_name, w.code AS warehouse_code,
                           (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) AS reorder_level,
                           GREATEST(0, (CASE WHEN ws.reorder_level > 0 THEN ws.reorder_level ELSE p.reorder_level END) - ws.quantity) AS shortage", FALSE)
            ->from('warehouse_stock ws')
            ->join('products p', 'p.id = ws.product_id', 'inner')
            ->join('warehouses w', 'w.id = ws.warehouse_id', 'inner')
            ->join('categories c', 'c.id = p.category_id', 'left');

        $this->apply_low_stock_filters($filters, $user_warehouse_scope);

        $this->db->order_by('shortage', 'DESC')
            ->order_by('ws.quantity', 'ASC')
            ->order_by('p.name', 'ASC');

        return $this->db->get()->result();
    }
}

