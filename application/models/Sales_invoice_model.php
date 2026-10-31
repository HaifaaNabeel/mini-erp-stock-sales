<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sales_invoice_model — F07 Sales Invoices Data Layer & Atomic Operations
 *
 * Implements atomic invoice creation, daily sequential numbering (INV-YYYYMMDD-XXXX),
 * stock deduction with atomic stock movements ('sale'), server-side financial calculations
 * with PHP_ROUND_HALF_UP, and strict role-based / warehouse-based access scoping.
 *
 * All database operations isolate db_debug using try/finally blocks.
 *
 * PHP Compatibility: 7.4+
 */
class Sales_invoice_model extends MY_Model
{
    protected $table = 'sales_invoices';
    protected $items_table = 'sales_invoice_items';
    protected $sequence_table = 'invoice_sequences';
    protected $stock_table = 'warehouse_stock';
    protected $movement_table = 'stock_movements';

    const MAX_UNSIGNED_INT = 4294967295;
    const MAX_DAILY_SEQUENCE = 9999;

    public function __construct()
    {
        parent::__construct();
        if (!isset($this->Customer_model)) {
            $this->load->model('Customer_model');
        }
        if (!isset($this->User_warehouse_model)) {
            $this->load->model('User_warehouse_model');
        }
    }

