<?php
// contact-submit.php - minimal server-side handler
$TO_EMAIL = 'teams@happybiz.in';
$RATE_DIR = __DIR__ . '/tmp_rate';
$LOG_FILE = __DIR__ . '/logs/contact_log.txt';
$RATE_LIMIT_SECONDS = 30;
$MIN_FORM_SECONDS = 3;
$MAX_BODY_LENGTH = 5000;

if (!is_dir($RATE_DIR)) mkdir($RATE_DIR, 0700, true);
if (!is_dir(dirname($LOG_FILE))) mkdir(dirname($LOG_FILE), 0700, true);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

function append_log($line) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($LOG_FILE, "[$ts] $line\n", FILE_APPEND | LOCK_EX);
}

// Honeypot
if (!empty($_POST['company'])) {
    append_log("HONEYPOT - ip:$ip ua:$ua");
    http_response_code(400);
    exit("Bad request");
}

// Timestamp check
$start_ms = isset($_POST['form_start']) ? intval($_POST['form_start']) : 0;
if ($start_ms && ((microtime(true)*1000) - $start_ms) < ($MIN_FORM_SECONDS*1000)) {
    append_log("TOO_FAST - ip:$ip ua:$ua");
    http_response_code(400);
    exit("Too fast");
}

// Rate limit
$rate_file = $RATE_DIR . '/' . preg_replace('/[^a-z0-9\._-]/i','_', $ip) . '.txt';
$now = time();
$last = file_exists($rate_file) ? intval(file_get_contents($rate_file)) : 0;
if ($now - $last < $RATE_LIMIT_SECONDS) {
    append_log("RATE_LIMIT - ip:$ip ua:$ua");
    http_response_code(429);
    exit("Too many requests");
}
file_put_contents($rate_file, $now);

// Validate
$name = trim(substr($_POST['name'] ?? '', 0, 200));
$email = trim(substr($_POST['email'] ?? '', 0, 200));
$message = trim(substr($_POST['message'] ?? '', 0, $MAX_BODY_LENGTH));

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$message) {
    append_log("INVALID_INPUT - ip:$ip ua:$ua");
    http_response_code(400);
    exit("Invalid input");
}

// Send mail (if mail() disabled on your host, use PHPMailer)
$subject = "Contact form — $name";
$body = "Name: $name\nEmail: $email\nMessage:\n$message\n\nIP: $ip\nUA: $ua\n";
$headers = "From: no-reply@happybiz.in\r\nReply-To: $email\r\n";
$mail_ok = mail($TO_EMAIL, $subject, $body, $headers);

append_log(($mail_ok ? "SENT" : "MAIL_FAIL") . " - ip:$ip name:$name email:$email");

if ($mail_ok) {
    header("Location: thankyou.html");
    exit;
} else {
    http_response_code(500);
    echo "Mail failed";
}
?>
