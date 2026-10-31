<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_warehouse_model extends MY_Model
{
    protected $table = 'user_warehouses';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get warehouses assigned to a specific user
     *
     * @param int $user_id
     * @return array
     */
    public function get_warehouses_by_user($user_id)
    {
        return $this->db->select('w.*, uw.created_at AS assigned_at')
            ->from($this->table . ' uw')
            ->join('warehouses w', 'w.id = uw.warehouse_id', 'inner')
            ->where('uw.user_id', (int)$user_id)
            ->order_by('w.name', 'ASC')
            ->get()
            ->result();
    }

    /**
     * Get array of warehouse IDs assigned to a user
     *
     * @param int $user_id
     * @return array
     */
    public function get_assigned_warehouse_ids($user_id)
    {
        $rows = $this->db->select('warehouse_id')
            ->from($this->table)
            ->where('user_id', (int)$user_id)
            ->get()
            ->result_array();

        return array_map(function($r) {
            return (int)$r['warehouse_id'];
        }, $rows);
    }

    /**
     * Get users assigned to a specific warehouse
     *
     * @param int $warehouse_id
     * @return array
     */
    public function get_users_by_warehouse($warehouse_id)
    {
        return $this->db->select('u.id, u.username, u.full_name, u.email, u.is_active, r.name AS role_name, r.display_name AS role_display_name, uw.created_at AS assigned_at')
            ->from($this->table . ' uw')
            ->join('users u', 'u.id = uw.user_id', 'inner')
            ->join('roles r', 'r.id = u.role_id', 'inner')
            ->where('uw.warehouse_id', (int)$warehouse_id)
            ->order_by('u.full_name', 'ASC')
            ->get()
            ->result();
    }

    /**
     * Check if a specific user is assigned to a warehouse
     *
     * @param int $user_id
     * @param int $warehouse_id
     * @return bool
     */
    public function is_assigned($user_id, $warehouse_id)
    {
        return ($this->db->from($this->table)
            ->where('user_id', (int)$user_id)
            ->where('warehouse_id', (int)$warehouse_id)
            ->count_all_results() > 0);
    }

    /**
     * Assign user to warehouse (composite PK prevents duplicates)
     *
     * @param int $user_id
     * @param int $warehouse_id
     * @return bool
     */
    public function assign($user_id, $warehouse_id)
    {
        if ($this->is_assigned($user_id, $warehouse_id)) {
            return TRUE;
        }

        return $this->db->insert($this->table, array(
            'user_id'      => (int)$user_id,
            'warehouse_id' => (int)$warehouse_id,
            'created_at'   => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * Remove assignment
     *
     * @param int $user_id
     * @param int $warehouse_id
     * @return bool
     */
    public function remove($user_id, $warehouse_id)
    {
        return $this->db->where('user_id', (int)$user_id)
            ->where('warehouse_id', (int)$warehouse_id)
            ->delete($this->table);
    }

    /**
     * Sync user warehouses (replace existing assignments for user)
     *
     * @param int $user_id
     * @param array $warehouse_ids
     * @return void
     */
    public function sync_user_warehouses($user_id, array $warehouse_ids)
    {
        // Remove existing assignments for this user
        $this->db->where('user_id', (int)$user_id)->delete($this->table);

        // Filter and insert new assignments
        $clean_ids = array_unique(array_filter(array_map('intval', $warehouse_ids)));

        foreach ($clean_ids as $wid) {
            if ($wid > 0) {
                $this->db->insert($this->table, array(
                    'user_id'      => (int)$user_id,
                    'warehouse_id' => $wid,
                    'created_at'   => date('Y-m-d H:i:s'),
                ));
            }
        }

        if (!isset($this->Audit_model)) {
            $this->load->model('Audit_model');
        }
        $this->Audit_model->log(
            'user_warehouses_assign',
            'user_warehouses',
            (int)$user_id,
            "تحديث تعيين مستودعات المستخدم #{$user_id}",
            array('assigned_warehouse_ids' => array_values($clean_ids))
        );
    }

    /**
     * Sync warehouse users (replace existing assignments for warehouse)
     *
     * @param int $warehouse_id
     * @param array $user_ids
     * @return void
     */
    public function sync_warehouse_users($warehouse_id, array $user_ids)
    {
        $this->db->where('warehouse_id', (int)$warehouse_id)->delete($this->table);

        $clean_ids = array_unique(array_filter(array_map('intval', $user_ids)));

        foreach ($clean_ids as $uid) {
            if ($uid > 0) {
                $this->db->insert($this->table, array(
                    'user_id'      => $uid,
                    'warehouse_id' => (int)$warehouse_id,
                    'created_at'   => date('Y-m-d H:i:s'),
                ));
            }
        }
    }

    /**
     * Get eligible users for warehouse assignment (warehouse and sales users)
     *
     * @return array
     */
    public function get_eligible_users()
    {
        return $this->db->select('u.id, u.username, u.full_name, u.is_active, r.name AS role_name, r.display_name AS role_display_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id', 'inner')
            ->where_in('r.name', array('warehouse', 'sales'))
            ->where('u.is_active', 1)
            ->order_by('u.full_name', 'ASC')
            ->get()
            ->result();
    }
}

