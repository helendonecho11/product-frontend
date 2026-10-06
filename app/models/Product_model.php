<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Product_model extends Model
{
    private function db() { return lava_instance()->call->database(); }
    public function all_products() { return $this->db()->table('products')->order_by('id', 'DESC')->result_array(); }
    public function find_product($id) { return $this->db()->table('products')->where('id', $id)->row_array(); }
    public function create_product($fields)
    {
        $this->db()->table('products')->insert($fields);
        return $this->find_product($this->db()->last_id());
    }
    public function update_product($id, $fields) { return $this->db()->table('products')->where('id', $id)->update($fields); }
    public function delete_product($id) { return $this->db()->table('products')->where('id', $id)->delete(); }
}
