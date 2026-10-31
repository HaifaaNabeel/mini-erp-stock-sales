<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stock_movement_model — F06 Stock Movements Data Layer & Atomic Operations
 *
 * Implements atomic stock in, stock adjustment, idempotency checks,
 * concurrency locking (FOR UPDATE on stock, warehouse, and product),
 * and server-side filtered movement history.
 * Historical records are append-only; update/delete operations are strictly omitted.
 *
 * All database operations (reads and transactions) explicitly isolate db_debug
 * using try/finally blocks to ensure errors are captured and returned cleanly
 * rather than halting execution.
 *
 * PHP Compatibility: 7.4+
 */
class Stock_movement_model extends MY_Model
{
    protected $table = 'stock_movements';
    protected $stock_table = 'warehouse_stock';

    // Maximum signed 32-bit INT supported by MySQL INT
    const MAX_INT_VALUE = 2147483647;
    // Maximum unsigned 32-bit INT supported by MySQL INT UNSIGNED
    const MAX_UNSIGNED_INT_VALUE = 4294967295;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a movement by primary key with joined names
     * Isolates db_debug with try/finally to capture failures cleanly
     *
     * @param mixed $id
     * @return object|null|false Returns object if found, NULL if not found, FALSE on invalid ID or query failure
     */
    public function get_by_id($id)
    {
        $valid_id = filter_var($id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT_VALUE)
        ));

        if ($valid_id === FALSE) {
            return FALSE;
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $query = $this->db->select('sm.*, w.name AS warehouse_name, w.code AS warehouse_code, p.name AS product_name, p.sku AS product_sku, u.full_name AS created_by_name, u.username AS created_by_username')
                ->from($this->table . ' sm')
                ->join('warehouses w', 'w.id = sm.warehouse_id', 'inner')
                ->join('products p', 'p.id = sm.product_id', 'inner')
                ->join('users u', 'u.id = sm.created_by', 'inner')
                ->where('sm.id', $valid_id)
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
     * Get a movement by idempotency key
     * Isolates db_debug with try/finally to capture failures cleanly.
     * Distinguishes explicitly between:
     * - Record found: returns object
     * - Record not found: returns NULL
     * - Invalid key format or query failure: returns FALSE
     *
     * @param string $idempotency_key
     * @return object|null|false
     */
    public function get_by_idempotency_key($idempotency_key)
    {
        if (!is_string($idempotency_key) || !preg_match('/^[a-f0-9]{32}$/', $idempotency_key)) {
            return FALSE;
        }

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $query = $this->db->where('idempotency_key', $idempotency_key)
                ->get($this->table);

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
     * Execute Stock In operation atomically
     *
     * @param mixed $warehouse_id
     * @param mixed $product_id
     * @param mixed $quantity Strictly positive integer within INT range (1 to 2147483647)
     * @param string|null $reason Optional note (max 255 chars)
     * @param string $idempotency_key 32-char hex token (lowercase only)
     * @param mixed $created_by User ID
     * @return array Result array with success flag, movement_id, or error message
     */
    public function record_stock_in($warehouse_id, $product_id, $quantity, $reason, $idempotency_key, $created_by)
    {
        // 1. Strict integer validation for quantity: reject floats, negative, zero, and non-numeric strings
        $valid_quantity = filter_var($quantity, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_INT_VALUE)
        ));

        if ($valid_quantity === FALSE) {
            return array(
                'success'    => FALSE,
                'error'      => 'يجب أن تكون كمية الإدخال عدداً صحيحاً موجباً أكبر من الصفر ولا تتجاوز 2,147,483,647 (الكسور غير مسموحة).',
                'error_code' => 'INVALID_QUANTITY'
            );
        }

        return $this->process_movement(array(
            'warehouse_id'    => $warehouse_id,
            'product_id'      => $product_id,
            'movement_type'   => 'in',
            'quantity_delta'  => $valid_quantity,
            'reason'          => $reason,
            'idempotency_key' => $idempotency_key,
            'created_by'      => $created_by,
        ));
    }

    /**
     * Execute Stock Adjustment operation atomically
     *
     * @param mixed $warehouse_id
     * @param mixed $product_id
     * @param mixed $quantity_delta Signed non-zero integer (+ for addition, - for deduction)
     * @param string $reason Mandatory reason (5 to 255 characters)
     * @param string $idempotency_key 32-char hex token (lowercase only)
     * @param mixed $created_by User ID
     * @return array Result array with success flag, movement_id, or error message
     */
    public function record_adjustment($warehouse_id, $product_id, $quantity_delta, $reason, $idempotency_key, $created_by)
    {
        // 1. Strict integer validation for quantity_delta: reject floats, non-numeric strings, and zero
        $valid_delta = filter_var($quantity_delta, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => -self::MAX_INT_VALUE, 'max_range' => self::MAX_INT_VALUE)
        ));

        if ($valid_delta === FALSE || $valid_delta === 0) {
            return array(
                'success'    => FALSE,
                'error'      => 'كمية التسوية يجب أن تكون عدداً صحيحاً غير صفري ضمن النطاق المسموح به (الكسور غير مسموحة).',
                'error_code' => 'INVALID_QUANTITY_DELTA'
            );
        }

        return $this->process_movement(array(
            'warehouse_id'    => $warehouse_id,
            'product_id'      => $product_id,
            'movement_type'   => 'adjustment',
            'quantity_delta'  => $valid_delta,
            'reason'          => $reason,
            'idempotency_key' => $idempotency_key,
            'created_by'      => $created_by,
        ));
    }

    /**
     * Central Atomic Transaction:
     * - Strictly validates raw integer identifiers without premature casting
     * - Strictly validates exact 32 lowercase hex characters for idempotency_key
     * - Normalizes reason once (trimming and length checks: 5 to 255 for adjustment)
     * - Idempotency lookup PRECEDES active status check (allows replaying historical movements)
     * - If idempotency lookup query fails, immediately returns error without starting movement
     * - Verifies trans_begin() returns TRUE
     * - Disables db_debug with guaranteed restoration in finally block
     * - Locks and verifies active status of Warehouse and Product inside transaction (FOR UPDATE)
     * - Safely lazy-initializes warehouse_stock under concurrency
     * - Locks stock row (FOR UPDATE)
     * - Checks integer overflow and non-negative stock balance
     * - Checks query results explicitly
     * - On error or duplicate key: executes trans_rollback immediately
     * - Verifies trans_commit() succeeds before returning success
     *
     * @param array $params
     * @return array
     */
    protected function process_movement(array $params)
    {
        $raw_warehouse_id   = isset($params['warehouse_id']) ? $params['warehouse_id'] : NULL;
        $raw_product_id     = isset($params['product_id']) ? $params['product_id'] : NULL;
        $raw_created_by     = isset($params['created_by']) ? $params['created_by'] : NULL;
        $raw_movement_type  = isset($params['movement_type']) ? $params['movement_type'] : NULL;
        $raw_quantity_delta = isset($params['quantity_delta']) ? $params['quantity_delta'] : NULL;
        $raw_reason         = isset($params['reason']) ? $params['reason'] : NULL;
        $raw_idempotency    = isset($params['idempotency_key']) ? $params['idempotency_key'] : NULL;

        // 1. Strict validation of integer identifiers (reject floats, strings like "1.5", and out-of-range values)
        $warehouse_id = filter_var($raw_warehouse_id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT_VALUE)
        ));
        $product_id = filter_var($raw_product_id, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT_VALUE)
        ));
        $created_by = filter_var($raw_created_by, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT_VALUE)
        ));

        if ($warehouse_id === FALSE || $product_id === FALSE || $created_by === FALSE) {
            return array(
                'success'    => FALSE,
                'error'      => 'معرفات المستودع أو المنتج أو المستخدم غير صالحة؛ يجب أن تكون أعداداً صحيحة موجبة.',
                'error_code' => 'INVALID_IDENTIFIERS'
            );
        }

        // 2. Strict validation of idempotency_key (exact 32 lowercase hex chars, no 'i' modifier, reject uppercase)
        if (!is_string($raw_idempotency) || !preg_match('/^[a-f0-9]{32}$/', $raw_idempotency)) {
            return array(
                'success'    => FALSE,
                'error'      => 'رمز العملية الفريد غير صالح؛ يجب أن يتكون من 32 محرفاً بنظام hex بأحرف صغيرة فقط.',
                'error_code' => 'INVALID_IDEMPOTENCY_KEY'
            );
        }
        $idempotency_key = $raw_idempotency;

        // 3. Movement type validation
        if (!is_string($raw_movement_type) || !in_array($raw_movement_type, array('in', 'adjustment'), true)) {
            return array(
                'success'    => FALSE,
                'error'      => 'نوع الحركة المحدد غير معتمد في هذه المرحلة.',
                'error_code' => 'INVALID_MOVEMENT_TYPE'
            );
        }
        $movement_type = $raw_movement_type;

        // 4. Quantity delta validation
        $quantity_delta = filter_var($raw_quantity_delta, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => -self::MAX_INT_VALUE, 'max_range' => self::MAX_INT_VALUE)
        ));
        if ($quantity_delta === FALSE || $quantity_delta === 0) {
            return array(
                'success'    => FALSE,
                'error'      => 'كمية الحركة يجب أن تكون عدداً صحيحاً غير صفري.',
                'error_code' => 'INVALID_QUANTITY_DELTA'
            );
        }
        if ($movement_type === 'in' && $quantity_delta <= 0) {
            return array(
                'success'    => FALSE,
                'error'      => 'حركة إدخال المخزون يجب أن تكون كميتها موجبة حصراً.',
                'error_code' => 'STOCK_IN_MUST_BE_POSITIVE'
            );
        }

        // 5. Normalized Reason validation (normalize once, reuse everywhere)
        $normalized_reason = NULL;
        if ($raw_reason !== NULL) {
            if (!is_string($raw_reason)) {
                return array(
                    'success'    => FALSE,
                    'error'      => 'سبب الحركة يجب أن يكون نصاً صالحاً.',
                    'error_code' => 'INVALID_REASON_TYPE'
                );
            }
            $trimmed_reason = trim($raw_reason);
            if ($trimmed_reason !== '') {
                $reason_length = mb_strlen($trimmed_reason, 'UTF-8');
                if ($reason_length > 255) {
                    return array(
                        'success'    => FALSE,
                        'error'      => 'سبب الحركة يجب ألا يتجاوز 255 حرفاً.',
                        'error_code' => 'REASON_TOO_LONG'
                    );
                }
                $normalized_reason = $trimmed_reason;
            }
        }

        if ($movement_type === 'adjustment') {
            if ($normalized_reason === NULL || mb_strlen($normalized_reason, 'UTF-8') < 5) {
                return array(
                    'success'    => FALSE,
                    'error'      => 'سبب التسوية إلزامي ويجب أن يتراوح طوله بين 5 و 255 حرفاً لتوضيح مبرر الحركة.',
                    'error_code' => 'REASON_REQUIRED'
                );
            }
        }

        // Bundle normalized parameters for consistent comparison
        $normalized_params = array(
            'warehouse_id'    => $warehouse_id,
            'product_id'      => $product_id,
            'movement_type'   => $movement_type,
            'quantity_delta'  => $quantity_delta,
            'reason'          => $normalized_reason,
            'idempotency_key' => $idempotency_key,
            'created_by'      => $created_by,
        );

        // 6. Idempotency Pre-Check (MUST PRECEDE active status check)
        // If a request is replayed for an already-completed movement, return the replay result
        // without altering stock, even if the product or warehouse was later deactivated.
        $existing = $this->get_by_idempotency_key($idempotency_key);
        if ($existing === FALSE) {
            // Database query failed during key lookup: do NOT assume absent, fail safely
            return array(
                'success'    => FALSE,
                'error'      => 'فشل استعلام التحقق من رمز العملية في قاعدة البيانات.',
                'error_code' => 'IDEMPOTENCY_QUERY_ERROR'
            );
        }
        if (is_object($existing)) {
            return $this->evaluate_idempotency_replay($existing, $normalized_params);
        }

        // 7. Begin Database Transaction with safe db_debug toggling
        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            // 7.1 Check trans_begin() returns TRUE explicitly
            $trans_begin = $this->db->trans_begin();
            if ($trans_begin !== TRUE || $this->db->trans_status() === FALSE) {
                $db_error = $this->db->error();
                return array(
                    'success'       => FALSE,
                    'error'         => 'تعذر بدء المعاملة في قاعدة البيانات.',
                    'error_code'    => 'TRANS_BEGIN_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            // 7.2 Protect Warehouse active status from concurrent changes (SELECT ... FOR UPDATE)
            $wh_lock_sql = "SELECT `id`, `name`, `is_active` FROM `warehouses` WHERE `id` = ? FOR UPDATE";
            $wh_lock_query = $this->db->query($wh_lock_sql, array($warehouse_id));

            if ($wh_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $db_error = $this->db->error();
                $this->db->trans_rollback();
                return array(
                    'success'       => FALSE,
                    'error'         => 'فشل تنفيذ استعلام قفل بيانات المستودع.',
                    'error_code'    => 'WAREHOUSE_LOCK_QUERY_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            $warehouse_row = $wh_lock_query->row();
            if (!$warehouse_row || (int)$warehouse_row->is_active !== 1) {
                $this->db->trans_rollback();
                return array(
                    'success'    => FALSE,
                    'error'      => 'المستودع المحدد غير موجود أو غير مفعل.',
                    'error_code' => 'INACTIVE_WAREHOUSE'
                );
            }

            // 7.3 Protect Product active status from concurrent changes (SELECT ... FOR UPDATE)
            $pr_lock_sql = "SELECT `id`, `name`, `sku`, `is_active` FROM `products` WHERE `id` = ? FOR UPDATE";
            $pr_lock_query = $this->db->query($pr_lock_sql, array($product_id));

            if ($pr_lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $db_error = $this->db->error();
                $this->db->trans_rollback();
                return array(
                    'success'       => FALSE,
                    'error'         => 'فشل تنفيذ استعلام قفل بيانات المنتج.',
                    'error_code'    => 'PRODUCT_LOCK_QUERY_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            $product_row = $pr_lock_query->row();
            if (!$product_row || (int)$product_row->is_active !== 1) {
                $this->db->trans_rollback();
                return array(
                    'success'    => FALSE,
                    'error'      => 'المنتج المحدد غير موجود أو غير مفعل.',
                    'error_code' => 'INACTIVE_PRODUCT'
                );
            }

            // 7.4 Safely ensure warehouse_stock row exists under concurrency
            // ON DUPLICATE KEY UPDATE avoids breaking uk_warehouse_product on concurrent race
            $upsert_sql = "INSERT INTO `{$this->stock_table}` (`warehouse_id`, `product_id`, `quantity`, `reorder_level`, `created_at`)
                           VALUES (?, ?, 0, 0, NOW())
                           ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)";
            $upsert_query = $this->db->query($upsert_sql, array($warehouse_id, $product_id));

            if ($upsert_query === FALSE || $this->db->trans_status() === FALSE) {
                $db_error = $this->db->error();
                $this->db->trans_rollback();
                return array(
                    'success'       => FALSE,
                    'error'         => 'فشل إنشاء أو تهيئة سجل الرصيد في المستودع.',
                    'error_code'    => 'STOCK_INIT_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            // 7.5 Lock the stock record exclusively (SELECT ... FOR UPDATE)
            $lock_sql = "SELECT `id`, `quantity` FROM `{$this->stock_table}` WHERE `warehouse_id` = ? AND `product_id` = ? FOR UPDATE";
            $lock_query = $this->db->query($lock_sql, array($warehouse_id, $product_id));

            if ($lock_query === FALSE || $this->db->trans_status() === FALSE) {
                $db_error = $this->db->error();
                $this->db->trans_rollback();
                return array(
                    'success'       => FALSE,
                    'error'         => 'فشل تنفيذ استعلام قفل رصيد المستودع.',
                    'error_code'    => 'STOCK_LOCK_QUERY_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            $stock_row = $lock_query->row();
            if (!$stock_row) {
                $this->db->trans_rollback();
                return array(
                    'success'    => FALSE,
                    'error'      => 'تعذر قفل رصيد المستودع المطلوب لإتمام الحركة.',
                    'error_code' => 'STOCK_ROW_NOT_FOUND'
                );
            }

            $before_quantity = (int)$stock_row->quantity;
            $after_quantity  = $before_quantity + $quantity_delta;

            // 7.6 Negative stock rejection
            if ($after_quantity < 0) {
                $this->db->trans_rollback();
                return array(
                    'success'         => FALSE,
                    'error'           => 'الرصيد المتاح (' . $before_quantity . ') غير كافٍ لتنفيذ الخصم المطلوب؛ الرصيد لا يمكن أن يكون سالباً.',
                    'error_code'      => 'INSUFFICIENT_STOCK',
                    'current_stock'   => $before_quantity,
                    'requested_delta' => $quantity_delta
                );
            }

            // 7.7 Integer overflow prevention (MySQL signed INT max = 2,147,483,647)
            if ($after_quantity > self::MAX_INT_VALUE) {
                $this->db->trans_rollback();
                return array(
                    'success'    => FALSE,
                    'error'      => 'الرصيد الناتج يتجاوز الحد الأقصى المسموح به للمخزون (2,147,483,647).',
                    'error_code' => 'STOCK_OVERFLOW'
                );
            }

            // 7.8 Update warehouse_stock quantity
            $update_result = $this->db->where('id', (int)$stock_row->id)->update($this->stock_table, array(
                'quantity'   => $after_quantity,
                'updated_at' => date('Y-m-d H:i:s'),
            ));

            if ($update_result === FALSE || $this->db->trans_status() === FALSE) {
                $db_error = $this->db->error();
                $this->db->trans_rollback();
                return array(
                    'success'       => FALSE,
                    'error'         => 'فشل تحديث رصيد المستودع في قاعدة البيانات.',
                    'error_code'    => 'STOCK_UPDATE_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            // 7.9 Insert movement record
            $movement_data = array(
                'warehouse_id'    => $warehouse_id,
                'product_id'      => $product_id,
                'movement_type'   => $movement_type,
                'quantity_delta'  => $quantity_delta,
                'before_quantity' => $before_quantity,
                'after_quantity'  => $after_quantity,
                'reason'          => $normalized_reason,
                'idempotency_key' => $idempotency_key,
                'created_by'      => $created_by,
                'created_at'      => date('Y-m-d H:i:s'),
            );

            $insert_result = $this->db->insert($this->table, $movement_data);
            $db_error      = $this->db->error();
            $movement_id   = $insert_result ? $this->db->insert_id() : 0;

            // 7.10 Check transaction status or DB errors immediately
            if ($insert_result === FALSE || $this->db->trans_status() === FALSE || !empty($db_error['code'])) {
                // Complete rollback of both the stock update and movement insert
                $this->db->trans_rollback();

                // Handle concurrent duplicate key (MySQL error 1062 on uk_sm_idempotency)
                if (isset($db_error['code']) && (int)$db_error['code'] === 1062) {
                    $existing_concurrent = $this->get_by_idempotency_key($idempotency_key);
                    if (is_object($existing_concurrent)) {
                        return $this->evaluate_idempotency_replay($existing_concurrent, $normalized_params);
                    }

                    // Query failed or could not read the concurrent movement safely
                    return array(
                        'success'       => FALSE,
                        'error'         => 'تعذر قراءة الحركة الأصلية المسجلة بعد اكتشاف تعارض في رمز العملية.',
                        'error_code'    => 'IDEMPOTENCY_LOOKUP_FAILED',
                        'db_error_code' => 1062
                    );
                }

                return array(
                    'success'       => FALSE,
                    'error'         => 'حدث خطأ أثناء حفظ حركة المخزون؛ تم إلغاء المعاملة بالكامل.',
                    'error_code'    => 'TRANSACTION_FAILED',
                    'db_error_code' => isset($db_error['code']) ? $db_error['code'] : NULL
                );
            }

            // 7.10.1 Central Audit Logging within Active Transaction (F10)
            if (!isset($this->Audit_model)) {
                $this->load->model('Audit_model');
            }
            $action_code = ($movement_type === 'in') ? 'stock_in' : 'stock_adjustment';
            $audit_details = ($movement_type === 'in')
                ? "إضافة مخزون (توريد) للمنتج #{$product_id} في المستودع #{$warehouse_id} بكمية +{$quantity_delta}"
                : "تسوية مخزون للمنتج #{$product_id} في المستودع #{$warehouse_id} بتغير {$quantity_delta} (السبب: {$normalized_reason})";

            $audit_meta = array(
                'warehouse_id'     => (int)$warehouse_id,
                'product_id'       => (int)$product_id,
                'movement_type'    => $movement_type,
                'quantity_delta'   => (int)$quantity_delta,
                'before_quantity'  => (int)$before_quantity,
                'after_quantity'   => (int)$after_quantity,
                'reason'           => $normalized_reason,
                'idempotency_key'  => $idempotency_key
            );

            $audit_logged = $this->Audit_model->log(
                $action_code,
                'stock_movement',
                $movement_id,
                $audit_details,
                $audit_meta,
                $created_by
            );

            if ($audit_logged === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array(
                    'success'    => FALSE,
                    'error'      => 'فشل تسجيل العملية في سجل التدقيق؛ تم التراجع عن حركة المخزون بالكامل.',
                    'error_code' => 'AUDIT_LOG_FAILED'
                );
            }

            // 7.11 Commit transaction explicitly and verify commit success
            $commit_result = $this->db->trans_commit();
            if ($commit_result === FALSE || $this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array(
                    'success'    => FALSE,
                    'error'      => 'فشل تثبيت المعاملة في قاعدة البيانات (Commit Failed).',
                    'error_code' => 'TRANS_COMMIT_FAILED'
                );
            }

            return array(
                'success'         => TRUE,
                'movement_id'     => $movement_id,
                'before_quantity' => $before_quantity,
                'after_quantity'  => $after_quantity,
                'quantity_delta'  => $quantity_delta,
                'is_replay'       => FALSE,
                'message'         => 'تم تسجيل حركة المخزون وتحديث الرصيد بنجاح.'
            );

        } catch (\Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Stock_movement_model::process_movement exception: ' . $e->getMessage());
            return array(
                'success'    => FALSE,
                'error'      => 'حدث خطأ غير متوقع أثناء معالجة حركة المخزون.',
                'error_code' => 'EXCEPTION_CAUGHT'
            );
        } finally {
            // Guarantee restoration of db_debug under all execution branches
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Compare all business-effective attributes of an existing movement against request parameters.
     * If all parameters match: return as successful idempotent replay (no stock altered).
     * If any parameter differs: return as a conflict error.
     *
     * @param object $existing
     * @param array $normalized_params
     * @return array
     */
    protected function evaluate_idempotency_replay($existing, array $normalized_params)
    {
        $same_warehouse = ((int)$existing->warehouse_id === (int)$normalized_params['warehouse_id']);
        $same_product   = ((int)$existing->product_id === (int)$normalized_params['product_id']);
        $same_type      = ($existing->movement_type === $normalized_params['movement_type']);
        $same_delta     = ((int)$existing->quantity_delta === (int)$normalized_params['quantity_delta']);
        $same_user      = ((int)$existing->created_by === (int)$normalized_params['created_by']);

        // Normalize reasons for exact byte-for-byte comparison
        $existing_reason = ($existing->reason !== NULL) ? trim((string)$existing->reason) : '';
        $param_reason    = ($normalized_params['reason'] !== NULL) ? trim((string)$normalized_params['reason']) : '';
        $same_reason     = ($existing_reason === $param_reason);

        if ($same_warehouse && $same_product && $same_type && $same_delta && $same_user && $same_reason) {
            return array(
                'success'         => TRUE,
                'movement_id'     => (int)$existing->id,
                'before_quantity' => (int)$existing->before_quantity,
                'after_quantity'  => (int)$existing->after_quantity,
                'quantity_delta'  => (int)$existing->quantity_delta,
                'is_replay'       => TRUE,
                'message'         => 'تم تأكيد استلام الحركة مسبقاً، وعُرضت النتيجة دون تكرار الخصم أو الإضافة.'
            );
        }

        return array(
            'success'    => FALSE,
            'error'      => 'تعارض: تم استخدام مفتاح الطلب (Token) نفسه مسبقاً لعملية ذات بيانات أو كميات مختلفة.',
            'error_code' => 'IDEMPOTENCY_CONFLICT'
        );
    }

    /**
     * Apply filter conditions to stock movements query
     * Rejects invalid filters explicitly instead of ignoring them silently:
     * - Invalid warehouse_id, movement_type, or date format forces 1 = 0
     * - allowed_warehouse_ids is strictly enforced as an independent authorization boundary
     *
     * @param string|null $search Product name, SKU, or Reason
     * @param mixed $warehouse_id
     * @param string|null $movement_type 'in' or 'adjustment'
     * @param string|null $start_date YYYY-MM-DD
     * @param string|null $end_date YYYY-MM-DD
     * @param array|null $allowed_warehouse_ids Authorization scope for non-admin
     * @return void
     */
    protected function apply_movement_filters($search = NULL, $warehouse_id = NULL, $movement_type = NULL, $start_date = NULL, $end_date = NULL, $allowed_warehouse_ids = NULL)
    {
        // 1. Text search
        if ($search !== NULL && trim($search) !== '') {
            $search_clean = trim($search);
            $this->db->group_start();
            $this->db->like('p.name', $search_clean);
            $this->db->or_like('p.sku', $search_clean);
            $this->db->or_like('sm.reason', $search_clean);
            $this->db->group_end();
        }

        // 2. Strict Authorization Scope (Cannot be bypassed or widened by user filter)
        $sanitized_scope = NULL;
        if ($allowed_warehouse_ids !== NULL) {
            if (empty($allowed_warehouse_ids)) {
                $this->db->where('1 = 0', NULL, FALSE);
                return;
            }

            $sanitized_scope = array_filter(array_map(function($wid) {
                return filter_var($wid, FILTER_VALIDATE_INT, array(
                    'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT_VALUE)
                ));
            }, (array)$allowed_warehouse_ids));

            if (empty($sanitized_scope)) {
                $this->db->where('1 = 0', NULL, FALSE);
                return;
            }

            $this->db->where_in('sm.warehouse_id', $sanitized_scope);
        }

        // 3. User Warehouse Filter
        if ($warehouse_id !== NULL && trim((string)$warehouse_id) !== '') {
            $valid_wid = filter_var($warehouse_id, FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 1, 'max_range' => self::MAX_UNSIGNED_INT_VALUE)
            ));

            if ($valid_wid === FALSE) {
                // Invalid warehouse filter provided: do not silently ignore, match nothing
                $this->db->where('1 = 0', NULL, FALSE);
                return;
            }

            // Ensure requested warehouse is within authorized scope
            if ($sanitized_scope !== NULL && !in_array($valid_wid, $sanitized_scope, true)) {
                $this->db->where('1 = 0', NULL, FALSE);
                return;
            }

            $this->db->where('sm.warehouse_id', $valid_wid);
        }

        // 4. Movement Type Filter
        if ($movement_type !== NULL && trim((string)$movement_type) !== '') {
            if (!in_array($movement_type, array('in', 'adjustment'), true)) {
                // Invalid movement type provided: do not silently ignore, match nothing
                $this->db->where('1 = 0', NULL, FALSE);
                return;
            }
            $this->db->where('sm.movement_type', $movement_type);
        }

        // 5. Date Range Filters (strict YYYY-MM-DD validation)
        if ($start_date !== NULL && trim((string)$start_date) !== '') {
            $clean_start = trim((string)$start_date);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean_start)) {
                $this->db->where('1 = 0', NULL, FALSE);
                return;
            }
            $this->db->where('DATE(sm.created_at) >=', $clean_start);
        }

        if ($end_date !== NULL && trim((string)$end_date) !== '') {
            $clean_end = trim((string)$end_date);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean_end)) {
                $this->db->where('1 = 0', NULL, FALSE);
                return;
            }
            $this->db->where('DATE(sm.created_at) <=', $clean_end);
        }
    }

    /**
     * Count total movements matching filters for pagination
     * Isolates db_debug with try/finally to capture failures cleanly.
     * Uses explicit COUNT(*) query rather than count_all_results() to distinguish
     * between successful count (integer >= 0) and database query error (FALSE).
     *
     * @param string|null $search
     * @param mixed $warehouse_id
     * @param string|null $movement_type
     * @param string|null $start_date
     * @param string|null $end_date
     * @param array|null $allowed_warehouse_ids
     * @return int|false Returns integer count on success (>= 0), or FALSE on database query failure
     */
    public function count_movements($search = NULL, $warehouse_id = NULL, $movement_type = NULL, $start_date = NULL, $end_date = NULL, $allowed_warehouse_ids = NULL)
    {
        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->select('COUNT(*) AS numrows');
            $this->db->from($this->table . ' sm')
                ->join('products p', 'p.id = sm.product_id', 'inner')
                ->join('warehouses w', 'w.id = sm.warehouse_id', 'inner');

            $this->apply_movement_filters($search, $warehouse_id, $movement_type, $start_date, $end_date, $allowed_warehouse_ids);

            $query = $this->db->get();
            $db_error = $this->db->error();

            if ($query === FALSE || !empty($db_error['code'])) {
                return FALSE;
            }

            $row = $query->row();
            return $row ? (int)$row->numrows : 0;
        } finally {
            $this->db->db_debug = $previous_debug;
        }
    }

    /**
     * Get paginated movements matching filters
     * Isolates db_debug with try/finally to capture failures cleanly.
     * Distinguishes between empty result set (array()) and database query error (FALSE).
     *
     * @param mixed $limit
     * @param mixed $offset
     * @param string|null $search
     * @param mixed $warehouse_id
     * @param string|null $movement_type
     * @param string|null $start_date
     * @param string|null $end_date
     * @param array|null $allowed_warehouse_ids
     * @return array|false Returns array of movement objects (may be empty), or FALSE on database query failure
     */
    public function get_movements_paged($limit, $offset, $search = NULL, $warehouse_id = NULL, $movement_type = NULL, $start_date = NULL, $end_date = NULL, $allowed_warehouse_ids = NULL)
    {
        $valid_limit = filter_var($limit, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100)));
        $valid_offset = filter_var($offset, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));

        $limit_clean = ($valid_limit !== FALSE) ? $valid_limit : 10;
        $offset_clean = ($valid_offset !== FALSE) ? $valid_offset : 0;

        $previous_debug = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            $this->db->select('sm.*, w.name AS warehouse_name, w.code AS warehouse_code, p.name AS product_name, p.sku AS product_sku, u.full_name AS created_by_name, u.username AS created_by_username')
                ->from($this->table . ' sm')
                ->join('products p', 'p.id = sm.product_id', 'inner')
                ->join('warehouses w', 'w.id = sm.warehouse_id', 'inner')
                ->join('users u', 'u.id = sm.created_by', 'inner');

            $this->apply_movement_filters($search, $warehouse_id, $movement_type, $start_date, $end_date, $allowed_warehouse_ids);

            $this->db->order_by('sm.id', 'DESC')
                ->limit($limit_clean, $offset_clean);

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
}
