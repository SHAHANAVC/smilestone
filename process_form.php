<?php
header('Content-Type: application/json');

// --- Configuration ---
$recipient_email = "smilestonedentalclinic11@gmail.com";
$subject = "New Appointment Request from Landing Page";
$recaptcha_secret_key = "6Lc-dt0sAAAAALXXnspKVZDGPicLxldUYEz7A8k0";

// Handle POST request only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

// 1. Anti-Bot: Honeypot Check
if (!empty($_POST['honeypot'])) {
    // If honeypot is filled, it's a bot. Act like it succeeded to fool the bot.
    echo json_encode(["status" => "success", "message" => "Form submitted successfully."]);
    exit;
}

// 2. Anti-Bot: Time Delay Check
$submission_time = isset($_POST['submission_time']) ? (int)$_POST['submission_time'] : 0;
$current_time = time();
// If submitted in less than 3 seconds
if (($current_time - $submission_time) < 3) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Submission too fast. Please try again."]);
    exit;
}

// 3. Validate Inputs
$name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
$phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
$reason = isset($_POST['reason']) ? trim(strip_tags($_POST['reason'])) : '';

if (empty($name) || empty($phone) || empty($reason)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please fill in all required fields."]);
    exit;
}

if (strlen($name) < 3) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Name must be at least 3 characters long."]);
    exit;
}

// Basic phone number validation (10 digits, optional country code)
if (!preg_match('/^[+]?[0-9]{10,15}$/', $phone)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please enter a valid phone number."]);
    exit;
}

// 4. reCAPTCHA Verification
$recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

if (empty($recaptcha_response)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please complete the reCAPTCHA."]);
    exit;
}

$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
$verify_data = [
    'secret' => $recaptcha_secret_key,
    'response' => $recaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR']
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($verify_data)
    ]
];
$context  = stream_context_create($options);
$verify_result = file_get_contents($verify_url, false, $context);
$recaptcha_data = json_decode($verify_result);

if (!$recaptcha_data->success) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "reCAPTCHA verification failed. Please try again."]);
    exit;
}

// 5. Send Email
$domain = $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : 'smilestonedentalclinic.com';
$from_email = "noreply@" . str_replace('www.', '', $domain);

$email_content = "New Appointment Request Details:\n\n";
$email_content .= "Name: $name\n";
$email_content .= "Phone: $phone\n";
$email_content .= "Reason: $reason\n\n";
$email_content .= "Submitted from IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
$email_content .= "Date: " . date('Y-m-d H:i:s') . "\n";

$headers = "From: Smile Stone Dental <$from_email>\r\n";
$headers .= "Reply-To: $from_email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

if (mail($recipient_email, $subject, $email_content, $headers)) {

    header("Location: https://smilestonedentalclinic.com/contact-success");
    exit();

} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "There was a problem sending your message. Please try calling us instead."
    ]);
}
?>
