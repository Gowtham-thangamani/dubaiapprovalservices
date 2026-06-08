<?php
/**
 * Forgot Password API - OTP via Email
 * Actions: send_otp, verify_otp, reset_password
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =========================================
    // STEP 1: Send OTP to admin email
    // =========================================
    case 'send_otp':
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        // Check if email is a valid admin email
        $admins = json_decode(ADMIN_CREDENTIALS, true);
        $valid_admin = false;
        foreach ($admins as $admin) {
            if (strtolower($admin['email']) === strtolower($email)) {
                $valid_admin = true;
                break;
            }
        }

        // Also check custom passwords file
        if (!$valid_admin && file_exists(PASSWORDS_FILE)) {
            $custom_passwords = json_decode(file_get_contents(PASSWORDS_FILE), true);
            if (is_array($custom_passwords)) {
                foreach ($custom_passwords as $admin) {
                    if (strtolower($admin['email']) === strtolower($email)) {
                        $valid_admin = true;
                        break;
                    }
                }
            }
        }

        if (!$valid_admin) {
            // Don't reveal whether email exists or not
            echo json_encode(['success' => true, 'message' => 'If this email is registered, you will receive an OTP shortly.']);
            exit;
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires = time() + 600; // 10 minutes
        $attempts = 0;

        // Save OTP
        $otp_data = [
            'email' => strtolower($email),
            'otp' => password_hash($otp, PASSWORD_DEFAULT),
            'expires' => $expires,
            'attempts' => $attempts,
            'created' => time()
        ];
        file_put_contents(OTP_FILE, json_encode($otp_data));

        // Send OTP email
        $otp_email_body = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;margin:0">
<div style="max-width:500px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)">
    <div style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;padding:30px;text-align:center">
        <h1 style="margin:0;font-size:22px">Password Reset OTP</h1>
        <p style="margin:8px 0 0;opacity:0.8;font-size:14px">' . SITE_NAME . ' Admin Panel</p>
    </div>
    <div style="padding:30px;text-align:center">
        <p style="color:#555;font-size:15px;line-height:1.6">You requested a password reset for your admin account. Use the OTP below to reset your password:</p>
        <div style="background:linear-gradient(135deg,#fdf8f0,#fff7ed);border:2px dashed #c19a3e;border-radius:12px;padding:25px;margin:25px 0">
            <div style="font-size:36px;font-weight:700;letter-spacing:12px;color:#c19a3e;font-family:monospace">' . $otp . '</div>
        </div>
        <p style="color:#e74c3c;font-size:13px;margin-top:15px"><strong>This OTP expires in 10 minutes.</strong></p>
        <p style="color:#999;font-size:13px;margin-top:20px">If you did not request this password reset, please ignore this email. Your account remains secure.</p>
    </div>
    <div style="background:#f8f8f8;padding:15px;text-align:center;font-size:12px;color:#999">
        ' . SITE_NAME . ' | ' . date('M d, Y h:i A') . '
    </div>
</div>
</body>
</html>';

        $sent = send_email(
            $email,
            'Password Reset OTP - ' . SITE_NAME . ' Admin',
            $otp_email_body
        );

        if ($sent) {
            // Mask email for display
            $parts = explode('@', $email);
            $masked = substr($parts[0], 0, 2) . str_repeat('*', max(strlen($parts[0]) - 2, 2)) . '@' . $parts[1];
            echo json_encode([
                'success' => true,
                'message' => 'OTP sent to ' . $masked . '. Check your inbox.',
                'masked_email' => $masked
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Check SMTP settings in config.php.']);
        }
        break;

    // =========================================
    // STEP 2: Verify OTP
    // =========================================
    case 'verify_otp':
        $email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

        if (empty($email) || empty($otp)) {
            echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
            exit;
        }

        if (!file_exists(OTP_FILE)) {
            echo json_encode(['success' => false, 'message' => 'No OTP request found. Please request a new one.']);
            exit;
        }

        $otp_data = json_decode(file_get_contents(OTP_FILE), true);

        // Check email matches
        if ($otp_data['email'] !== strtolower($email)) {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP request.']);
            exit;
        }

        // Check expiry
        if (time() > $otp_data['expires']) {
            @unlink(OTP_FILE);
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            exit;
        }

        // Check max attempts (5)
        if ($otp_data['attempts'] >= 5) {
            @unlink(OTP_FILE);
            echo json_encode(['success' => false, 'message' => 'Too many attempts. Please request a new OTP.']);
            exit;
        }

        // Verify OTP
        if (!password_verify($otp, $otp_data['otp'])) {
            $otp_data['attempts']++;
            file_put_contents(OTP_FILE, json_encode($otp_data));
            $remaining = 5 - $otp_data['attempts'];
            echo json_encode(['success' => false, 'message' => 'Incorrect OTP. ' . $remaining . ' attempts remaining.']);
            exit;
        }

        // OTP verified - generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $otp_data['verified'] = true;
        $otp_data['reset_token'] = password_hash($reset_token, PASSWORD_DEFAULT);
        $otp_data['reset_expires'] = time() + 300; // 5 min to reset
        file_put_contents(OTP_FILE, json_encode($otp_data));

        echo json_encode([
            'success' => true,
            'message' => 'OTP verified successfully! Set your new password.',
            'reset_token' => $reset_token
        ]);
        break;

    // =========================================
    // STEP 3: Reset Password
    // =========================================
    case 'reset_password':
        $email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
        $token = isset($_POST['reset_token']) ? trim($_POST['reset_token']) : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (empty($email) || empty($token) || empty($new_password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit;
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit;
        }

        if (!file_exists(OTP_FILE)) {
            echo json_encode(['success' => false, 'message' => 'Reset session expired. Please start over.']);
            exit;
        }

        $otp_data = json_decode(file_get_contents(OTP_FILE), true);

        // Validate reset session
        if ($otp_data['email'] !== strtolower($email) ||
            empty($otp_data['verified']) ||
            !password_verify($token, $otp_data['reset_token']) ||
            time() > $otp_data['reset_expires']) {
            @unlink(OTP_FILE);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired reset session. Please start over.']);
            exit;
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in admin_users.json (primary auth)
        $users_file = DATA_DIR . '/admin_users.json';
        if (file_exists($users_file)) {
            $users = json_decode(file_get_contents($users_file), true);
            if (is_array($users)) {
                foreach ($users as &$user) {
                    if (strtolower($user['email']) === strtolower($email)) {
                        $user['password'] = $hashed_password;
                        break;
                    }
                }
                unset($user);
                file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));
            }
        }

        // Also update backup passwords file (hashed)
        $passwords = [];
        if (file_exists(PASSWORDS_FILE)) {
            $passwords = json_decode(file_get_contents(PASSWORDS_FILE), true);
            if (!is_array($passwords)) $passwords = [];
        }

        $found = false;
        foreach ($passwords as &$entry) {
            if (strtolower($entry['email']) === strtolower($email)) {
                $entry['password'] = $hashed_password;
                $entry['updated_at'] = date('c');
                $found = true;
                break;
            }
        }
        if (!$found) {
            $passwords[] = [
                'email' => $email,
                'password' => $hashed_password,
                'updated_at' => date('c')
            ];
        }

        file_put_contents(PASSWORDS_FILE, json_encode($passwords, JSON_PRETTY_PRINT));

        // Clean up OTP file
        @unlink(OTP_FILE);

        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully! Redirecting to login...'
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>
