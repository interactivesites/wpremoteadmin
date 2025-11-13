<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Database.php';

require_login();

$db = new Database();
$contract_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$contract = $db->get_contract_by_id($contract_id);

if (!$contract) {
    header('Location: /index.php');
    exit;
}

$site = $db->get_site($contract['site_id']);
$options = $db->get_contract_options($contract_id);
$payment_status = $db->get_payment_status($contract);

// Serve PDF file
if (!empty($contract['contract_file_path'])) {
    $file_path = __DIR__ . '/uploads/contracts/' . basename($contract['contract_file_path']);
    if (file_exists($file_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($contract['contract_file_path']) . '"');
        readfile($file_path);
        exit;
    }
}

header('Location: /index.php');
exit;

