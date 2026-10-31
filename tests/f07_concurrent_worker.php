<?php
/**
 * Worker script for F07 Concurrent Invoice Creation testing.
 * Executed as an independent process with its own database connection.
 * Reads input from input_file (argument 1) to avoid CI3 URI routing character restrictions.
 */

if ($argc < 3) {
    echo json_encode(array('success' => FALSE, 'error' => 'Missing arguments'));
    exit(1);
}

$input_file   = $argv[1];
$output_file  = $argv[2];

if (!file_exists($input_file)) {
    file_put_contents($output_file, json_encode(array('success' => FALSE, 'error' => 'Input file does not exist')));
    exit(1);
}

$raw_json = file_get_contents($input_file);
$worker_payload = json_decode($raw_json, TRUE);
if (!$worker_payload) {
    file_put_contents($output_file, json_encode(array('success' => FALSE, 'error' => 'Invalid JSON input in file')));
    exit(1);
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['argv'] = array('index.php');
$_SERVER['argc'] = 1;
$argv = array('index.php');
$argc = 1;
ob_start();
require_once __DIR__ . '/../index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('Sales_invoice_model');
$CI->load->model('User_model');

$user = $CI->User_model->get_by_id((int)$worker_payload['user_id']);

$invoice_data = array(
    'customer_id'         => (int)$worker_payload['customer_id'],
    'warehouse_id'        => (int)$worker_payload['warehouse_id'],
    'invoice_date'        => $worker_payload['invoice_date'],
    'discount_percentage' => isset($worker_payload['discount_percentage']) ? (float)$worker_payload['discount_percentage'] : 0.00,
    'notes'               => isset($worker_payload['notes']) ? $worker_payload['notes'] : NULL,
    'idempotency_key'     => isset($worker_payload['idempotency_key']) ? $worker_payload['idempotency_key'] : NULL,
);

$items = $worker_payload['items'];

$start_time = microtime(TRUE);
$result = $CI->Sales_invoice_model->create_invoice($invoice_data, $items, $user);
$end_time = microtime(TRUE);

$result['start_time'] = $start_time;
$result['end_time']   = $end_time;
$result['duration']   = $end_time - $start_time;

file_put_contents($output_file, json_encode($result));
exit($result['success'] ? 0 : 2);
