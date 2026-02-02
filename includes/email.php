<?php
/**
 * Email Service
 * Handles SMTP email sending and queue management
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system-config.php';

/**
 * Email configuration from settings
 */
function getEmailConfig() {
    global $pdo;

    $defaults = getEmailDefaults();

    $config = [
        'host' => $defaults['host'],
        'port' => $defaults['port'],
        'secure' => $defaults['secure'],
        'username' => '',
        'password' => '',
        'from_email' => $defaults['from_email'],
        'from_name' => $defaults['from_name']
    ];
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'email'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($settings['smtp_host'])) $config['host'] = $settings['smtp_host'];
        if (!empty($settings['smtp_port'])) $config['port'] = (int)$settings['smtp_port'];
        if (!empty($settings['smtp_secure'])) $config['secure'] = $settings['smtp_secure'];
        if (!empty($settings['smtp_username'])) $config['username'] = $settings['smtp_username'];
        if (!empty($settings['smtp_password'])) $config['password'] = $settings['smtp_password'];
        if (!empty($settings['smtp_from_email'])) $config['from_email'] = $settings['smtp_from_email'];
        if (!empty($settings['smtp_from_name'])) $config['from_name'] = $settings['smtp_from_name'];
    } catch (PDOException $e) {
        error_log("Failed to load email config: " . $e->getMessage());
    }
    
    return $config;
}

/**
 * Queue an email for sending
 */
function queueEmail($recipientEmail, $recipientName, $subject, $bodyHtml, $bodyText = null, $template = null, $templateData = null, $scheduledAt = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_queue (id, recipient_email, recipient_name, subject, body_html, body_text, template, template_data, scheduled_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $id = generateULID();
        $stmt->execute([
            $id,
            $recipientEmail,
            $recipientName,
            $subject,
            $bodyHtml,
            $bodyText ?? strip_tags($bodyHtml),
            $template,
            $templateData ? json_encode($templateData) : null,
            $scheduledAt ?? date('Y-m-d H:i:s')
        ]);
        
        return $id;
    } catch (PDOException $e) {
        error_log("Failed to queue email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using SMTP
 */
function sendEmail($to, $toName, $subject, $bodyHtml, $bodyText = null) {
    $config = getEmailConfig();
    
    // Check if SMTP is configured
    if (empty($config['username']) || empty($config['password'])) {
        error_log("SMTP not configured - email not sent to: $to");
        return false;
    }
    
    // Build email headers
    $boundary = md5(time());
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'Reply-To: ' . $config['from_email'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Build message body
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= ($bodyText ?? strip_tags($bodyHtml)) . "\r\n\r\n";
    
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $bodyHtml . "\r\n\r\n";
    
    $message .= "--$boundary--";
    
    // Use SMTP if available, otherwise fall back to mail()
    if (function_exists('stream_socket_client')) {
        return sendViaSMTP($config, $to, $toName, $subject, $message, $headers);
    } else {
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
}

/**
 * Send email via SMTP socket
 */
function sendViaSMTP($config, $to, $toName, $subject, $message, $headers) {
    $host = $config['secure'] === 'ssl' ? 'ssl://' . $config['host'] : $config['host'];
    $port = $config['port'];
    
    $socket = @stream_socket_client(
        "$host:$port",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT
    );
    
    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }
    
    // Set timeout
    stream_set_timeout($socket, 30);
    
    try {
        // Read greeting
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            throw new Exception("Invalid greeting: $response");
        }
        
        // EHLO
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        
        // STARTTLS if needed
        if ($config['secure'] === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '220') {
                throw new Exception("STARTTLS failed: $response");
            }
            
            // Enable TLS
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // EHLO again after TLS
            fwrite($socket, "EHLO " . gethostname() . "\r\n");
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
        }
        
        // AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("AUTH LOGIN failed: $response");
        }
        
        // Username
        fwrite($socket, base64_encode($config['username']) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("Username rejected: $response");
        }
        
        // Password
        fwrite($socket, base64_encode($config['password']) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '235') {
            throw new Exception("Authentication failed: $response");
        }
        
        // MAIL FROM
        fwrite($socket, "MAIL FROM:<{$config['from_email']}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("MAIL FROM failed: $response");
        }
        
        // RCPT TO
        fwrite($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("RCPT TO failed: $response");
        }
        
        // DATA
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '354') {
            throw new Exception("DATA failed: $response");
        }
        
        // Send headers and message
        $fullMessage = "To: $toName <$to>\r\n";
        $fullMessage .= "Subject: $subject\r\n";
        $fullMessage .= implode("\r\n", $headers) . "\r\n\r\n";
        $fullMessage .= $message . "\r\n.\r\n";
        
        fwrite($socket, $fullMessage);
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("Message send failed: $response");
        }
        
        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP error: " . $e->getMessage());
        fclose($socket);
        return false;
    }
}

