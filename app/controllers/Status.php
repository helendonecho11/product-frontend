<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Status extends Controller
{
    public function index()
    {
        $this->call->library('api')->respond([
            'name' => 'Product Management API',
            'status' => 'running',
        ]);
    }
}
