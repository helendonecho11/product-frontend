<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Products extends Controller
{
    private function api()
    {
        return $this->call->library('api');
    }

    private function db()
    {
        return $this->call->database();
    }

    private function ensureUsersTable()
    {
        $this->call->dbforge();
        $lava = lava_instance();
        if ($lava->dbforge->table_exists('users')) return;

        $lava->dbforge
            ->add_field([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => TRUE, 'auto_increment' => TRUE, 'null' => FALSE],
                'username' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => FALSE],
                'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => FALSE, 'unique' => TRUE],
                'password' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => FALSE],
                'role' => ['type' => 'ENUM', 'constraint' => "'admin','moderator','user'", 'null' => FALSE, 'default' => 'user'],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => TRUE, 'null' => FALSE, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => FALSE, 'default' => 'CURRENT_TIMESTAMP'],
                'updated_at' => ['type' => 'DATETIME', 'null' => TRUE, 'default' => NULL],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('username', unique: TRUE, name: 'username_unique')
            ->add_key('email', name: 'email_idx')
            ->add_key('role', name: 'role_idx')
            ->create_table('users');
    }

    private function input()
    {
        $data = $this->api()->body();
        if (!is_array($data)) {
            $this->api()->respond_error('A JSON object is required.', 400);
        }
        return $data;
    }

    private function productFields($data, $partial = false)
    {
        $fields = ['product_name', 'description', 'price', 'quantity'];
        foreach ($fields as $field) {
            if (!$partial && !array_key_exists($field, $data)) {
                $this->api()->respond_error('Missing field: ' . $field, 422);
            }
        }

        $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            if ($field === 'product_name') {
                $nameLength = is_string($value) ? (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) : 0;
                if (!is_string($value) || trim($value) === '' || $nameLength > 100) {
                    $this->api()->respond_error('Product name is required and must be at most 100 characters.', 422);
                }
                $values[$field] = trim($value);
            } elseif ($field === 'description') {
                if (!is_string($value)) $this->api()->respond_error('Description must be text.', 422);
                $values[$field] = $value;
            } elseif ($field === 'price') {
                if (!is_numeric($value) || (float)$value < 0 || !is_finite((float)$value)) {
                    $this->api()->respond_error('Price must be a non-negative number.', 422);
                }
                $values[$field] = number_format((float)$value, 2, '.', '');
            } else {
                if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) {
                    $this->api()->respond_error('Quantity must be a non-negative integer.', 422);
                }
                $values[$field] = (int)$value;
            }
        }
        return $values;
    }

    public function login()
    {
        $api = $this->api();
        $api->rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);
        $data = $this->input();
        $this->ensureUsersTable();
        $this->ensureAdminAccount();
        $username = trim((string)($data['username'] ?? ''));
        $user = $username === '' ? null : $this->db()->table('users')->where('username', $username)->row_array();
        if (!$user || (int)$user['is_active'] !== 1 || !password_verify((string)($data['password'] ?? ''), $user['password'])) {
            $api->respond_error('Invalid username or password.', 401);
        }

        $token = $api->encode_jwt(['sub' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role'], 'scope' => ['products:read', 'products:write']]);
        $api->respond(['token' => $token, 'token_type' => 'Bearer', 'expires_in' => 900, 'username' => $user['username'], 'role' => $user['role']]);
    }

    public function register()
    {
        $api = $this->api();
        $api->rate_limit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 60);
        $data = $this->input();
        $this->ensureUsersTable();
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($username === '' || strlen($username) > 100 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $api->respond_error('Username must be 1–100 characters using letters, numbers, dots, dashes, or underscores.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $api->respond_error('Enter a valid email address.', 422);
        }
        if (strlen($password) < 8) {
            $api->respond_error('Password must be at least 8 characters.', 422);
        }

        $db = $this->db();
        if ($db->table('users')->where('username', $username)->row_array()
            || $db->table('users')->where('email', $email)->row_array()) {
            $api->respond_error('That username or email is already registered.', 409);
        }

        $db->table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',
            'is_active' => 1,
        ]);
        $user = $db->table('users')->where('id', $db->last_id())->row_array();
        $token = $api->encode_jwt(['sub' => (int)$user['id'], 'username' => $user['username'], 'role' => 'user', 'scope' => ['products:read', 'products:write']]);
        $api->respond(['message' => 'Account created.', 'token' => $token, 'token_type' => 'Bearer', 'expires_in' => 900, 'username' => $user['username'], 'role' => 'user'], 201);
    }

    private function ensureAdminAccount()
    {
        $username = getenv('APP_ADMIN_USERNAME') ?: 'Admin';
        $passwordHash = getenv('APP_ADMIN_PASSWORD_HASH') ?: '';
        if ($passwordHash === '') return;

        $db = $this->db();
        $existing = $db->table('users')->where('username', $username)->row_array();
        if ($existing) {
            if ($existing['role'] !== 'admin' || !hash_equals($passwordHash, (string)$existing['password'])) {
                $db->table('users')->where('id', (int)$existing['id'])->update([
                    'password' => $passwordHash,
                    'role' => 'admin',
                    'is_active' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return;
        }

        $db->table('users')->insert([
            'username' => $username,
            'email' => getenv('APP_ADMIN_EMAIL') ?: 'admin@stockroom.local',
            'password' => $passwordHash,
            'role' => 'admin',
            'is_active' => 1,
        ]);
    }

    public function index()
    {
        $api = $this->api();
        $api->require_jwt();
        $products = $this->db()->table('products')->order_by('id', 'DESC')->result_array();
        $api->respond(['data' => $products]);
    }

    public function create()
    {
        $api = $this->api();
        $api->require_jwt();
        $fields = $this->productFields($this->input());
        $fields['created_at'] = date('Y-m-d H:i:s');
        $db = $this->db();
        $db->table('products')->insert($fields);
        $product = $db->table('products')->where('id', $db->last_id())->row_array();
        $api->respond(['message' => 'Product created.', 'data' => $product], 201);
    }

    public function update($id)
    {
        $api = $this->api();
        $api->require_jwt();
        if (!ctype_digit((string)$id) || (int)$id < 1) $api->respond_error('Invalid product ID.', 400);
        $db = $this->db();
        $existing = $db->table('products')->where('id', (int)$id)->row_array();
        if (!$existing) $api->respond_error('Product not found.', 404);
        $fields = $this->productFields($this->input(), true);
        if (!$fields) $api->respond_error('Provide at least one product field to update.', 422);
        $db->table('products')->where('id', (int)$id)->update($fields);
        $product = $db->table('products')->where('id', (int)$id)->row_array();
        $api->respond(['message' => 'Product updated.', 'data' => $product]);
    }

    public function destroy($id)
    {
        $api = $this->api();
        $api->require_jwt();
        if (!ctype_digit((string)$id) || (int)$id < 1) $api->respond_error('Invalid product ID.', 400);
        $db = $this->db();
        $existing = $db->table('products')->where('id', (int)$id)->row_array();
        if (!$existing) $api->respond_error('Product not found.', 404);
        $db->table('products')->where('id', (int)$id)->delete();
        $api->respond(['message' => 'Product deleted.']);
    }
}
