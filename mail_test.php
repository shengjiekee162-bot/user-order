<?php
// mail_test.php
// Simple test script to send an email using mail_config.php and PHPMailer if available.
// Usage: php mail_test.php

// Load composer autoload if present
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// load mail config if exists
$mailCfg = [];
if (file_exists(__DIR__ . '/mail_config.php')) {
    $mailCfg = include __DIR__ . '/mail_config.php';
}

// recipient to test - EDIT THIS to your target email
$testRecipient = $mailCfg['test_recipient'] ?? 'your_test_recipient@example.com';
$testRecipientName = $mailCfg['test_recipient_name'] ?? 'Test Recipient';

// Prepare message
$subject = 'Test Email from Shopee Clone';
$bodyHtml = '<h1>This is a test email sent using PHPMailer library in PHP.</h1>';
$bodyText = 'This is the plain text version of the email content.';

// If PHPMailer is available and smtp config provided, use it
$mail_sent = false;
if (!empty($mailCfg['smtp']) && class_exists('PHPMailer\\PHPMailer\\PHPMailer') || !empty($mailCfg['smtp']) && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $smtp = $mailCfg['smtp'];
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        $mail->SMTPSecure = $smtp['secure'] ?? 'tls';
        $mail->Port = $smtp['port'] ?? 587;

        $fromEmail = $mailCfg['from_email'] ?? 'noreply@localhost';
        $fromName  = $mailCfg['from_name'] ?? 'Shopee Clone';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($testRecipient, $testRecipientName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = $bodyText;

        $mail->send();
        echo "Message has been sent successfully via PHPMailer SMTP\n";
        $mail_sent = true;
    } catch (Exception $e) {
        echo "PHPMailer exception: " . $e->getMessage() . "\n";
        $mail_sent = false;
    }
} else {
    // Fallback to PHP mail()
    $fromEmail = $mailCfg['from_email'] ?? ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName  = $mailCfg['from_name'] ?? 'Shopee Clone';
    $headers = 'From: ' . $fromName . ' <' . $fromEmail . '>' . "\r\n" . 'Content-Type: text/html; charset=utf-8';
    try {
        $mail_sent = mail($testRecipient, $subject, $bodyHtml, $headers);
        if ($mail_sent) echo "Message has been sent successfully via mail()\n";
        else echo "mail() returned false (sending failed)\n";
    } catch (Exception $e) {
        echo "mail() exception: " . $e->getMessage() . "\n";
    }
}

if (!$mail_sent) {
    echo "Mail not sent. Check mail_config.php, composer/PHPMailer installation, and php_errors.log for details.\n";
}

?>