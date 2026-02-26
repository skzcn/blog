<?php
declare(strict_types=1);

namespace app\common;

use app\model\Config;

/**
 * 邮件发送助手类 (简易 SMTP 实现)
 */
class Mail
{
    /**
     * 发送邮件
     * @param string $to 收件人
     * @param string $subject 主题
     * @param string $content 内容
     * @return bool|string 成功返回 true，失败返回错误信息
     */
    public static function send($to, $subject, $content)
    {
        $config = Config::getAll();
        $smtp_server = $config['smtp_server'] ?? '';
        $smtp_port = intval($config['smtp_port'] ?? 25);
        $smtp_user = $config['smtp_user'] ?? '';
        $smtp_pass = $config['smtp_pass'] ?? '';
        $sender_name = $config['sender_name'] ?? 'System';
        $sender_email = $config['sender_email'] ?? $smtp_user;

        if (empty($smtp_server) || empty($smtp_user) || empty($smtp_pass)) {
            return "SMTP configuration missing";
        }

        try {
            $socket = fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
            if (!$socket) return "Connection failed: $errstr ($errno)";

            self::getResponse($socket, "220");
            
            fwrite($socket, "HELO " . $smtp_server . "\r\n");
            self::getResponse($socket, "250");

            fwrite($socket, "AUTH LOGIN\r\n");
            self::getResponse($socket, "334");

            fwrite($socket, base64_encode($smtp_user) . "\r\n");
            self::getResponse($socket, "334");

            fwrite($socket, base64_encode($smtp_pass) . "\r\n");
            self::getResponse($socket, "235");

            fwrite($socket, "MAIL FROM: <$sender_email>\r\n");
            self::getResponse($socket, "250");

            fwrite($socket, "RCPT TO: <$to>\r\n");
            self::getResponse($socket, "250");

            fwrite($socket, "DATA\r\n");
            self::getResponse($socket, "354");

            $header = "To: $to\r\n";
            $header .= "From: $sender_name <$sender_email>\r\n";
            $header .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $header .= "MIME-Version: 1.0\r\n";
            $header .= "Content-Type: text/html; charset=utf-8\r\n";
            $header .= "Content-Transfer-Encoding: base64\r\n\r\n";
            
            $body = base64_encode($content) . "\r\n.\r\n";
            
            fwrite($socket, $header . $body);
            self::getResponse($socket, "250");

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private static function getResponse($socket, $expectedCode)
    {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == " ") break;
        }
        if (substr($response, 0, 3) !== $expectedCode) {
            throw new \Exception("SMTP Error: " . $response);
        }
    }
}
