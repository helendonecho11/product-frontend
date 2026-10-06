<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller
{
    private function api() { return $this->call->library('api'); }
    private function db() { return $this->call->database(); }
    private function input()
    {
        $data = $this->api()->body();
        if (!is_array($data) || !$data) $this->api()->respond_error('A JSON object is required.', 400);
        return $data;
    }
    private function publicUser($user)
    {
        return ['id' => (int)$user['id'], 'username' => $user['username'], 'email' => $user['email'], 'role' => $user['role']];
    }
    public function login()
    {
        $api = $this->api();
        $data = $this->input();
        $user = $this->db()->table('users')->where('username', (string)($data['username'] ?? ''))->row_array();
        if (!$user || (int)$user['is_active'] !== 1 || !password_verify((string)($data['password'] ?? ''), $user['password'])) $api->respond_error('Invalid username or password.', 401);
        $tokens = $api->issue_tokens(['id' => (int)$user['id'], 'role' => $user['role'], 'scopes' => ['read', 'write', 'delete']]);
        $api->respond(['message' => 'Login successful.', 'tokens' => $tokens, 'user' => $this->publicUser($user)]);
    }
    public function refresh()
    {
        $data = $this->input();
        if (empty($data['refresh_token'])) $this->api()->respond_error('Refresh token is required.', 422);
        $this->db();
        $this->api()->refresh_access_token((string)$data['refresh_token']);
    }
    public function logout()
    {
        $data = $this->input();
        if (!empty($data['refresh_token'])) { $this->db(); $this->api()->revoke_refresh_token((string)$data['refresh_token']); }
        $this->api()->respond(['message' => 'Logged out.']);
    }
    public function me()
    {
        $payload = $this->api()->require_jwt();
        $user = $this->db()->table('users')->where('id', (int)$payload['sub'])->row_array();
        if (!$user || (int)$user['is_active'] !== 1) $this->api()->respond_error('Unauthorized', 401);
        $this->api()->respond(['user' => $this->publicUser($user)]);
    }
}