/**
 * Process email queue (call from cron or after request)
 */
function processEmailQueue($limit = 10) {
    global $pdo;
    
    try {
        // Get pending emails
        $stmt = $pdo->prepare("
            SELECT * FROM email_queue 
            WHERE status = 'pending' 
            AND scheduled_at <= NOW()
            AND attempts < max_attempts
            ORDER BY scheduled_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll();
        
        $processed = 0;
        
        foreach ($emails as $email) {
            // Mark as processing
            $pdo->prepare("UPDATE email_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?")
                ->execute([$email['id']]);
            
            // Send email
            $success = sendEmail(
                $email['recipient_email'],
                $email['recipient_name'],
                $email['subject'],
                $email['body_html'],
                $email['body_text']
            );
            
            if ($success) {
                $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")
                    ->execute([$email['id']]);
                $processed++;
            } else {
                $status = $email['attempts'] + 1 >= $email['max_attempts'] ? 'failed' : 'pending';
                $pdo->prepare("UPDATE email_queue SET status = ?, error_message = 'Send failed' WHERE id = ?")
                    ->execute([$status, $email['id']]);
            }
        }
        
        return $processed;
        
    } catch (PDOException $e) {
        error_log("Failed to process email queue: " . $e->getMessage());
        return 0;
    }
}

/**
 * Email templates
 */
function getEmailTemplate($template, $data) {
    // Get system configuration for dynamic branding
    $systemConfig = getSystemConfig();
    $companyName = $systemConfig['company_name'] ?? 'File Tracker';
    $primaryColor = $systemConfig['primary_color'] ?? '#667eea';
    $secondaryColor = $systemConfig['secondary_color'] ?? '#764ba2';
    
    $templates = [
        'task_assigned' => [
            'subject' => 'New Task Assigned: {task_title}',
            'body' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, ' . $primaryColor . ' 0%, ' . $secondaryColor . ' 100%); padding: 20px; text-align: center;">
                        <h1 style="color: white; margin: 0;">New Task Assigned</h1>
                    </div>
                    <div style="padding: 20px; background: #f9fafb;">
                        <p>Hello <strong>{recipient_name}</strong>,</p>
                        <p>A new task has been assigned to you:</p>
                        <div style="background: white; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <h3 style="margin: 0 0 10px 0; color: #374151;">{task_title}</h3>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Letter:</strong> {letter_reference}</p>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Priority:</strong> {priority}</p>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Due Date:</strong> {due_date}</p>
                        </div>
                        <a href="{action_url}" style="display: inline-block; background: ' . $primaryColor . '; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">View Task</a>
                    </div>
                    <div style="padding: 15px; text-align: center; color: #9ca3af; font-size: 12px;">
                        <p>' . $companyName . '</p>
                    </div>
                </div>
            '
        ],
        'task_updated' => [
            'subject' => 'Task Updated: {task_title}',
            'body' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, ' . $primaryColor . ' 0%, ' . $secondaryColor . ' 100%); padding: 20px; text-align: center;">
                        <h1 style="color: white; margin: 0;">Task Updated</h1>
                    </div>
                    <div style="padding: 20px; background: #f9fafb;">
                        <p>Hello <strong>{recipient_name}</strong>,</p>
                        <p>A task you are involved with has been updated:</p>
                        <div style="background: white; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <h3 style="margin: 0 0 10px 0; color: #374151;">{task_title}</h3>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Status:</strong> {old_status} → {new_status}</p>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Updated by:</strong> {updated_by}</p>
                            {comment_section}
                        </div>
                        <a href="{action_url}" style="display: inline-block; background: ' . $primaryColor . '; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">View Task</a>
                    </div>
                    <div style="padding: 15px; text-align: center; color: #9ca3af; font-size: 12px;">
                        <p>' . $companyName . '</p>
                    </div>
                </div>
            '
        ],
        'deadline_reminder' => [
            'subject' => '⚠️ Task Deadline Approaching: {task_title}',
            'body' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #F59E0B 0%, #EF4444 100%); padding: 20px; text-align: center;">
                        <h1 style="color: white; margin: 0;">⚠️ Deadline Reminder</h1>
                    </div>
                    <div style="padding: 20px; background: #f9fafb;">
                        <p>Hello <strong>{recipient_name}</strong>,</p>
                        <p>The following task is due soon:</p>
                        <div style="background: white; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #F59E0B;">
                            <h3 style="margin: 0 0 10px 0; color: #374151;">{task_title}</h3>
                            <p style="margin: 5px 0; color: #EF4444;"><strong>Due Date:</strong> {due_date}</p>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Status:</strong> {status}</p>
                        </div>
                        <a href="{action_url}" style="display: inline-block; background: #F59E0B; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">View Task</a>
                    </div>
                    <div style="padding: 15px; text-align: center; color: #9ca3af; font-size: 12px;">
                        <p>' . $companyName . '</p>
                    </div>
                </div>
            '
        ],
        'letter_created' => [
            'subject' => 'New Letter Added: {reference_no}',
            'body' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, ' . $primaryColor . ' 0%, ' . $secondaryColor . ' 100%); padding: 20px; text-align: center;">
                        <h1 style="color: white; margin: 0;">New Letter Added</h1>
                    </div>
                    <div style="padding: 20px; background: #f9fafb;">
                        <p>Hello <strong>{recipient_name}</strong>,</p>
                        <p>A new letter has been added to the system:</p>
                        <div style="background: white; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <h3 style="margin: 0 0 10px 0; color: #374151;">{reference_no}</h3>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Subject:</strong> {subject}</p>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Stakeholder:</strong> {stakeholder}</p>
                            <p style="margin: 5px 0; color: #6b7280;"><strong>Priority:</strong> {priority}</p>
                        </div>
                        <a href="{action_url}" style="display: inline-block; background: ' . $primaryColor . '; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;">View Letter</a>
                    </div>
                    <div style="padding: 15px; text-align: center; color: #9ca3af; font-size: 12px;">
                        <p>' . $companyName . '</p>
                    </div>
                </div>
            '
        ]
    ];
    
    if (!isset($templates[$template])) {
        return null;
    }
    
    $tpl = $templates[$template];
    $subject = $tpl['subject'];
    $body = $tpl['body'];
    
    // Replace placeholders
    foreach ($data as $key => $value) {
        $subject = str_replace('{' . $key . '}', $value, $subject);
        $body = str_replace('{' . $key . '}', $value, $body);
    }
    
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Send notification email using template
 */
