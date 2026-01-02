<?php
namespace ProConsultancy\Core;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Email Mailer Class
 * Handles sending emails via SMTP with template support
 * 
 * Features:
 * - PHPMailer integration
 * - Template-based emails
 * - Email queueing
 * - SMTP configuration
 * - HTML email support
 * - Email logging
 * 
 * @version 2.0
 */
class Mailer {
    
    private array $config;
    private bool $useQueue = false;
    private static ?Mailer $instance = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $appConfig = require __DIR__ . '/../../config/app.php';
        
        $this->config = [
            'from_email' => $appConfig['from_email'] ?? 'noreply@proconsultancy.be',
            'from_name' => $appConfig['from_name'] ?? 'Pro Consultancy',
            'smtp_host' => $appConfig['smtp_host'] ?? 'smtp.gmail.com',
            'smtp_port' => $appConfig['smtp_port'] ?? 587,
            'smtp_username' => $appConfig['smtp_username'] ?? '',
            'smtp_password' => $appConfig['smtp_password'] ?? '',
            'smtp_encryption' => $appConfig['smtp_encryption'] ?? 'tls',
            'use_queue' => $appConfig['mail_use_queue'] ?? false,
        ];
        
        $this->useQueue = $this->config['use_queue'];
    }
    
    /**
     * Get singleton instance
     * 
     * @return Mailer
     */
    public static function getInstance(): Mailer {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * STATIC METHOD: Send email (for backward compatibility)
     * 
     * Usage: Mailer::send($to, $subject, $templateOrBody, $variables)
     * 
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $templateOrBody Template name or HTML body
     * @param array $variables Template variables or email options
     * @return bool Success status
     */
    public static function send($to, string $subject, string $templateOrBody, array $variables = []): bool {
        $instance = self::getInstance();
        
        // Check if this is a template name or direct HTML
        if (strpos($templateOrBody, '<') === false && strpos($templateOrBody, '>') === false) {
            // Looks like a template name
            return $instance->sendFromTemplate($to, $subject, $templateOrBody, $variables);
        } else {
            // Direct HTML body
            return $instance->sendDirect($to, $subject, $templateOrBody, $variables);
        }
    }
    
    /**
     * Send email directly with HTML body
     * 
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $options Additional options (cc, bcc, attachments, reply_to)
     * @return bool Success status
     */
    public function sendDirect($to, string $subject, string $body, array $options = []): bool {
        try {
            // Validate recipient
            $recipients = is_array($to) ? $to : [$to];
            foreach ($recipients as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email address: {$email}");
                }
            }
            
            // If using queue, add to queue instead of sending immediately
            if ($this->useQueue) {
                return $this->queue($to, $subject, $body, null, $options);
            }
            
            // Build complete HTML email
            $fullBody = $this->buildEmailWrapper($body);
            
            // Create PHPMailer instance
            $mail = $this->createPHPMailerInstance();
            
            // Set recipients
            foreach ($recipients as $email) {
                $mail->addAddress($email);
            }
            
            // Set CC
            if (isset($options['cc'])) {
                $ccList = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
                foreach ($ccList as $cc) {
                    $mail->addCC($cc);
                }
            }
            
            // Set BCC
            if (isset($options['bcc'])) {
                $bccList = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
                foreach ($bccList as $bcc) {
                    $mail->addBCC($bcc);
                }
            }
            
            // Set Reply-To
            if (isset($options['reply_to'])) {
                $mail->addReplyTo($options['reply_to']);
            }
            
            // Add attachments
            if (isset($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    }
                }
            }
            
            // Set subject and body
            $mail->Subject = $subject;
            $mail->Body = $fullBody;
            $mail->AltBody = strip_tags($body);
            
            // Send email
            $result = $mail->send();
            
            // Log email
            if ($result) {
                Logger::getInstance()->info('Email sent successfully', [
                    'to' => $recipients,
                    'subject' => $subject,
                    'method' => 'phpmailer'
                ]);
            } else {
                Logger::getInstance()->error('Email failed to send', [
                    'to' => $recipients,
                    'subject' => $subject,
                    'error' => $mail->ErrorInfo
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Email exception', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Send email from template file
     * 
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject (can contain {{variables}})
     * @param string $templateName Template filename (without .php)
     * @param array $variables Template variables
     * @return bool Success status
     */
    public function sendFromTemplate($to, string $subject, string $templateName, array $variables = []): bool {
        try {
            // Load template content
            $body = $this->loadTemplate($templateName, $variables);
            
            if (!$body) {
                throw new Exception("Email template not found: {$templateName}");
            }
            
            // Replace variables in subject
            $subject = $this->replacePlaceholders($subject, $variables);
            
            // Send email
            return $this->sendDirect($to, $subject, $body);
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Template email failed', [
                'template' => $templateName,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Load email template from file
     * 
     * @param string $templateName Template filename
     * @param array $variables Variables to pass to template
     * @return string|false Template content
     */
    private function loadTemplate(string $templateName, array $variables = []) {
        // Look for template in multiple locations
        $possiblePaths = [
            __DIR__ . '/../../modules/reports/templates/' . $templateName . '.php',
            __DIR__ . '/../../templates/emails/' . $templateName . '.php',
            __DIR__ . '/../../../templates/emails/' . $templateName . '.php',
        ];
        
        foreach ($possiblePaths as $templatePath) {
            if (file_exists($templatePath)) {
                // Extract variables for template
                extract($variables);
                
                // Start output buffering
                ob_start();
                
                // Include template
                include $templatePath;
                
                // Get buffered content
                $content = ob_get_clean();
                
                return $content;
            }
        }
        
        Logger::getInstance()->error('Template file not found', [
            'template' => $templateName,
            'searched_paths' => $possiblePaths
        ]);
        
        return false;
    }
    
    /**
     * Send email from database template
     * 
     * @param string|array $to Recipient email(s)
     * @param string $templateCode Template code (e.g., 'cv_direct', 'cv_job_application')
     * @param array $variables Template variables
     * @param array $options Additional options
     * @return bool Success status
     */
    public function sendFromDatabaseTemplate($to, string $templateCode, array $variables = [], array $options = []): bool {
        try {
            // Get template from database
            $template = $this->getTemplate($templateCode);
            
            if (!$template) {
                throw new Exception("Email template not found: {$templateCode}");
            }
            
            // Replace variables in subject and body
            $subject = $this->replacePlaceholders($template['subject'], $variables);
            $body = $this->replacePlaceholders($template['body_html'], $variables);
            
            // Send email
            return $this->sendDirect($to, $subject, $body, $options);
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Database template email failed', [
                'template' => $templateCode,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Queue email for later sending
     * 
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $body Email body
     * @param string|null $templateCode Template code (if template-based)
     * @param array $options Additional options
     * @return bool Success status
     */
    public function queue($to, string $subject, string $body, ?string $templateCode = null, array $options = []): bool {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $toEmail = is_array($to) ? implode(',', $to) : $to;
            $cc = isset($options['cc']) ? (is_array($options['cc']) ? implode(',', $options['cc']) : $options['cc']) : null;
            $bcc = isset($options['bcc']) ? (is_array($options['bcc']) ? implode(',', $options['bcc']) : $options['bcc']) : null;
            $optionsJson = json_encode($options);
            
            $stmt = $conn->prepare("
                INSERT INTO email_queue (
                    to_email, 
                    cc_email, 
                    bcc_email, 
                    subject, 
                    body, 
                    template_code,
                    options,
                    status, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->bind_param(
                "sssssss",
                $toEmail,
                $cc,
                $bcc,
                $subject,
                $body,
                $templateCode,
                $optionsJson
            );
            
            $stmt->execute();
            
            Logger::getInstance()->info('Email queued', [
                'to' => $toEmail,
                'subject' => $subject,
                'template' => $templateCode
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to queue email', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Process email queue (called by cron job)
     * 
     * @param int $limit Number of emails to process
     * @return int Number of emails sent
     */
    public static function processQueue(int $limit = 10): int {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Get pending emails
            $stmt = $conn->prepare("
                SELECT * FROM email_queue 
                WHERE status = 'pending' 
                AND attempts < 3
                ORDER BY created_at ASC
                LIMIT ?
            ");
            
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sent = 0;
            $mailer = self::getInstance();
            
            while ($email = $result->fetch_assoc()) {
                $options = json_decode($email['options'], true) ?? [];
                
                if (!empty($email['cc_email'])) {
                    $options['cc'] = explode(',', $email['cc_email']);
                }
                
                if (!empty($email['bcc_email'])) {
                    $options['bcc'] = explode(',', $email['bcc_email']);
                }
                
                // Temporarily disable queueing to prevent infinite loop
                $mailer->useQueue = false;
                
                $success = $mailer->sendDirect(
                    explode(',', $email['to_email']),
                    $email['subject'],
                    $email['body'],
                    $options
                );
                
                if ($success) {
                    // Mark as sent
                    $updateStmt = $conn->prepare("
                        UPDATE email_queue 
                        SET status = 'sent', 
                            sent_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("i", $email['id']);
                    $updateStmt->execute();
                    $sent++;
                } else {
                    // Increment attempts
                    $updateStmt = $conn->prepare("
                        UPDATE email_queue 
                        SET attempts = attempts + 1,
                            status = CASE WHEN attempts >= 2 THEN 'failed' ELSE 'pending' END,
                            error_message = 'Failed to send',
                            last_attempt_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->bind_param("i", $email['id']);
                    $updateStmt->execute();
                }
            }
            
            Logger::getInstance()->info('Email queue processed', [
                'sent' => $sent,
                'processed' => $result->num_rows
            ]);
            
            return $sent;
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Queue processing failed', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Create configured PHPMailer instance
     * 
     * @return PHPMailer
     */
    private function createPHPMailerInstance(): PHPMailer {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = $this->config['smtp_encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp_port'];
            
            // Sender
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            
            return $mail;
            
        } catch (PHPMailerException $e) {
            Logger::getInstance()->error('PHPMailer configuration failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get email template from database
     * 
     * @param string $templateCode Template code
     * @return array|null Template data
     */
    private function getTemplate(string $templateCode): ?array {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("
                SELECT template_code, template_name, subject, body_html, variables
                FROM email_templates 
                WHERE template_code = ? 
                AND is_active = 1
            ");
            
            $stmt->bind_param("s", $templateCode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_assoc();
            
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to get email template', [
                'template_code' => $templateCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Replace placeholders in template
     * 
     * @param string $text Template text with {{placeholders}}
     * @param array $variables Variables to replace
     * @return string Processed text
     */
    private function replacePlaceholders(string $text, array $variables): string {
        foreach ($variables as $key => $value) {
            // Convert arrays/objects to strings
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            
            // Replace both {{key}} and {key} formats
            $text = str_replace(["{{{$key}}}", "{{" . $key . "}}"], $value, $text);
        }
        return $text;
    }
    
    /**
     * Build complete email HTML with wrapper
     * 
     * @param string $content Email content
     * @return string Complete HTML
     */
    private function buildEmailWrapper(string $content): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .email-body {
            padding: 40px 30px;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            border-top: 1px solid #e0e0e0;
        }
        .email-footer p {
            margin: 5px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #667eea;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            border: 1px solid #e0e0e0;
        }
        table td {
            padding: 10px;
            border: 1px solid #e0e0e0;
        }
        .kpi-card {
            background: #e9f7ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .kpi-card strong {
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Pro Consultancy</h1>
        </div>
        <div class="email-body">
            {$content}
        </div>
        <div class="email-footer">
            <p><strong>Pro Consultancy SRL</strong></p>
            <p>Leuvensesteenweg 122, Zaventem 1932, Belgium</p>
            <p>Email: admin@proconsultancy.be | Phone: +32 (0)472 849033</p>
            <p style="margin-top: 15px;">
                &copy; 2025 Pro Consultancy. All rights reserved.<br>
                This is an automated email. Please do not reply directly to this message.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Send test email
     * 
     * @param string $to Test recipient
     * @return bool Success status
     */
    public function sendTest(string $to): bool {
        $subject = "Test Email from Pro Consultancy ATS";
        $body = "
            <h2>Email System Test</h2>
            <p>This is a test email to verify that the email system is working correctly.</p>
            <p><strong>System Information:</strong></p>
            <ul>
                <li>Sent at: " . date('Y-m-d H:i:s') . "</li>
                <li>From: {$this->config['from_email']}</li>
                <li>SMTP Host: {$this->config['smtp_host']}</li>
                <li>SMTP Port: {$this->config['smtp_port']}</li>
            </ul>
            <p>If you received this email, your email configuration is working correctly!</p>
        ";
        
        return $this->sendDirect($to, $subject, $body);
    }
}