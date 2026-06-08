<?php
/**
 * Email & Site Configuration for Dubai Approval Services
 * UPDATE these settings with your actual SMTP credentials
 */

// ========================================
// SITE SETTINGS
// ========================================
define('SITE_NAME', 'Dubai Approval Services');
define('SITE_URL', 'https://www.dubaiapprovalservices.com');
define('ADMIN_EMAIL', 'info@dubaiapprovalservices.com'); // Receives enquiry notifications
define('ADMIN_PHONE', '+971 4 285 1590');

// ========================================
// SMTP EMAIL SETTINGS
// ========================================
// Option 1: Use your hosting SMTP (cPanel email)
// Option 2: Use Gmail SMTP (requires App Password)
// Option 3: Use any SMTP provider (SendGrid, Mailgun, etc.)

define('SMTP_ENABLED', true);           // Set true for SMTP, false for PHP mail()
define('SMTP_HOST', 'mail.dubaiapprovalservices.com');  // Your SMTP server
define('SMTP_PORT', 587);               // 587 for TLS, 465 for SSL, 25 for none
define('SMTP_SECURE', 'tls');           // 'tls', 'ssl', or '' for none
define('SMTP_AUTH', true);              // Usually true
define('SMTP_USERNAME', 'info@dubaiapprovalservices.com');  // Your email
define('SMTP_PASSWORD', 'YOUR_EMAIL_PASSWORD_HERE');        // Your email password or App Password
define('SMTP_FROM_EMAIL', 'info@dubaiapprovalservices.com');
define('SMTP_FROM_NAME', 'Dubai Approval Services');

// ========================================
// ADMIN CREDENTIALS (for forgot password)
// ========================================
define('ADMIN_CREDENTIALS', json_encode([
    ['email' => 'admin@dubaiapprovalservices.com', 'password' => 'Admin@123'],
    ['email' => 'info@dubaiapprovalservices.com', 'password' => 'Admin@123']
]));

// ========================================
// PATHS
// ========================================
define('DATA_DIR', dirname(__DIR__) . '/data');
define('MESSAGES_FILE', DATA_DIR . '/messages.json');
define('OTP_FILE', DATA_DIR . '/.otp_cache');
define('PASSWORDS_FILE', DATA_DIR . '/.admin_passwords');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// ========================================
// EMAIL SENDING FUNCTION
// ========================================
function send_email($to, $subject, $html_body, $reply_to = '') {
    if (SMTP_ENABLED) {
        return send_smtp_email($to, $subject, $html_body, $reply_to);
    } else {
        return send_php_mail($to, $subject, $html_body, $reply_to);
    }
}

function send_smtp_email($to, $subject, $html_body, $reply_to = '') {
    $socket = @fsockopen(
        (SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST,
        SMTP_PORT,
        $errno, $errstr, 10
    );
    if (!$socket) return false;

    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '220') { fclose($socket); return false; }

    // EHLO
    fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
    $response = '';
    while ($line = fgets($socket, 512)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }

    // STARTTLS if needed
    if (SMTP_SECURE === 'tls') {
        fwrite($socket, "STARTTLS\r\n");
        fgets($socket, 512);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
    }

    // AUTH
    if (SMTP_AUTH) {
        fwrite($socket, "AUTH LOGIN\r\n");
        fgets($socket, 512);
        fwrite($socket, base64_encode(SMTP_USERNAME) . "\r\n");
        fgets($socket, 512);
        fwrite($socket, base64_encode(SMTP_PASSWORD) . "\r\n");
        $auth_response = fgets($socket, 512);
        if (substr($auth_response, 0, 3) !== '235') { fclose($socket); return false; }
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM:<" . SMTP_FROM_EMAIL . ">\r\n");
    fgets($socket, 512);

    // RCPT TO
    fwrite($socket, "RCPT TO:<" . $to . ">\r\n");
    fgets($socket, 512);

    // DATA
    fwrite($socket, "DATA\r\n");
    fgets($socket, 512);

    // Build message
    $boundary = md5(time());
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "To: <" . $to . ">\r\n";
    $headers .= "Subject: " . $subject . "\r\n";
    if ($reply_to) $headers .= "Reply-To: <" . $reply_to . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $message = $headers . "\r\n" . $html_body . "\r\n.\r\n";
    fwrite($socket, $message);
    $data_response = fgets($socket, 512);

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return substr($data_response, 0, 3) === '250';
}

function send_php_mail($to, $subject, $html_body, $reply_to = '') {
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    if ($reply_to) $headers .= "Reply-To: " . $reply_to . "\r\n";
    return mail($to, $subject, $html_body, $headers);
}
?>
