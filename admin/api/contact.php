<?php
/**
 * Contact Form Handler - Dubai Approval Services
 * 1. Validates form data
 * 2. Saves message to admin/data/messages.json
 * 3. Sends email notification to admin
 * 4. Sends confirmation email to user
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/config.php';

/**
 * Send this enquiry to the DAS central leads inbox (dasandpartnersengineering.com).
 * This is the authoritative destination, not a mirror: the caller MUST surface a
 * failure to the visitor rather than reporting success for a lead that never landed.
 * Returns true only on a 2xx response from /api/contact.
 */
if (!function_exists('das_forward_central')) {
    function das_forward_central(array $fields) {
        $endpoint = 'https://www.dasandpartnersengineering.com/api/contact';
        $payload  = json_encode($fields);
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_POSTREDIR      => 7,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_CONNECTTIMEOUT => 5,
                ]);
                curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return ($code >= 200 && $code < 300);
            }
            $resp = @file_get_contents($endpoint, false, stream_context_create([
                'http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 8, 'ignore_errors' => true],
            ]));
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $c = (int) $m[1];
                return ($c >= 200 && $c < 300);
            }
            return ($resp !== false);
        } catch (Throwable $e) {
            return false;
        }
    }
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$name    = isset($_POST['username']) ? trim(strip_tags($_POST['username'])) : '';
$email   = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
$phone   = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
$service = isset($_POST['service']) ? trim(strip_tags($_POST['service'])) : 'General Enquiry';
$message = isset($_POST['message']) ? trim(strip_tags($_POST['message'])) : '';

// Validate required fields
if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Name, Email, Message).']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Honeypot spam check
if (!empty($_POST['website_url'])) {
    echo json_encode(['success' => true, 'message' => 'Thank you for your message!']);
    exit;
}

// Create message object
$msg_id = 'msg_' . time() . '_' . bin2hex(random_bytes(4));
$new_message = [
    'id'        => $msg_id,
    'name'      => $name,
    'email'     => $email,
    'phone'     => $phone,
    'service'   => $service,
    'message'   => $message,
    'read'      => false,
    'createdAt' => date('c'),
    'ip'        => $_SERVER['REMOTE_ADDR'] ?? ''
];

// Save to messages.json
$messages = [];
if (file_exists(MESSAGES_FILE)) {
    $content = file_get_contents(MESSAGES_FILE);
    $messages = json_decode($content, true);
    if (!is_array($messages)) $messages = [];
}
array_unshift($messages, $new_message); // Add to beginning
file_put_contents(MESSAGES_FILE, json_encode($messages, JSON_PRETTY_PRINT));

// Send to the DAS central leads inbox (authoritative destination)
$central_ok = das_forward_central([
    'source'    => 'dubai-approval',
    'form_type' => 'contact',
    'name'      => $name,
    'email'     => $email,
    'phone'     => $phone,
    'service'   => $service,
    'message'   => $message,
    'page_url'  => $_SERVER['HTTP_REFERER'] ?? '',
]);