    /**
     * Get invoice by ID with customer, warehouse, and creator details
     *
     * @param mixed $id
     * @return object|null|false
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
            $query = $this->db->select('si.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, w.name AS warehouse_name, w.code AS warehouse_code, u.full_name AS created_by_name, u.username AS created_by_username, cu.full_name AS cancelled_by_name')
                ->from($this->table . ' si')
                ->join('customers c', 'c.id = si.customer_id', 'inner')
                ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
                ->join('users u', 'u.id = si.created_by', 'inner')
                ->join('users cu', 'cu.id = si.cancelled_by', 'left')
                ->where('si.id', $valid_id)
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
     * Get invoice by invoice number
     *
     * @param string $invoice_number
     * @return object|null|false
     */
    public function get_by_invoice_number($invoice_number)
    {
        $clean_number = trim((string)$invoice_number);
        if ($clean_number === '') {
            return FALSE;
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $query = $this->db->select('si.*, c.name AS customer_name, c.phone AS customer_phone, w.name AS warehouse_name, w.code AS warehouse_code, u.full_name AS created_by_name')
                ->from($this->table . ' si')
                ->join('customers c', 'c.id = si.customer_id', 'inner')
                ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
                ->join('users u', 'u.id = si.created_by', 'inner')
                ->where('si.invoice_number', $clean_number)
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
     * Get invoice by idempotency key
     *
     * @param string $idempotency_key
     * @return object|null|false
     */
    public function get_by_idempotency_key($idempotency_key)
    {
        $clean_key = trim((string)$idempotency_key);
        if ($clean_key === '') {
            return FALSE;
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $query = $this->db->where('idempotency_key', $clean_key)->get($this->table);

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
     * Get line items for an invoice
     *
     * @param mixed $invoice_id
     * @return array|false Array of item objects or FALSE on failure
     */
    public function get_items_by_invoice_id($invoice_id)
    {
        $valid_id = filter_var($invoice_id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
        ));

        if ($valid_id === FALSE) {
            return FALSE;
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $query = $this->db->select('sii.*, p.sku AS current_product_sku, p.name AS current_product_name')
                ->from($this->items_table . ' sii')
                ->join('products p', 'p.id = sii.product_id', 'left')
                ->where('sii.invoice_id', $valid_id)
                ->order_by('sii.id', 'ASC')
                ->get();

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
     * Get active products with current stock in a specific warehouse
     *
     * @param int $warehouse_id
     * @return array
     */
    public function get_products_with_stock($warehouse_id)
    {
        $warehouse_id = (int)$warehouse_id;
        if ($warehouse_id <= 0) {
            return array();
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $query = $this->db->select('p.id, p.sku, p.name, p.sale_price, COALESCE(ws.quantity, 0) AS stock_quantity')
                ->from('products p')
                ->join('warehouse_stock ws', 'ws.product_id = p.id AND ws.warehouse_id = ' . $warehouse_id, 'left')
                ->where('p.is_active', 1)
                ->order_by('p.name', 'ASC')
                ->get();

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
     * Count invoices matching filters and user role scoping
     *
     * @param array $filters
     * @param int|null $user_id
     * @param string $role_name
     * @param array|null $allowed_warehouse_ids
     * @return int|false
     */
    public function count_invoices($filters = array(), $user_id = NULL, $role_name = 'admin', $allowed_warehouse_ids = NULL)
    {
        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->from($this->table . ' si')
                ->join('customers c', 'c.id = si.customer_id', 'inner');

            $scoped = $this->apply_filters_and_scoping($filters, $user_id, $role_name, $allowed_warehouse_ids);
            if ($scoped === FALSE) {
                $this->db->reset_query();
                return 0;
            }

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
     * Get paginated invoices with customer and warehouse details, scoped by role
     *
     * @param int $limit
     * @param int $offset
     * @param array $filters
     * @param int|null $user_id
     * @param string $role_name
     * @param array|null $allowed_warehouse_ids
     * @return array|false
     */
    public function get_invoices_paged($limit, $offset, $filters = array(), $user_id = NULL, $role_name = 'admin', $allowed_warehouse_ids = NULL)
    {
        $limit_val  = max(1, (int)$limit);
        $offset_val = max(0, (int)$offset);

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->select('si.*, c.name AS customer_name, c.phone AS customer_phone, w.name AS warehouse_name, w.code AS warehouse_code, u.full_name AS created_by_name')
                ->from($this->table . ' si')
                ->join('customers c', 'c.id = si.customer_id', 'inner')
                ->join('warehouses w', 'w.id = si.warehouse_id', 'inner')
                ->join('users u', 'u.id = si.created_by', 'inner');

            $scoped = $this->apply_filters_and_scoping($filters, $user_id, $role_name, $allowed_warehouse_ids);
            if ($scoped === FALSE) {
                $this->db->reset_query();
                return array();
            }

            $this->db->order_by('si.id', 'DESC');
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
     * Apply role-based scoping and search/filter criteria
     *
     * @param array $filters
     * @param int|null $user_id
     * @param string $role_name
     * @param array|null $allowed_warehouse_ids
     * @return bool FALSE if role has zero access, TRUE otherwise
     */
    protected function apply_filters_and_scoping($filters = array(), $user_id = NULL, $role_name = 'admin', $allowed_warehouse_ids = NULL)
    {
        // 1. Role-based scoping
        if ($role_name === 'admin') {
            // Admin can access all invoices
            if (!empty($filters['warehouse_id'])) {
                $this->db->where('si.warehouse_id', (int)$filters['warehouse_id']);
            }
        } elseif ($role_name === 'sales') {
            // Sales user can only see invoices created by themselves AND in their assigned warehouses
            if (empty($user_id)) {
                return FALSE;
            }

            $this->db->where('si.created_by', (int)$user_id);

            if ($allowed_warehouse_ids === NULL) {
                $wh_q = $this->db->query("SELECT warehouse_id FROM user_warehouses WHERE user_id = ?", array((int)$user_id));
                $allowed_warehouse_ids = array();
                if ($wh_q !== FALSE) {
                    foreach ($wh_q->result_array() as $r) {
                        $allowed_warehouse_ids[] = (int)$r['warehouse_id'];
                    }
                }
            }

            if (empty($allowed_warehouse_ids)) {
                return FALSE;
            }

            if (!empty($filters['warehouse_id'])) {
                $filter_wh = (int)$filters['warehouse_id'];
                if (!in_array($filter_wh, $allowed_warehouse_ids, TRUE)) {
                    return FALSE;
                }
                $this->db->where('si.warehouse_id', $filter_wh);
            } else {
                $this->db->where_in('si.warehouse_id', $allowed_warehouse_ids);
            }
        } else {
            // Warehouse role or unrecognized role is forbidden from sales invoices
            return FALSE;
        }

        // 2. Search filter (invoice number, customer name, customer phone)
        if (!empty($filters['search'])) {
            $clean_search = trim((string)$filters['search']);
            if ($clean_search !== '') {
                $this->db->group_start();
                $this->db->like('si.invoice_number', $clean_search);
                $this->db->or_like('c.name', $clean_search);
                $this->db->or_like('c.phone', $clean_search);
                $this->db->group_end();
            }
        }

        // 3. Customer ID filter
        if (!empty($filters['customer_id'])) {
            $this->db->where('si.customer_id', (int)$filters['customer_id']);
        }

        // 4. Status filter
        if (!empty($filters['status']) && in_array($filters['status'], array('active', 'cancelled'), TRUE)) {
            $this->db->where('si.status', $filters['status']);
        }

        // 5. Date range filters
        if (!empty($filters['start_date'])) {
            $this->db->where('si.invoice_date >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $this->db->where('si.invoice_date <=', $filters['end_date']);
        }

        return TRUE;
    }

    /**
     * Create Sales Invoice atomically within an ACID transaction
     *
     * Validates customer, warehouse, user permissions, non-empty items,
     * locks warehouse and products FOR UPDATE, verifies and locks stock,
     * calculates totals with PHP_ROUND_HALF_UP, generates atomic daily invoice number,
     * inserts invoice header and items, decrements stock, and inserts stock movements.
     *
     * @param array $invoice_data Invoice header attributes
     * @param array $items Array of line items: [['product_id' => int, 'quantity' => int, 'unit_price' => float|null]]
     * @param object|array $current_user Current authenticated user object or array
     * @return array
     */
    public function create_invoice($invoice_data, $items, $current_user)
    {
        // -------------------------------------------------------------
        // 1. Normalize and validate Current User & Role Authorization
        // -------------------------------------------------------------
        $user_id   = is_object($current_user) ? (int)$current_user->id : (isset($current_user['id']) ? (int)$current_user['id'] : 0);
        $role_name = is_object($current_user) ? $current_user->role_name : (isset($current_user['role_name']) ? $current_user['role_name'] : '');

        if ($user_id <= 0) {
            return array('success' => FALSE, 'error' => 'بيانات المستخدم غير صالحة.', 'error_code' => 'INVALID_USER');
        }

        if ($role_name === 'warehouse') {
            return array('success' => FALSE, 'error' => 'غير مصرح لمستخدم المستودع بإنشاء فواتير مبيعات.', 'error_code' => 'PERMISSION_DENIED');
        }

        if ($role_name !== 'admin' && $role_name !== 'sales') {
            return array('success' => FALSE, 'error' => 'الدور الحالي غير مصرح له بإنشاء فواتير مبيعات.', 'error_code' => 'PERMISSION_DENIED');
        }

        // -------------------------------------------------------------
        // 2. Validate Customer
        // -------------------------------------------------------------
        $customer_id = isset($invoice_data['customer_id']) ? filter_var($invoice_data['customer_id'], FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
        )) : FALSE;

        if ($customer_id === FALSE) {
            return array('success' => FALSE, 'error' => 'يجب تحديد عميل صالح.', 'error_code' => 'INVALID_CUSTOMER_ID');
        }

        $customer = $this->Customer_model->get_active_customer($customer_id);
        if (!$customer) {
            return array('success' => FALSE, 'error' => 'العميل المحدد غير موجود أو غير مفعل.', 'error_code' => 'INACTIVE_CUSTOMER');
        }

        // -------------------------------------------------------------
        // 3. Validate Warehouse & User Warehouse Assignment
        // -------------------------------------------------------------
        $warehouse_id = isset($invoice_data['warehouse_id']) ? filter_var($invoice_data['warehouse_id'], FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
        )) : FALSE;

        if ($warehouse_id === FALSE) {
            return array('success' => FALSE, 'error' => 'يجب تحديد مستودع صالح.', 'error_code' => 'INVALID_WAREHOUSE_ID');
        }

        if ($role_name === 'sales') {
            if (!$this->User_warehouse_model->is_assigned($user_id, $warehouse_id)) {
                return array('success' => FALSE, 'error' => 'غير مصرح لك بالبيع من هذا المستودع.', 'error_code' => 'UNAUTHORIZED_WAREHOUSE');
            }
        }

        // -------------------------------------------------------------
        // 4. Validate Invoice Date, Discount Percentage, Notes, Idempotency
        // -------------------------------------------------------------
        $invoice_date = !empty($invoice_data['invoice_date']) ? trim((string)$invoice_data['invoice_date']) : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice_date) || !strtotime($invoice_date)) {
            return array('success' => FALSE, 'error' => 'تاريخ الفاتورة غير صالح. الصيغة المطلوبة YYYY-MM-DD.', 'error_code' => 'INVALID_INVOICE_DATE');
        }

        $discount_pct = 0.00;
        if (isset($invoice_data['discount_percentage']) && $invoice_data['discount_percentage'] !== '') {
            if (!is_numeric($invoice_data['discount_percentage'])) {
                return array('success' => FALSE, 'error' => 'نسبة الخصم يجب أن تكون قيمة عددية.', 'error_code' => 'INVALID_DISCOUNT_PERCENTAGE');
            }
            $discount_pct = (float)$invoice_data['discount_percentage'];
            if ($discount_pct < 0.00 || $discount_pct > 100.00) {
                return array('success' => FALSE, 'error' => 'نسبة الخصم يجب أن تكون بين 0.00% و100.00%.', 'error_code' => 'DISCOUNT_OUT_OF_RANGE');
            }
        }
        $discount_pct = round($discount_pct, 2);

        $notes = !empty($invoice_data['notes']) ? trim((string)$invoice_data['notes']) : NULL;
        if ($notes !== NULL && mb_strlen($notes, 'UTF-8') > 255) {
            return array('success' => FALSE, 'error' => 'ملاحظات الفاتورة لا يجب أن تتجاوز 255 حرفاً.', 'error_code' => 'NOTES_TOO_LONG');
        }

        // Idempotency key
        $idempotency_key = !empty($invoice_data['idempotency_key']) ? trim((string)$invoice_data['idempotency_key']) : bin2hex(random_bytes(16));
        if (!preg_match('/^[a-zA-Z0-9_\-]{16,64}$/', $idempotency_key)) {
            return array('success' => FALSE, 'error' => 'رمز التحقق من تكرار العملية (Idempotency Key) غير صالح.', 'error_code' => 'INVALID_IDEMPOTENCY_KEY');
        }

        // -------------------------------------------------------------
        // 5. Validate Line Items (Non-empty, consolidation, positive int)
        // -------------------------------------------------------------
        if (!is_array($items) || count($items) === 0) {
            return array('success' => FALSE, 'error' => 'لا يمكن إنشاء فاتورة فارغة بدون بنود.', 'error_code' => 'EMPTY_INVOICE_ITEMS');
        }

        // Group items by product_id to prevent duplicates and sum quantities safely
        $consolidated_items = array();
        foreach ($items as $idx => $line) {
            $pid = isset($line['product_id']) ? filter_var($line['product_id'], FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
            )) : FALSE;

            if ($pid === FALSE) {
                return array('success' => FALSE, 'error' => 'معرف المنتج في البند رقم ' . ($idx + 1) . ' غير صالح.', 'error_code' => 'INVALID_PRODUCT_ID');
            }

            $qty = isset($line['quantity']) ? filter_var($line['quantity'], FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 1)
            )) : FALSE;

            if ($qty === FALSE) {
                return array('success' => FALSE, 'error' => 'الكمية في البند رقم ' . ($idx + 1) . ' يجب أن تكون عدداً صحيحاً موجباً أكبر من الصفر.', 'error_code' => 'INVALID_ITEM_QUANTITY');
            }

            $custom_price = NULL;
            if (isset($line['unit_price']) && $line['unit_price'] !== '' && $line['unit_price'] !== NULL) {
                if (!is_numeric($line['unit_price']) || (float)$line['unit_price'] < 0.00) {
                    return array('success' => FALSE, 'error' => 'سعر الوحدة في البند رقم ' . ($idx + 1) . ' غير صالح.', 'error_code' => 'INVALID_UNIT_PRICE');
                }
                $custom_price = round((float)$line['unit_price'], 2);
            }

            if (!isset($consolidated_items[$pid])) {
                $consolidated_items[$pid] = array(
                    'product_id'   => $pid,
                    'quantity'     => $qty,
                    'custom_price' => $custom_price
                );
            } else {
                $consolidated_items[$pid]['quantity'] += $qty;
                // If custom price specified in subsequent line, check consistency or keep first
                if ($custom_price !== NULL && $consolidated_items[$pid]['custom_price'] === NULL) {
                    $consolidated_items[$pid]['custom_price'] = $custom_price;
                }
            }
        }

        if (empty($consolidated_items)) {
            return array('success' => FALSE, 'error' => 'لا توجد بنود صالحة لإنشاء الفاتورة.', 'error_code' => 'EMPTY_INVOICE_ITEMS');
        }

        // Sort product IDs in ascending order to prevent deadlocks when locking rows
        ksort($consolidated_items, SORT_NUMERIC);

        // -------------------------------------------------------------
        // 5.1 Idempotency Pre-Check with Deep Payload Validation
        // -------------------------------------------------------------
        $existing_si = $this->get_by_idempotency_key($idempotency_key);
        if ($existing_si) {
            return $this->evaluate_idempotency_replay($existing_si, $customer_id, $warehouse_id, $discount_pct, $consolidated_items, $invoice_date, $notes, $user_id);
        }

        // -------------------------------------------------------------
        // 6. Begin ACID Database Transaction
        // -------------------------------------------------------------
        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $trans_started = $this->db->trans_begin();
            if ($trans_started === FALSE) {
                return array('success' => FALSE, 'error' => 'فشل بدء المعاملة في قاعدة البيانات.', 'error_code' => 'TRANS_BEGIN_FAILED');
            }

            // 6.1 Lock Warehouse and verify active status (SELECT ... FOR UPDATE)
            $wh_lock_sql = "SELECT `id`, `name`, `is_active` FROM `warehouses` WHERE `id` = ? FOR UPDATE";
            $wh_lock_query = $this->db->query($wh_lock_sql, array($warehouse_id));

            if ($wh_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل قفل بيانات المستودع.', 'error_code' => 'WAREHOUSE_LOCK_FAILED');
            }

            $warehouse_row = $wh_lock_query->row();
            if (!$warehouse_row || (int)$warehouse_row->is_active !== 1) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'المستودع المحدد غير موجود أو غير مفعل.', 'error_code' => 'INACTIVE_WAREHOUSE');
            }

            // 6.2 Lock and verify each Product, calculate line totals and subtotal
            $verified_lines = array();
            $subtotal = 0.00;

            foreach ($consolidated_items as $pid => $item_meta) {
                $pr_lock_sql = "SELECT `id`, `name`, `sku`, `sale_price`, `is_active` FROM `products` WHERE `id` = ? FOR UPDATE";
                $pr_lock_query = $this->db->query($pr_lock_sql, array($pid));

                if ($pr_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل قفل بيانات المنتج رقم ' . $pid, 'error_code' => 'PRODUCT_LOCK_FAILED');
                }

                $product_row = $pr_lock_query->row();
                if (!$product_row || (int)$product_row->is_active !== 1) {
                    $this->db->trans_rollback();
                    $p_name = $product_row ? $product_row->name : ('ID ' . $pid);
                    return array('success' => FALSE, 'error' => 'المنتج (' . $p_name . ') غير موجود أو غير مفعل.', 'error_code' => 'INACTIVE_PRODUCT');
                }

                // Determine unit price: use custom if provided, otherwise locked product sale_price
                $unit_price = ($item_meta['custom_price'] !== NULL) ? $item_meta['custom_price'] : (float)$product_row->sale_price;
                $unit_price = round($unit_price, 2);

                $line_total = round($item_meta['quantity'] * $unit_price, 2, PHP_ROUND_HALF_UP);
                $subtotal = round($subtotal + $line_total, 2, PHP_ROUND_HALF_UP);

                $verified_lines[] = array(
                    'product_id'            => (int)$product_row->id,
                    'sku_snapshot'          => $product_row->sku,
                    'product_name_snapshot' => $product_row->name,
                    'unit_price'            => $unit_price,
                    'quantity'              => (int)$item_meta['quantity'],
                    'line_total'            => $line_total,
                );
            }

            // 6.3 Calculate Final Discount & Total with PHP_ROUND_HALF_UP
            $discount_amount = round($subtotal * ($discount_pct / 100.0), 2, PHP_ROUND_HALF_UP);
            $total_amount    = round($subtotal - $discount_amount, 2, PHP_ROUND_HALF_UP);

            // Double check math balance constraint
            if ($total_amount < 0.00) {
                $total_amount = 0.00;
            }

            // 6.4 Lock and Verify Stock for all items (SELECT ... FOR UPDATE)
            $stock_rows = array();
            foreach ($verified_lines as $line) {
                $stock_lock_sql = "SELECT `id`, `quantity` FROM `{$this->stock_table}` WHERE `warehouse_id` = ? AND `product_id` = ? FOR UPDATE";
                $stock_lock_query = $this->db->query($stock_lock_sql, array($warehouse_id, $line['product_id']));

                if ($stock_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل قفل رصيد المستودع للمنتج ' . $line['product_name_snapshot'], 'error_code' => 'STOCK_LOCK_FAILED');
                }

                $stock_row = $stock_lock_query->row();
                $available_qty = $stock_row ? (int)$stock_row->quantity : 0;

                if (!$stock_row || $available_qty < $line['quantity']) {
                    $this->db->trans_rollback();
                    return array(
                        'success'         => FALSE,
                        'error'           => sprintf(
                            'الرصيد المتاح من المنتج "%s" في المستودع (%d) غير كافٍ لتنفيذ البيع (المطلوب: %d).',
                            $line['product_name_snapshot'],
                            $available_qty,
                            $line['quantity']
                        ),
                        'error_code'      => 'INSUFFICIENT_STOCK',
                        'product_id'      => $line['product_id'],
                        'product_name'    => $line['product_name_snapshot'],
                        'available_stock' => $available_qty,
                        'requested_qty'   => $line['quantity']
                    );
                }

                $stock_rows[$line['product_id']] = array(
                    'stock_id'        => (int)$stock_row->id,
                    'before_quantity' => $available_qty,
                    'after_quantity'  => $available_qty - $line['quantity']
                );
            }

            // 6.5 Daily Atomic Sequence Generation (INV-YYYYMMDD-XXXX)
            // Initialize or touch row for today
            $seq_init_sql = "INSERT INTO `{$this->sequence_table}` (`sequence_date`, `current_sequence`) VALUES (?, 0)
                             ON DUPLICATE KEY UPDATE `sequence_date` = `sequence_date`";
            $seq_init_query = $this->db->query($seq_init_sql, array($invoice_date));

            if ($seq_init_query === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل تهيئة عداد ترقيم الفواتير.', 'error_code' => 'SEQUENCE_INIT_FAILED');
            }

            // Lock the sequence row for today exclusively
            $seq_lock_sql = "SELECT `current_sequence` FROM `{$this->sequence_table}` WHERE `sequence_date` = ? FOR UPDATE";
            $seq_lock_query = $this->db->query($seq_lock_sql, array($invoice_date));

            if ($seq_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل قفل عداد ترقيم الفواتير.', 'error_code' => 'SEQUENCE_LOCK_FAILED');
            }

            $seq_row = $seq_lock_query->row();
            $current_seq = $seq_row ? (int)$seq_row->current_sequence : 0;

            if ($current_seq >= self::MAX_DAILY_SEQUENCE) {
                $this->db->trans_rollback();
                return array(
                    'success'    => FALSE,
                    'error'      => 'تم بلوغ الحد الأقصى اليومي لترقيم الفواتير (9999 فاتورة). لا يمكن إنشاء المزيد من الفواتير بهذا التاريخ.',
                    'error_code' => 'DAILY_SEQUENCE_LIMIT_EXCEEDED'
                );
            }

            $next_seq = $current_seq + 1;

            // Increment sequence
            $seq_update_sql = "UPDATE `{$this->sequence_table}` SET `current_sequence` = ? WHERE `sequence_date` = ?";
            $seq_update_query = $this->db->query($seq_update_sql, array($next_seq, $invoice_date));

            if ($seq_update_query === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل تحديث عداد ترقيم الفواتير.', 'error_code' => 'SEQUENCE_UPDATE_FAILED');
            }

            $formatted_date = date('Ymd', strtotime($invoice_date));
            $invoice_number = sprintf('INV-%s-%04d', $formatted_date, $next_seq);

            // 6.6 Insert Sales Invoice Header
            $invoice_insert = array(
                'invoice_number'      => $invoice_number,
                'customer_id'         => $customer_id,
                'warehouse_id'        => $warehouse_id,
                'created_by'          => $user_id,
                'invoice_date'        => $invoice_date,
                'status'              => 'active',
                'subtotal'            => $subtotal,
                'discount_percentage' => $discount_pct,
                'discount_amount'     => $discount_amount,
                'total_amount'        => $total_amount,
                'currency_code'       => 'SAR',
                'notes'               => $notes,
                'idempotency_key'     => $idempotency_key,
                'created_at'          => date('Y-m-d H:i:s'),
            );

            $insert_invoice_res = $this->db->insert($this->table, $invoice_insert);
            $db_error = $this->db->error();

            if ($insert_invoice_res === FALSE || $this->db->trans_status() === FALSE || !empty($db_error['code'])) {
                $this->db->trans_rollback();
                if (isset($db_error['code']) && (int)$db_error['code'] === 1062) {
                    $existing_concurrent = $this->get_by_idempotency_key($idempotency_key);
                    if ($existing_concurrent) {
                        return $this->evaluate_idempotency_replay($existing_concurrent, $customer_id, $warehouse_id, $discount_pct, $consolidated_items, $invoice_date, $notes, $user_id);
                    }
                }
                return array('success' => FALSE, 'error' => 'فشل حفظ رأس الفاتورة في قاعدة البيانات.', 'error_code' => 'INVOICE_INSERT_FAILED');
            }

            $invoice_id = (int)$this->db->insert_id();

            // 6.7 Insert Line Items, Deduct Stock, and Record Stock Movements
            foreach ($verified_lines as $line) {
                // Insert item
                $item_insert = array(
                    'invoice_id'            => $invoice_id,
                    'product_id'            => $line['product_id'],
                    'sku_snapshot'          => $line['sku_snapshot'],
                    'product_name_snapshot' => $line['product_name_snapshot'],
                    'unit_price'            => $line['unit_price'],
                    'quantity'              => $line['quantity'],
                    'line_total'            => $line['line_total'],
                    'created_at'            => date('Y-m-d H:i:s'),
                );

                $insert_item_res = $this->db->insert($this->items_table, $item_insert);
                if ($insert_item_res === FALSE || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل حفظ بنود الفاتورة.', 'error_code' => 'ITEM_INSERT_FAILED');
                }

                // Deduct stock in warehouse_stock
                $st_meta = $stock_rows[$line['product_id']];
                $update_stock_res = $this->db->where('id', $st_meta['stock_id'])->update($this->stock_table, array(
                    'quantity'   => $st_meta['after_quantity'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ));

                if ($update_stock_res === FALSE || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل تحديث رصيد المخزون للبند ' . $line['product_name_snapshot'], 'error_code' => 'STOCK_UPDATE_FAILED');
                }

                // Record stock movement (movement_type = 'sale', negative delta, linked sales_invoice_id)
                $movement_insert = array(
                    'warehouse_id'           => $warehouse_id,
                    'product_id'             => $line['product_id'],
                    'movement_type'          => 'sale',
                    'quantity_delta'         => -$line['quantity'], // strictly negative
                    'before_quantity'        => $st_meta['before_quantity'],
                    'after_quantity'         => $st_meta['after_quantity'],
                    'reason'                 => 'فاتورة مبيعات ' . $invoice_number,
                    'idempotency_key'        => bin2hex(random_bytes(16)),
                    'sales_invoice_id'       => $invoice_id,
                    'reversal_of_movement_id'=> NULL,
                    'created_by'             => $user_id,
                    'created_at'             => date('Y-m-d H:i:s'),
                );

                $insert_sm_res = $this->db->insert($this->movement_table, $movement_insert);
                if ($insert_sm_res === FALSE || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل تسجيل حركة المخزون للبند ' . $line['product_name_snapshot'], 'error_code' => 'MOVEMENT_INSERT_FAILED');
                }
            }

            // 6.8 Final status check and Commit
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشلت المعاملة أثناء إتمام العملية.', 'error_code' => 'TRANSACTION_FAILED');
            }

            $this->db->trans_commit();

            return array(
                'success'         => TRUE,
                'is_replay'       => FALSE,
                'invoice_id'      => $invoice_id,
                'invoice_number'  => $invoice_number,
                'invoice_date'    => $invoice_date,
                'subtotal'        => $subtotal,
                'discount_amount' => $discount_amount,
                'total_amount'    => $total_amount,
                'currency_code'   => 'SAR',
                'error'           => NULL
            );
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Compare all business-effective attributes of an existing invoice against request parameters.
     * If all parameters (customer, warehouse, discount, items, quantities) match: return as idempotent replay.
     * If any parameter differs: return an IDEMPOTENCY_CONFLICT error.
     *
     * @param object $existing_invoice
     * @param int $customer_id
     * @param int $warehouse_id
     * @param float $discount_pct
     * @param array $consolidated_items
     * @return array
     */
    protected function evaluate_idempotency_replay($existing_invoice, $customer_id, $warehouse_id, $discount_pct, array $consolidated_items, $invoice_date = NULL, $notes = NULL, $user_id = NULL)
    {
        $same_customer  = ((int)$existing_invoice->customer_id === (int)$customer_id);
        $same_warehouse = ((int)$existing_invoice->warehouse_id === (int)$warehouse_id);
        $same_discount  = (abs((float)$existing_invoice->discount_percentage - (float)$discount_pct) < 0.001);

        // Verify invoice_date match
        $same_date = TRUE;
        if ($invoice_date !== NULL) {
            $same_date = ((string)$existing_invoice->invoice_date === (string)$invoice_date);
        }

        // Verify notes match (normalized whitespace)
        $same_notes = TRUE;
        if ($notes !== NULL || $existing_invoice->notes !== NULL) {
            $norm_existing_notes = trim((string)$existing_invoice->notes);
            $norm_input_notes    = trim((string)$notes);
            $same_notes          = ($norm_existing_notes === $norm_input_notes);
        }

        // Verify created_by user match
        $same_user = TRUE;
        if ($user_id !== NULL) {
            $same_user = ((int)$existing_invoice->created_by === (int)$user_id);
        }

        if (!$same_customer || !$same_warehouse || !$same_discount || !$same_date || !$same_notes || !$same_user) {
            return array(
                'success'    => FALSE,
                'error'      => 'تعارض في رمز العملية: تم استخدام مفتاح التحقق (Idempotency Key) نفسه مسبقاً لعملية بيع ذات بيانات أو بنود مختلفة.',
                'error_code' => 'IDEMPOTENCY_CONFLICT'
            );
        }

        $existing_items = $this->get_items_by_invoice_id($existing_invoice->id);
        if ($existing_items === FALSE || count($existing_items) !== count($consolidated_items)) {
            return array(
                'success'    => FALSE,
                'error'      => 'تعارض في رمز العملية: تم استخدام مفتاح التحقق (Idempotency Key) نفسه مسبقاً لعملية بيع ذات بيانات أو بنود مختلفة.',
                'error_code' => 'IDEMPOTENCY_CONFLICT'
            );
        }

        $existing_map = array();
        foreach ($existing_items as $item) {
            $existing_map[(int)$item->product_id] = (int)$item->quantity;
        }

        foreach ($consolidated_items as $pid => $meta) {
            if (!isset($existing_map[$pid]) || $existing_map[$pid] !== (int)$meta['quantity']) {
                return array(
                    'success'    => FALSE,
                    'error'      => 'تعارض في رمز العملية: تم استخدام مفتاح التحقق (Idempotency Key) نفسه مسبقاً لعملية بيع ذات بيانات أو بنود مختلفة.',
                    'error_code' => 'IDEMPOTENCY_CONFLICT'
                );
            }
        }

        return array(
            'success'         => TRUE,
            'is_replay'       => TRUE,
            'invoice_id'      => (int)$existing_invoice->id,
            'invoice_number'  => $existing_invoice->invoice_number,
            'subtotal'        => (float)$existing_invoice->subtotal,
            'discount_amount' => (float)$existing_invoice->discount_amount,
            'total_amount'    => (float)$existing_invoice->total_amount,
            'error'           => NULL,
            'message'         => 'تم استرجاع الفاتورة المنشأة مسبقاً لهذا الرمز دون تكرار الخصم أو الإنشاء.'
        );
    }

    /**
     * Cancel an active sales invoice atomically (F08)
     *
     * Business rules:
     * 1. Admin only.
     * 2. Cannot cancel an already cancelled invoice (ALREADY_CANCELLED).
     * 3. Cannot delete invoice or line items; preserve all historical records.
     * 4. Cancellation reason is mandatory (min 5 chars, max 255 chars).
     * 5. Atomically restore warehouse stock for each item sold.
     * 6. Create 'sale_reversal' stock movements linked to original 'sale' movements.
     * 7. Enforces single database transaction; rolls back completely on any error.
     *
     * @param int $invoice_id Sales invoice ID
     * @param string $cancellation_reason Mandatory reason (>= 5 chars)
     * @param object $user Current authenticated user object (must be admin)
     * @return array Result array with success flag, invoice data, or error details
     */
    public function cancel_invoice($invoice_id, $cancellation_reason, $user)
    {
        // -------------------------------------------------------------
        // 1. Pre-validation: Input ID & User Role (Admin only)
        // -------------------------------------------------------------
        $valid_id = filter_var($invoice_id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT)
        ));

        if ($valid_id === FALSE) {
            return array('success' => FALSE, 'error' => 'معرف الفاتورة غير صالح.', 'error_code' => 'INVALID_INVOICE_ID');
        }

        if (!is_object($user) || empty($user->id)) {
            return array('success' => FALSE, 'error' => 'بيانات المستخدم غير صالحة.', 'error_code' => 'INVALID_USER');
        }

        $role_name = isset($user->role_name) ? $user->role_name : NULL;
        if ($role_name === NULL && isset($user->role_id)) {
            $user_row = $this->db->select('r.name AS role_name')
                ->from('users u')
                ->join('roles r', 'r.id = u.role_id')
                ->where('u.id', $user->id)
                ->get()->row();
            $role_name = $user_row ? $user_row->role_name : NULL;
        }

        if ($role_name !== 'admin') {
            return array('success' => FALSE, 'error' => 'إلغاء الفواتير متاح حصراً لمدير النظام (Admin).', 'error_code' => 'PERMISSION_DENIED');
        }

        // -------------------------------------------------------------
        // 2. Pre-validation: Cancellation Reason (5 to 255 chars)
        // -------------------------------------------------------------
        $clean_reason = trim((string)$cancellation_reason);
        $reason_len = mb_strlen($clean_reason, 'UTF-8');

        if ($reason_len < 5) {
            return array('success' => FALSE, 'error' => 'سبب الإلغاء إلزامي ويجب ألا يقل عن 5 أحرف.', 'error_code' => 'INVALID_CANCELLATION_REASON');
        }

        if ($reason_len > 255) {
            return array('success' => FALSE, 'error' => 'سبب الإلغاء يجب ألا يتجاوز 255 حرفاً.', 'error_code' => 'CANCELLATION_REASON_TOO_LONG');
        }

        // -------------------------------------------------------------
        // 3. Begin ACID Database Transaction
        // -------------------------------------------------------------
        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $trans_started = $this->db->trans_begin();
            if ($trans_started === FALSE) {
                return array('success' => FALSE, 'error' => 'فشل بدء المعاملة في قاعدة البيانات.', 'error_code' => 'TRANS_BEGIN_FAILED');
            }

            // 3.1 Lock Invoice Header exclusively (SELECT ... FOR UPDATE)
            $inv_lock_sql = "SELECT * FROM `{$this->table}` WHERE `id` = ? FOR UPDATE";
            $inv_lock_query = $this->db->query($inv_lock_sql, array($valid_id));

            if ($inv_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل قفل سجل الفاتورة.', 'error_code' => 'INVOICE_LOCK_FAILED');
            }

            $invoice_row = $inv_lock_query->row();
            if (!$invoice_row) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'الفاتورة المطلوبة غير موجودة.', 'error_code' => 'INVOICE_NOT_FOUND');
            }

            // 3.2 Verify Invoice Status (prevent duplicate cancellation)
            if ($invoice_row->status === 'cancelled') {
                $this->db->trans_rollback();
                return array(
                    'success'             => FALSE,
                    'error'               => 'هذه الفاتورة ملغاة بالفعل ولا يمكن إلغاؤها مجدداً.',
                    'error_code'          => 'ALREADY_CANCELLED',
                    'invoice_id'          => (int)$invoice_row->id,
                    'invoice_number'      => $invoice_row->invoice_number,
                    'cancelled_at'        => $invoice_row->cancelled_at,
                    'cancelled_by'        => $invoice_row->cancelled_by,
                    'cancellation_reason' => $invoice_row->cancellation_reason,
                );
            }

            if ($invoice_row->status !== 'active') {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'حالة الفاتورة غير صالحة للإلغاء.', 'error_code' => 'INVALID_INVOICE_STATUS');
            }

            $warehouse_id = (int)$invoice_row->warehouse_id;

            // 3.3 Lock Warehouse and verify active status
            $wh_lock_sql = "SELECT `id`, `name`, `is_active` FROM `warehouses` WHERE `id` = ? FOR UPDATE";
            $wh_lock_query = $this->db->query($wh_lock_sql, array($warehouse_id));

            if ($wh_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل قفل بيانات المستودع.', 'error_code' => 'WAREHOUSE_LOCK_FAILED');
            }

            // 3.4 Retrieve and lock original 'sale' stock movements for this invoice
            $sm_lock_sql = "SELECT * FROM `{$this->movement_table}` WHERE `sales_invoice_id` = ? AND `movement_type` = 'sale' ORDER BY `id` ASC FOR UPDATE";
            $sm_lock_query = $this->db->query($sm_lock_sql, array($valid_id));

            if ($sm_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل استرجاع حركات البيع المرتبطة بالفاتورة.', 'error_code' => 'MOVEMENTS_LOCK_FAILED');
            }

            $orig_movements = $sm_lock_query->result();
            if (empty($orig_movements)) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'لم يتم العثور على حركات بيع أصلية مرتبطة بهذه الفاتورة لاسترجاعها.', 'error_code' => 'NO_SALE_MOVEMENTS_FOUND');
            }

            // 3.5 Verify no reversal movement already exists for this invoice
            $existing_rev_sql = "SELECT `id` FROM `{$this->movement_table}` WHERE `sales_invoice_id` = ? AND `movement_type` = 'sale_reversal' LIMIT 1 FOR UPDATE";
            $existing_rev_query = $this->db->query($existing_rev_sql, array($valid_id));

            if ($existing_rev_query !== FALSE && $existing_rev_query->num_rows() > 0) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'توجد حركات إلغاء مسجلة مسبقاً لهذه الفاتورة.', 'error_code' => 'ALREADY_CANCELLED');
            }

            $now_dt = date('Y-m-d H:i:s');

            // 3.6 For each original movement, restore stock and insert reversal movement
            foreach ($orig_movements as $orig_sm) {
                $product_id = (int)$orig_sm->product_id;
                $restored_qty = abs((int)$orig_sm->quantity_delta);

                if ($restored_qty <= 0) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'كمية حركة البيع الأصلية غير صالحة للاسترجاع.', 'error_code' => 'INVALID_REVERSAL_QUANTITY');
                }

                // Lock warehouse stock for this product
                $stock_lock_sql = "SELECT `id`, `quantity` FROM `{$this->stock_table}` WHERE `warehouse_id` = ? AND `product_id` = ? FOR UPDATE";
                $stock_lock_query = $this->db->query($stock_lock_sql, array($warehouse_id, $product_id));

                if ($stock_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل قفل رصيد المستودع للمنتج رقم ' . $product_id, 'error_code' => 'STOCK_LOCK_FAILED');
                }

                $stock_row = $stock_lock_query->row();
                if (!$stock_row) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'سجل رصيد المستودع غير موجود للمنتج رقم ' . $product_id, 'error_code' => 'STOCK_NOT_FOUND');
                }

                $before_qty = (int)$stock_row->quantity;
                $after_qty  = $before_qty + $restored_qty;

                // Update warehouse_stock
                $update_stock_sql = "UPDATE `{$this->stock_table}` SET `quantity` = ?, `updated_at` = ? WHERE `id` = ?";
                $update_stock_res = $this->db->query($update_stock_sql, array($after_qty, $now_dt, $stock_row->id));

                if ($update_stock_res === FALSE || $this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل تحديث رصيد المستودع للمنتج رقم ' . $product_id, 'error_code' => 'STOCK_UPDATE_FAILED');
                }

                // Format reversal reason within VARCHAR(255)
                $rev_reason = sprintf('إلغاء فاتورة مبيعات %s: %s', $invoice_row->invoice_number, $clean_reason);
                if (mb_strlen($rev_reason, 'UTF-8') > 255) {
                    $rev_reason = mb_substr($rev_reason, 0, 252, 'UTF-8') . '...';
                }

                // Insert reversal movement (movement_type = 'sale_reversal', strictly positive delta)
                $reversal_insert = array(
                    'warehouse_id'            => $warehouse_id,
                    'product_id'              => $product_id,
                    'movement_type'           => 'sale_reversal',
                    'quantity_delta'          => $restored_qty, // positive
                    'before_quantity'         => $before_qty,
                    'after_quantity'          => $after_qty,
                    'reason'                  => $rev_reason,
                    'idempotency_key'         => bin2hex(random_bytes(16)),
                    'sales_invoice_id'        => $invoice_row->id,
                    'reversal_of_movement_id' => $orig_sm->id,
                    'created_by'              => (int)$user->id,
                    'created_at'              => $now_dt,
                );

                $insert_rev_res = $this->db->insert($this->movement_table, $reversal_insert);
                $db_err = $this->db->error();

                if ($insert_rev_res === FALSE || $this->db->trans_status() === FALSE || !empty($db_err['code'])) {
                    $this->db->trans_rollback();
                    return array('success' => FALSE, 'error' => 'فشل تسجيل حركة المخزون العكسية.', 'error_code' => 'REVERSAL_MOVEMENT_FAILED');
                }
            }

            // 3.7 Update Sales Invoice Header (status = 'cancelled', cancelled_at, cancelled_by, cancellation_reason)
            $invoice_update = array(
                'status'              => 'cancelled',
                'cancelled_at'        => $now_dt,
                'cancelled_by'        => (int)$user->id,
                'cancellation_reason' => $clean_reason,
                'updated_at'          => $now_dt,
            );

            $update_inv_res = $this->db->where('id', $invoice_row->id)->update($this->table, $invoice_update);
            $db_err = $this->db->error();

            if ($update_inv_res === FALSE || $this->db->trans_status() === FALSE || !empty($db_err['code'])) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل تحديث حالة الفاتورة إلى ملغاة.', 'error_code' => 'INVOICE_CANCEL_UPDATE_FAILED');
            }

            // 3.8 Commit Transaction
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array('success' => FALSE, 'error' => 'فشل إتمام معاملة إلغاء الفاتورة.', 'error_code' => 'TRANS_COMMIT_FAILED');
            }

            $this->db->trans_commit();

            return array(
                'success'             => TRUE,
                'invoice_id'          => (int)$invoice_row->id,
                'invoice_number'      => $invoice_row->invoice_number,
                'status'              => 'cancelled',
                'cancelled_at'        => $now_dt,
                'cancelled_by'        => (int)$user->id,
                'cancellation_reason' => $clean_reason,
                'restored_items'      => count($orig_movements),
                'error'               => NULL,
                'message'             => 'تم إلغاء الفاتورة وإعادة كميات المخزون بنجاح.'
            );

        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', 'Sales_invoice_model::cancel_invoice exception: ' . $e->getMessage());
            return array('success' => FALSE, 'error' => 'حدث خطأ غير متوقع أثناء إلغاء الفاتورة.', 'error_code' => 'EXCEPTION_CAUGHT');
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }
}
