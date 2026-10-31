<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Central Audit Service
 *
 * Exposes a clean, reusable API for controllers and business services to log
 * critical domain events without duplicating logging or database access logic.
 */
class Audit_service
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        if (!isset($this->CI->Audit_model)) {
            $this->CI->load->model('Audit_model');
        }
    }

    /**
     * Dispatch an audit log event
     *
     * @param string $action
     * @param string $entity_type
     * @param int|null $entity_id
     * @param string $details
     * @param array $metadata
     * @param int|null $user_id
     * @return int|bool
     */
    public function log($action, $entity_type, $entity_id, $details, array $metadata = array(), $user_id = NULL)
    {
        return $this->CI->Audit_model->log($action, $entity_type, $entity_id, $details, $metadata, $user_id);
    }

    /**
     * Log user management event
     *
     * @param string $action
     * @param int $target_user_id
     * @param string $details
     * @param array $metadata
     * @return int|bool
     */
    public function log_user($action, $target_user_id, $details, array $metadata = array())
    {
        return $this->log($action, 'user', $target_user_id, $details, $metadata);
    }

    /**
     * Log master data event (product, category, warehouse, customer)
     *
     * @param string $action
     * @param string $entity_type
     * @param int $entity_id
     * @param string $details
     * @param array $metadata
     * @return int|bool
     */
    public function log_master_data($action, $entity_type, $entity_id, $details, array $metadata = array())
    {
        return $this->log($action, $entity_type, $entity_id, $details, $metadata);
    }

    /**
     * Log inventory movement event
     *
     * @param string $action
     * @param int $movement_id
     * @param string $details
     * @param array $metadata
     * @param int|null $user_id
     * @return int|bool
     */
    public function log_inventory($action, $movement_id, $details, array $metadata = array(), $user_id = NULL)
    {
        return $this->log($action, 'stock_movement', $movement_id, $details, $metadata, $user_id);
    }

    /**
     * Log sales invoice event
     *
     * @param string $action
     * @param int $invoice_id
     * @param string $details
     * @param array $metadata
     * @param int|null $user_id
     * @return int|bool
     */
    public function log_sales($action, $invoice_id, $details, array $metadata = array(), $user_id = NULL)
    {
        return $this->log($action, 'sales_invoice', $invoice_id, $details, $metadata, $user_id);
    }
}

