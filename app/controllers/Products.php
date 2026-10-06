<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Products extends Controller
{
    private function api() { return $this->call->library('api'); }
    private function model() { return $this->call->model('Product_model'); }
    private function input()
    {
        $data = $this->api()->body();
        if (!is_array($data) || !$data) $this->api()->respond_error('A JSON object is required.', 400);
        return $data;
    }
    private function fields($data, $partial = false)
    {
        $fields = ['product_name', 'description', 'price', 'quantity'];
        $errors = [];
        if (!$partial) foreach ($fields as $field) if (!array_key_exists($field, $data)) $errors[$field] = 'This field is required.';
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            if ($field === 'product_name') {
                $length = is_string($value) ? (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) : 0;
                if (!is_string($value) || trim($value) === '' || $length > 100) $errors[$field] = 'Name is required and must be at most 100 characters.';
                else $data[$field] = trim($value);
            } elseif ($field === 'price') {
                if (!is_numeric($value) || (float)$value < 0) $errors[$field] = 'Price must be numeric and at least zero.';
                else $data[$field] = number_format((float)$value, 2, '.', '');
            } elseif ($field === 'quantity') {
                if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) $errors[$field] = 'Quantity must be an integer of at least zero.';
                else $data[$field] = (int)$value;
            } elseif ($field === 'description' && !is_string($value)) $errors[$field] = 'Description must be text.';
        }
        if ($errors) $this->api()->respond(['message' => 'Validation failed.', 'errors' => $errors], 422);
        return array_intersect_key($data, array_flip($fields));
    }
    private function id($id)
    {
        if (!ctype_digit((string)$id) || (int)$id < 1) $this->api()->respond_error('Invalid product ID.', 400);
        return (int)$id;
    }
    public function index()
    {
        $this->api()->require_jwt();
        $this->api()->respond(['data' => $this->model()->all_products()]);
    }
    public function show($id)
    {
        $this->api()->require_jwt();
        $product = $this->model()->find_product($this->id($id));
        if (!$product) $this->api()->respond_error('Product not found.', 404);
        $this->api()->respond(['data' => $product]);
    }
    public function create()
    {
        $this->api()->require_jwt();
        $product = $this->model()->create_product($this->fields($this->input()));
        $this->api()->respond(['message' => 'Product created.', 'data' => $product], 201);
    }
    public function update($id)
    {
        $this->api()->require_jwt();
        $id = $this->id($id);
        if (!$this->model()->find_product($id)) $this->api()->respond_error('Product not found.', 404);
        $fields = $this->fields($this->input(), true);
        if (!$fields) $this->api()->respond(['message' => 'Validation failed.', 'errors' => ['product' => 'Provide at least one field.']], 422);
        $this->model()->update_product($id, $fields);
        $this->api()->respond(['message' => 'Product updated.', 'data' => $this->model()->find_product($id)]);
    }
    public function destroy($id)
    {
        $this->api()->require_jwt();
        $id = $this->id($id);
        if (!$this->model()->find_product($id)) $this->api()->respond_error('Product not found.', 404);
        $this->model()->delete_product($id);
        $this->api()->respond(['message' => 'Product deleted.']);
    }
}
