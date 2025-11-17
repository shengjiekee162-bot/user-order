<?php
// test_send_code.php - send a 6-digit code using mail_config.php SMTP settings
$cfg = [];
if (file_exists(__DIR__ . '/mail_config.php')) {
    $cfg = include __DIR__ . '/mail_config.php';
}
$to = $cfg['test_recipient'] ?? ($cfg['smtp']['username'] ?? '');
if (!$to) {
    echo "No recipient found in mail_config.php (test_recipient or smtp.username).\n";
    exit(1);
}
$code = random_int(0, 999999);
$code = str_pad((string)$code, 6, '0', STR_PAD_LEFT);
$subject = "【测试】密码重置验证码";
$body = "您好，\n\n本次测试验证码为： $code \n\n如果不是您本人操作，请忽略。";

// Try PHPMailer first if available
$sent = false;
if (!empty($cfg['smtp']) && file_exists(__DIR__ . '/vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $smtp = $cfg['smtp'];
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'] ?? 587;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        if (!empty($smtp['secure'])) $mail->SMTPSecure = $smtp['secure'];
        $from = $cfg['from_email'] ?? $mail->Username;
        $fromName = $cfg['from_name'] ?? 'App';
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        echo "PHPMailer: sent OK to $to\n";
        $sent = true;
    } catch (Exception $e) {
        echo "PHPMailer failed: " . $e->getMessage() . "\n";
        error_log('test_send_code PHPMailer failed: ' . $e->getMessage());
    }
}

if (!$sent) {
    // fallback to mail()
    $from = $cfg['from_email'] ?? ('noreply@' . preg_replace('/:\d+$/', '', ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $headers = 'From: Test <' . $from . '>' . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
    $res = mail($to, $subject, $body, $headers);
    echo "mail() returned: " . ($res ? 'true' : 'false') . "\n";
    if (!$res) error_log('test_send_code mail() returned false');
}

// print recent php_errors.log tail
$log = __DIR__ . '/php_errors.log';
if (file_exists($log)) {
    echo "\n--- php_errors.log (tail 50) ---\n";
    $lines = array_slice(file($log), -50);
    foreach ($lines as $l) echo $l;
}

echo "\nDone. Code sent (or attempted): $code\n";
