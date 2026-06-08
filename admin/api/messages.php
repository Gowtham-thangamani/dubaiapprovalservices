<?php
/**
 * Messages API for Dubai Approval Services Admin Panel
 * Handles reading, marking as read, and deleting contact form messages
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');

$data_dir = dirname(__DIR__) . '/data';
$file_path = $data_dir . '/messages.json';

if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

function get_messages($file_path) {
    if (!file_exists($file_path)) return [];
    $content = file_get_contents($file_path);
    if (!$content) return [];
    $messages = json_decode($content, true);
    return is_array($messages) ? $messages : [];
}

function save_messages($file_path, $messages) {
    file_put_contents($file_path, json_encode($messages, JSON_PRETTY_PRINT));
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $messages = get_messages($file_path);
        $unread = 0;
        foreach ($messages as $msg) {
            if (empty($msg['read'])) $unread++;
        }
        echo json_encode([
            'success' => true,
            'total' => count($messages),
            'unread' => $unread,
            'messages' => $messages
        ]);
        break;

    case 'count':
        $messages = get_messages($file_path);
        $unread = 0;
        foreach ($messages as $msg) {
            if (empty($msg['read'])) $unread++;
        }
        echo json_encode([
            'success' => true,
            'total' => count($messages),
            'unread' => $unread
        ]);
        break;

    case 'read':
        $id = isset($_GET['id']) ? $_GET['id'] : '';
        if (empty($id)) {
            echo json_encode(['success' => false, 'error' => 'Missing message ID']);
            break;
        }
        $messages = get_messages($file_path);
        foreach ($messages as &$msg) {
            if ($msg['id'] === $id) { $msg['read'] = true; break; }
        }
        save_messages($file_path, $messages);
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $id = isset($_GET['id']) ? $_GET['id'] : '';
        if (empty($id)) {
            echo json_encode(['success' => false, 'error' => 'Missing message ID']);
            break;
        }
        $messages = get_messages($file_path);
        $filtered = array_values(array_filter($messages, function($msg) use ($id) {
            return $msg['id'] !== $id;
        }));
        save_messages($file_path, $filtered);
        echo json_encode(['success' => true]);
        break;

    case 'mark_all_read':
        $messages = get_messages($file_path);
        foreach ($messages as &$msg) { $msg['read'] = true; }
        save_messages($file_path, $messages);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
