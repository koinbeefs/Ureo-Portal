<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailProduction($to, $subject, $body, $queue_number = null) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER; // From config.php
        $mail->Password   = SMTP_PASS; // App password from config.php
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom(SYSTEM_EMAIL, SYSTEM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SYSTEM_EMAIL, SYSTEM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        $success = true;
        $error_message = '';
        
    } catch (Exception $e) {
        $success = false;
        $error_message = "Email could not be sent. Error: {$mail->ErrorInfo}";
        error_log($error_message);
    }
    
    // Log email to database
    $conn = getDBConnection();
    $status = $success ? 'sent' : 'failed';
    $stmt = $conn->prepare("INSERT INTO email_logs (queue_number, recipient_email, email_type, subject, body_html, status, error_message) VALUES (?, ?, 'general', ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $queue_number, $to, $subject, $body, $status, $error_message);
    $stmt->execute();
    closeDBConnection($conn);
    
    return $success;
}
?>