// Send email notification to admin
$admin_email_body = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;margin:0">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)">
    <div style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;padding:25px 30px">
        <h1 style="margin:0;font-size:22px;font-weight:600">New Enquiry Received</h1>
        <p style="margin:8px 0 0;opacity:0.8;font-size:14px">' . SITE_NAME . ' - Contact Form</p>
    </div>
    <div style="padding:30px">
        <table style="width:100%;border-collapse:collapse">
            <tr><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#888;width:120px;vertical-align:top"><strong>Name:</strong></td><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#333">' . htmlspecialchars($name) . '</td></tr>
            <tr><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#888;vertical-align:top"><strong>Email:</strong></td><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#333"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#c19a3e">' . htmlspecialchars($email) . '</a></td></tr>
            <tr><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#888;vertical-align:top"><strong>Phone:</strong></td><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#333">' . ($phone ? '<a href="tel:' . htmlspecialchars($phone) . '" style="color:#c19a3e">' . htmlspecialchars($phone) . '</a>' : 'Not provided') . '</td></tr>
            <tr><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#888;vertical-align:top"><strong>Service:</strong></td><td style="padding:12px 0;border-bottom:1px solid #f0f0f0;color:#333"><span style="background:#fdf8f0;color:#c19a3e;padding:4px 12px;border-radius:6px;font-size:13px">' . htmlspecialchars($service) . '</span></td></tr>
            <tr><td style="padding:12px 0;color:#888;vertical-align:top"><strong>Message:</strong></td><td style="padding:12px 0;color:#333;line-height:1.6">' . nl2br(htmlspecialchars($message)) . '</td></tr>
        </table>
        <div style="margin-top:25px;padding-top:20px;border-top:1px solid #f0f0f0;text-align:center">
            <a href="' . SITE_URL . '/admin/dashboard.html" style="display:inline-block;background:linear-gradient(135deg,#c19a3e,#d4af37);color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px">View in Admin Panel</a>
        </div>
    </div>
    <div style="background:#f8f8f8;padding:15px 30px;text-align:center;font-size:12px;color:#999">
        Received on ' . date('M d, Y \a\t h:i A') . ' | ' . SITE_URL . '
    </div>
</div>
</body>
</html>';

$email_sent = send_email(
    ADMIN_EMAIL,
    'New Enquiry: ' . $service . ' - ' . $name,
    $admin_email_body,
    $email
);

// Send confirmation email to user
$user_email_body = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;margin:0">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1)">
    <div style="background:linear-gradient(135deg,#c19a3e,#d4af37);color:#fff;padding:30px;text-align:center">
        <h1 style="margin:0;font-size:24px;font-weight:600">Thank You, ' . htmlspecialchars($name) . '!</h1>
        <p style="margin:10px 0 0;opacity:0.9;font-size:15px">We have received your enquiry</p>
    </div>
    <div style="padding:30px">
        <p style="color:#333;line-height:1.8;font-size:15px">Thank you for reaching out to <strong>' . SITE_NAME . '</strong>. We have received your enquiry regarding <strong>' . htmlspecialchars($service) . '</strong> and our team will get back to you within <strong>24 hours</strong>.</p>
        <div style="background:#fdf8f0;border-left:4px solid #c19a3e;padding:20px;border-radius:0 8px 8px 0;margin:25px 0">
            <p style="margin:0 0 8px;color:#888;font-size:13px"><strong>Your Message:</strong></p>
            <p style="margin:0;color:#333;line-height:1.6">' . nl2br(htmlspecialchars($message)) . '</p>
        </div>
        <p style="color:#555;line-height:1.8;font-size:14px">If you need urgent assistance, feel free to contact us directly:</p>
        <div style="text-align:center;margin:20px 0">
            <a href="tel:' . ADMIN_PHONE . '" style="display:inline-block;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;margin:5px">Call Us</a>
            <a href="https://wa.me/97142851590" style="display:inline-block;background:linear-gradient(135deg,#25d366,#128c7e);color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;margin:5px">WhatsApp</a>
        </div>
    </div>
    <div style="background:#f8f8f8;padding:20px 30px;text-align:center;font-size:12px;color:#999">
        <p style="margin:0">' . SITE_NAME . ' | Office 3409, 34th Floor, The Citadel Tower, Business Bay, Dubai</p>
        <p style="margin:5px 0 0"><a href="' . SITE_URL . '" style="color:#c19a3e">' . SITE_URL . '</a></p>
    </div>
</div>
</body>
</html>';

send_email(
    $email,
    'Thank you for your enquiry - ' . SITE_NAME,
    $user_email_body
);

// Success is reported only once the enquiry actually landed in the DAS central admin
// panel — a lead that never arrived must not be confirmed to the visitor as submitted.
if (!$central_ok) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'We could not submit your enquiry right now. Please try again in a moment.'
    ]);
    exit;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Thank you ' . $name . '! Your enquiry has been submitted successfully. We will contact you within 24 hours.'
]);
?>
