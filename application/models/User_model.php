<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends MY_Model
{
    protected $table = 'users';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Normalize username: trim spaces and convert to lowercase
     *
     * @param string $username
     * @return string
     */
    public function normalize_username($username)
    {
        return strtolower(trim((string)$username));
    }

    /**
     * Normalize email: trim, lowercase or null if empty
     *
     * @param string|null $email
     * @return string|null
     */
    public function normalize_email($email)
    {
        $trimmed = trim((string)$email);
        return ($trimmed !== '') ? strtolower($trimmed) : null;
    }

    /**
     * Get user by ID with role info (without password hash)
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        return $this->db->select('u.id, u.username, u.full_name, u.email, u.role_id, u.is_active, u.created_at, u.updated_at, u.last_login_at, r.name AS role_name, r.display_name AS role_display_name')
            ->from($this->table . ' u')
            ->join('roles r', 'r.id = u.role_id', 'inner')
            ->where('u.id', (int)$id)
            ->get()
            ->row();
    }

    /**
     * Get user by username including password_hash for authentication
     *
     * @param string $username
     * @return object|null
     */
    public function get_by_username_for_auth($username)
    {
        $clean_username = $this->normalize_username($username);
        return $this->db->select('u.id, u.username, u.full_name, u.email, u.password_hash, u.role_id, u.is_active, u.last_login_at, r.name AS role_name, r.display_name AS role_display_name')
            ->from($this->table . ' u')
            ->join('roles r', 'r.id = u.role_id', 'inner')
            ->where('u.username', $clean_username)
            ->get()
            ->row();
    }

    /**
     * Get all users with role details (excluding password_hash)
     *
     * @return array
     */
    public function get_all_users()
    {
        return $this->db->select('u.id, u.username, u.full_name, u.email, u.role_id, u.is_active, u.created_at, u.updated_at, u.last_login_at, r.name AS role_name, r.display_name AS role_display_name')
            ->from($this->table . ' u')
            ->join('roles r', 'r.id = u.role_id', 'inner')
            ->order_by('u.id', 'ASC')
            ->get()
            ->result();
    }

    /**
     * Count users matching search and filters
     *
     * @param string|null $search
     * @param int|null $role_id
     * @param int|null $status
     * @param string|null $role_name
     * @return int
     */
    public function count_users($search = NULL, $role_id = NULL, $status = NULL, $role_name = NULL)
    {
        $this->db->from($this->table . ' u')
            ->join('roles r', 'r.id = u.role_id', 'inner');

        if ($search !== NULL && trim($search) !== '') {
            $this->db->group_start();
            $this->db->like('u.username', trim($search));
            $this->db->or_like('u.full_name', trim($search));
            $this->db->or_like('u.email', trim($search));
            $this->db->group_end();
        }

        if ($role_id !== NULL && (int)$role_id > 0) {
            $this->db->where('u.role_id', (int)$role_id);
        }

        if ($role_name !== NULL && trim($role_name) !== '') {
            $this->db->where('r.name', trim($role_name));
        }

        if ($status !== NULL && $status !== '') {
            $this->db->where('u.is_active', (int)$status);
        }

        return (int)$this->db->count_all_results();
    }

    /**
     * Get paginated users matching search and filters
     *
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @param int|null $role_id
     * @param int|null $status
     * @param string|null $role_name
     * @return array
     */
    public function get_users_paged($limit, $offset, $search = NULL, $role_id = NULL, $status = NULL, $role_name = NULL)
    {
        $this->db->select('u.id, u.username, u.full_name, u.email, u.role_id, u.is_active, u.created_at, u.updated_at, u.last_login_at, r.name AS role_name, r.display_name AS role_display_name')
            ->from($this->table . ' u')
            ->join('roles r', 'r.id = u.role_id', 'inner')
            ->order_by('u.id', 'DESC');

        if ($search !== NULL && trim($search) !== '') {
            $this->db->group_start();
            $this->db->like('u.username', trim($search));
            $this->db->or_like('u.full_name', trim($search));
            $this->db->or_like('u.email', trim($search));
            $this->db->group_end();
        }

        if ($role_id !== NULL && (int)$role_id > 0) {
            $this->db->where('u.role_id', (int)$role_id);
        }

        if ($role_name !== NULL && trim($role_name) !== '') {
            $this->db->where('r.name', trim($role_name));
        }

        if ($status !== NULL && $status !== '') {
            $this->db->where('u.is_active', (int)$status);
        }

        return $this->db->limit((int)$limit, (int)$offset)->get()->result();
    }

    /**
     * Create a new user
     *
     * @param array $data
     * @return int Insert ID
     */
    public function create_user(array $data)
    {
        $insert_data = array(
            'username'      => $this->normalize_username($data['username']),
            'full_name'     => trim($data['full_name']),
            'email'         => $this->normalize_email(isset($data['email']) ? $data['email'] : null),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role_id'       => (int)$data['role_id'],
            'is_active'     => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => null,
            'last_login_at' => null,
        );

        $this->db->insert($this->table, $insert_data);
        return $this->db->insert_id();
    }

    /**
     * Update user details (excluding password)
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update_user($id, array $data)
    {
        $update_data = array(
            'updated_at' => date('Y-m-d H:i:s'),
        );

        if (isset($data['username'])) {
            $update_data['username'] = $this->normalize_username($data['username']);
        }
        if (isset($data['full_name'])) {
            $update_data['full_name'] = trim($data['full_name']);
        }
        if (array_key_exists('email', $data)) {
            $update_data['email'] = $this->normalize_email($data['email']);
        }
        if (isset($data['role_id'])) {
            $update_data['role_id'] = (int)$data['role_id'];
        }
        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int)$data['is_active'];
        }

        return $this->db->where('id', (int)$id)->update($this->table, $update_data);
    }

    /**
     * Update user password
     *
     * @param int $id
     * @param string $plain_password
     * @return bool
     */
    public function update_password($id, $plain_password)
    {
        $update_data = array(
            'password_hash' => password_hash($plain_password, PASSWORD_BCRYPT),
            'updated_at'    => date('Y-m-d H:i:s'),
        );

        return $this->db->where('id', (int)$id)->update($this->table, $update_data);
    }

    /**
     * Update last_login_at timestamp
     *
     * @param int $id
     * @return bool
     */
    public function update_last_login($id)
    {
        return $this->db->where('id', (int)$id)
            ->update($this->table, array('last_login_at' => date('Y-m-d H:i:s')));
    }

    /**
     * Count active admin users
     *
     * @return int
     */
    public function count_active_admins()
    {
        return (int)$this->db->from($this->table . ' u')
            ->join('roles r', 'r.id = u.role_id', 'inner')
            ->where('r.name', 'admin')
            ->where('u.is_active', 1)
            ->count_all_results();
    }

    /**
     * Check if username exists
     *
     * @param string $username
     * @param int|null $exclude_id
     * @return bool
     */
    public function username_exists($username, $exclude_id = null)
    {
        $clean = $this->normalize_username($username);
        $this->db->from($this->table)->where('username', $clean);
        if ($exclude_id !== null) {
            $this->db->where('id !=', (int)$exclude_id);
        }
        return ($this->db->count_all_results() > 0);
    }

    /**
     * Check if email exists
     *
     * @param string $email
     * @param int|null $exclude_id
     * @return bool
     */
    public function email_exists($email, $exclude_id = null)
    {
        $clean = $this->normalize_email($email);
        if ($clean === null) {
            return false;
        }

        $this->db->from($this->table)->where('email', $clean);
        if ($exclude_id !== null) {
            $this->db->where('id !=', (int)$exclude_id);
        }
        return ($this->db->count_all_results() > 0);
    }
}
