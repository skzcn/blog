<?php
declare(strict_types=1);

namespace app\service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use app\model\Config;

class EmailService
{
    /**
     * 发送邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件标题
     * @param string $body 邮件内容
     * @return bool|string 成功返回true，失败返回错误信息
     */
    public static function send($to, $subject, $body)
    {
        $config = Config::getAll();
        
        // Check if email config exists
        if (empty($config['email_smtp_host']) || empty($config['email_smtp_user']) || empty($config['email_smtp_pass'])) {
            return '邮件服务未配置';
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $config['email_smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['email_smtp_user'];
            $mail->Password   = $config['email_smtp_pass'];
            
            $port = isset($config['email_smtp_port']) ? intval($config['email_smtp_port']) : 465;
            $mail->Port       = $port;
            if ($port == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($port == 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->CharSet    = 'UTF-8';

            // Recipients
            $fromName = isset($config['email_from_name']) ? $config['email_from_name'] : 'Blog System';
            $fromAddr = isset($config['email_from_addr']) ? $config['email_from_addr'] : $config['email_smtp_user'];
            
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            return "邮件发送失败: {$mail->ErrorInfo}";
        }
    }
}
