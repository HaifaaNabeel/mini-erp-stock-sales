<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role_model extends MY_Model
{
    protected $table = 'roles';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get all roles
     *
     * @return array
     */
    public function get_all()
    {
        return $this->db->order_by('id', 'ASC')->get($this->table)->result();
    }

    /**
     * Get role by ID
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        return $this->db->where('id', (int)$id)->get($this->table)->row();
    }

    /**
     * Get role by internal name (e.g. 'admin', 'warehouse', 'sales')
     *
     * @param string $name
     * @return object|null
     */
    public function get_by_name($name)
    {
        return $this->db->where('name', trim($name))->get($this->table)->row();
    }
}
