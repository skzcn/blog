<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\Config;

/**
 * 系统设置控制器
 */
class Settings extends AdminBase
{
    /**
     * 网站设置
     */
    public function site()
    {
        if ($this->request->isPost()) {
            return $this->saveSettings();
        }
        $configs = Config::getAll();
        return view('site', ['config' => $configs]);
    }

    /**
     * 邮件设置
     */
    public function email()
    {
        if ($this->request->isPost()) {
            return $this->saveSettings();
        }
        $configs = Config::getAll();
        return view('email', ['config' => $configs]);
    }

    /**
     * 测试邮件发送
     */
    public function testEmail()
    {
        $params = $this->request->post();
        $to = $params['test_email'] ?? '';
        if (empty($to)) {
            return json(['code' => 0, 'msg' => '请输入接收测试邮件的邮箱']);
        }

        $config = $params;
        if (empty($config['email_smtp_host']) || empty($config['email_smtp_user']) || empty($config['email_smtp_pass'])) {
            return json(['code' => 0, 'msg' => '邮件服务配置不完整']);
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $config['email_smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['email_smtp_user'];
            $mail->Password   = $config['email_smtp_pass'];
            
            $port = isset($config['email_smtp_port']) ? intval($config['email_smtp_port']) : 465;
            $mail->Port       = $port;
            if ($port == 465) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($port == 587) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->CharSet    = 'UTF-8';

            $fromName = !empty($config['email_from_name']) ? $config['email_from_name'] : 'Blog System';
            $fromAddr = !empty($config['email_from_addr']) ? $config['email_from_addr'] : $config['email_smtp_user'];
            
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = '【测试邮件】SMTP配置成功';
            $mail->Body    = '这是一封测试邮件。如果您看到此邮件，则说明您的 SMTP 配置是正确的！';

            $mail->send();
            return json(['code' => 1, 'msg' => '测试邮件发送成功！']);
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $errorMsg = $mail->ErrorInfo;
            // 针对 QQ 邮箱等常见的认证失败提供明确的提示
            if (stripos($errorMsg, 'Could not authenticate') !== false) {
                $errorMsg .= ' (提示: QQ/网易等邮箱的密码请填写“授权码”而非登录密码，并确认已在邮箱设置中开启 SMTP 服务)';
            }
            return json(['code' => 0, 'msg' => "测试邮件发送失败: {$errorMsg}"]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => "测试邮件发送异常: {$e->getMessage()}"]);
        }
    }


    /**
     * 支付设置
     */
    public function pay()
    {
        if ($this->request->isPost()) {
            return $this->saveSettings();
        }
        $configs = \app\model\Config::getAll();
        return view('pay', ['config' => $configs]);
    }

    /**
     * 兑换设置
     */
    public function exchange()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            // Validate that exchange_rate is numeric and positive
            if (!isset($params['exchange_rate']) || !is_numeric($params['exchange_rate']) || $params['exchange_rate'] <= 0) {
                 return json(['code' => 0, 'msg' => '兑换比例必须为正数']);
            }
            
            // Get old rate to calculate ratio
            $oldRateConfig = \app\model\Config::where('key', 'exchange_rate')->find();
            $oldRate = $oldRateConfig ? floatval($oldRateConfig->value) : 100;
            if ($oldRate <= 0) $oldRate = 100;
            
            $newRate = floatval($params['exchange_rate']);
            
            // Save Settings
            $this->saveSettings(); 
            
            // Re-calculate VIP prices if rate changed
            if (abs($oldRate - $newRate) > 0.01) {
                $ratio = $newRate / $oldRate;
                // Update all VIP levels price
                $vipLevels = \app\model\VipLevel::select();
                foreach($vipLevels as $vip) {
                    // Calculate new price
                    $newPrice = ceil($vip->price * $ratio);
                    
                    // Update directly using Db to avoid any model event interference if any
                    \think\facade\Db::name('blog_vip_level')
                        ->where('id', $vip->id)
                        ->update(['price' => $newPrice]);
                }
            }
            
            return json(['code' => 1, 'msg' => '保存成功，VIP价格已自动调整']);
        }
        $configs = \app\model\Config::getAll();
        return view('exchange', ['config' => $configs]);
    }

    private function saveSettings()
    {
        $params = $this->request->post();
        foreach ($params as $key => $value) {
            $exist = Config::where('key', $key)->find();
            if ($exist) {
                $exist->value = $value;
                $exist->save();
            } else {
                Config::create(['key' => $key, 'value' => $value]);
            }
        }
        return json(['code' => 1, 'msg' => '保存成功']);
    }
}
