<?php
/**
 * Admin Users API - Dubai Approval Services
 * Handles CRUD operations for admin user accounts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once __DIR__ . '/config.php';

$users_file = DATA_DIR . '/admin_users.json';

function get_users($file) {
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    if (!$content) return [];
    $users = json_decode($content, true);
    return is_array($users) ? $users : [];
}

function save_users($file, $users) {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

// Initialize with default admin if no users exist
if (!file_exists($users_file)) {
    $default_users = [
        [
            'id' => 'user_default_1',
            'name' => 'Admin',
            'email' => 'admin@dubaiapprovalservices.com',
            'password' => password_hash('Admin@123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
            'createdAt' => date('c')
        ]
    ];
    save_users($users_file, $default_users);
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'list');

switch ($action) {
    case 'list':
        $users = get_users($users_file);
        // Remove passwords before sending
        $safe_users = array_map(function($u) {
            unset($u['password']);
            return $u;
        }, $users);
        echo json_encode(['success' => true, 'users' => $safe_users, 'total' => count($safe_users)]);
        break;

    case 'add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required']);
            break;
        }

        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : 'editor';

        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Name, email, and password are required.']);
            break;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            break;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            break;
        }

        $users = get_users($users_file);

        // Check for duplicate email
        foreach ($users as $u) {
            if (strtolower($u['email']) === strtolower($email)) {
                echo json_encode(['success' => false, 'message' => 'A user with this email already exists.']);
                break 2;
            }
        }

        $new_user = [
            'id' => 'user_' . time() . '_' . bin2hex(random_bytes(4)),
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => in_array($role, ['admin', 'editor', 'viewer']) ? $role : 'editor',
            'status' => 'active',
            'createdAt' => date('c')
        ];

        $users[] = $new_user;
        save_users($users_file, $users);

        unset($new_user['password']);
        echo json_encode(['success' => true, 'message' => 'User created successfully.', 'user' => $new_user]);
        break;

    case 'edit':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required']);
            break;
        }

        $id = isset($_POST['id']) ? $_POST['id'] : '';
        $name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
        $email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'User ID required.']);
            break;
        }

        $users = get_users($users_file);
        $found = false;

        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                if (!empty($name)) $u['name'] = $name;
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    // Check duplicate email (excluding current user)
                    foreach ($users as $other) {
                        if ($other['id'] !== $id && strtolower($other['email']) === strtolower($email)) {
                            echo json_encode(['success' => false, 'message' => 'Email already taken by another user.']);
                            break 3;
                        }
                    }
                    $u['email'] = $email;
                }
                if (!empty($role) && in_array($role, ['admin', 'editor', 'viewer'])) $u['role'] = $role;
                if (!empty($password) && strlen($password) >= 6) $u['password'] = password_hash($password, PASSWORD_DEFAULT);
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            break;
        }

        save_users($users_file, $users);
        echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
        break;

    case 'delete':
        $id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : '');

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'User ID required.']);
            break;
        }

        $users = get_users($users_file);

        // Don't allow deleting the last admin
        $admin_count = 0;
        $is_admin = false;
        foreach ($users as $u) {
            if ($u['role'] === 'admin' && $u['status'] === 'active') $admin_count++;
            if ($u['id'] === $id && $u['role'] === 'admin') $is_admin = true;
        }

        if ($is_admin && $admin_count <= 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete the last admin user.']);
            break;
        }

        $filtered = array_values(array_filter($users, function($u) use ($id) {
            return $u['id'] !== $id;
        }));

        if (count($filtered) === count($users)) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            break;
        }

        save_users($users_file, $filtered);
        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        break;

    case 'toggle_status':
        $id = isset($_POST['id']) ? $_POST['id'] : '';

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'User ID required.']);
            break;
        }

        $users = get_users($users_file);
        $found = false;

        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u['status'] = ($u['status'] === 'active') ? 'inactive' : 'active';
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            break;
        }

        save_users($users_file, $users);
        echo json_encode(['success' => true, 'message' => 'Status updated.']);
        break;

    case 'login':
        // Used by the login page to authenticate
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required']);
            break;
        }

        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password required.']);
            break;
        }

        $users = get_users($users_file);
        $authenticated = false;
        $user_data = null;

        foreach ($users as $u) {
            if (strtolower($u['email']) === strtolower($email) && $u['status'] === 'active') {
                if (password_verify($password, $u['password'])) {
                    $authenticated = true;
                    $user_data = [
                        'name' => $u['name'],
                        'email' => $u['email'],
                        'role' => $u['role']
                    ];
                    break;
                }
            }
        }

        echo json_encode(['success' => $authenticated, 'user' => $user_data]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>
