<?php
/**
 * Check Password API - validates against custom passwords set via forgot-password
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

$email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false]);
    exit;
}

// Check custom passwords file
if (!file_exists(PASSWORDS_FILE)) {
    echo json_encode(['success' => false]);
    exit;
}

$passwords = json_decode(file_get_contents(PASSWORDS_FILE), true);
if (!is_array($passwords)) {
    echo json_encode(['success' => false]);
    exit;
}

foreach ($passwords as $entry) {
    if (strtolower($entry['email']) === $email && password_verify($password, $entry['password'])) {
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);
?>