function sendNotificationEmail($recipientEmail, $recipientName, $template, $data) {
    $emailContent = getEmailTemplate($template, array_merge($data, ['recipient_name' => $recipientName]));
    
    if (!$emailContent) {
        error_log("Unknown email template: $template");
        return false;
    }
    
    return queueEmail(
        $recipientEmail,
        $recipientName,
        $emailContent['subject'],
        $emailContent['body'],
        null,
        $template,
        $data
    );
}

/**
 * Test SMTP connection
 */
function testSMTPConnection() {
    $config = getEmailConfig();
    
    if (empty($config['username']) || empty($config['password'])) {
        return ['success' => false, 'message' => 'SMTP credentials not configured'];
    }
    
    $host = $config['secure'] === 'ssl' ? 'ssl://' . $config['host'] : $config['host'];
    $port = $config['port'];
    
    $socket = @stream_socket_client(
        "$host:$port",
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT
    );
    
    if (!$socket) {
        return ['success' => false, 'message' => "Connection failed: $errstr ($errno)"];
    }
    
    $response = fgets($socket, 515);
    fclose($socket);
    
    if (substr($response, 0, 3) === '220') {
        return ['success' => true, 'message' => 'SMTP connection successful'];
    }
    
    return ['success' => false, 'message' => "Unexpected response: $response"];
}
