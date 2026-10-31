<?php
/**
 * F08 Concurrent Invoice Cancellation Worker
 *
 * Invoked as an independent PHP CLI process to test real race conditions
 * and database transaction row locking under concurrent cancellation requests.
 *
 * Usage: php tests/f08_concurrent_cancel_worker.php <input_json_file> <output_json_file>
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only.\n");
}

if ($argc < 3) {
    echo json_encode(array('success' => FALSE, 'error' => 'Missing arguments'));
    exit(1);
}

$input_file  = $argv[1];
$output_file = $argv[2];

if (!file_exists($input_file)) {
    file_put_contents($output_file, json_encode(array('success' => FALSE, 'error' => 'Input file does not exist')));
    exit(1);
}

$payload = json_decode(file_get_contents($input_file), TRUE);
if (!$payload) {
    file_put_contents($output_file, json_encode(array('success' => FALSE, 'error' => 'Invalid JSON input')));
    exit(1);
}

$invoice_id = (int)$payload['invoice_id'];
$user_id    = (int)$payload['user_id'];
$reason     = (string)$payload['reason'];

// Reset server vars before booting CodeIgniter to prevent URI routing issues with Arabic/spaces
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['argv'] = array('index.php');
$_SERVER['argc'] = 1;
$argv = array('index.php');
$argc = 1;

ob_start();
require_once dirname(__DIR__) . '/index.php';
ob_end_clean();

$CI =& get_instance();
$CI->load->model('Sales_invoice_model');
$CI->load->model('User_model');

$user = $CI->User_model->get_by_id($user_id);
if (!$user) {
    file_put_contents($output_file, json_encode(array('success' => FALSE, 'error' => 'User not found')));
    exit(1);
}

// Microsecond jitter to provoke concurrent lock contention
usleep(random_int(1000, 10000));

$result = $CI->Sales_invoice_model->cancel_invoice($invoice_id, $reason, $user);
file_put_contents($output_file, json_encode($result, JSON_UNESCAPED_UNICODE));
exit(0);
