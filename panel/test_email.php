<?php
require_once __DIR__ . '/modules/_common.php';

use ProConsultancy\Core\Mailer;

// Change to your email
$testEmail = 'your-email@example.com';

$mailer = Mailer::getInstance();
$result = $mailer->sendTest($testEmail);

if ($result) {
    echo "✅ Test email sent successfully to {$testEmail}!\n";
    echo "Check your inbox.\n";
} else {
    echo "❌ Failed to send test email.\n";
    echo "Check error logs.\n";
}