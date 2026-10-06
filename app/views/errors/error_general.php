<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$status = http_response_code();
if (!$status || $status < 400) $status = 500;
$messages = [
    400 => 'Bad request.',
    403 => 'Forbidden.',
    404 => 'Not found.',
    405 => 'Method not allowed.',
];
$message = $messages[$status] ?? 'Internal server error.';
if (isset($heading) && $status < 500 && is_string($heading)) $message = $heading;

if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => $message, 'status' => $status], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);