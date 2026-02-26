<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\View;

class Pay extends BaseController
{
    /**
     * Payment Cashier / Channel Selection
     */
    public function index()
    {
        $orderNo = $this->request->param('order_no');
        if (empty($orderNo)) {
            return '订单号不能为空';
        }

        // 1. Get Active Channels
        $channels = Db::name('blog_pay_channel')
            ->where('status', 1)
            ->where('type', 'IN', ['scan', 'jump', 'pc', 'wap', 'personal_alipay', 'personal_wx', 'lakala', 'jialian'])
            ->select();

        if ($channels->isEmpty()) {
            return '系统未配置支付通道';
        }

        // Convert to array if needed and force lowercase on all channel keys
        if ($channels instanceof \think\Collection) {
            $channels = $channels->toArray();
        }
        
        // Pre-process ALL channels to ensure lowercase keys for routing
        foreach ($channels as &$channel) {
            $channel['channel_key'] = strtolower($channel['channel_key']);
        }
        unset($channel);

        // 2. If only one channel, redirect immediately
        if (count($channels) == 1) {
            $channel = $channels[0];
            $method = $channel['channel_key']; // now guaranteed to be lowercase
            
            // Check if method exists in this controller
            if (method_exists($this, $method)) {
                return redirect((string) url('index/Pay/' . $method, ['order_no' => $orderNo]));
            } else {
                 return '支付通道方法未定义: ' . $method;
            }
        }

        // 3. If multiple, render selection view

    // 3. If multiple, render selection view
    // Calculate expire_seconds for countdown on pay page
    $order = Db::name('blog_order')->where('order_no', $orderNo)->find();
    $expireSeconds = 0;
    $orderPrice = 0;
    if ($order) {
        $orderPrice = $order['price'];
        $createTime = $order['create_time'];
        if (is_string($createTime) && !is_numeric($createTime)) {
            $createTime = strtotime($createTime);
        }
        $expireSetting = (int) \app\model\Config::getVal('order_expiration_time', 30);
        if ($expireSetting > 0 && $createTime > 0) {
            $remaining = ((int)$createTime + ($expireSetting * 60)) - time();
            $expireSeconds = $remaining > 0 ? $remaining : 0;
        }
    }
    return view('index', [
        'channels' => $channels,
        'orderNo' => $orderNo,
        'expireSeconds' => $expireSeconds,
        'orderPrice' => $orderPrice
    ]);
    }

    /**
     * YPay (EasyPay Protocol)
     */
    public function ypay()
    {
        $orderNo = $this->request->param('order_no');
        if (empty($orderNo)) return '订单号不能为空';

        $order = Db::name('blog_order')->where('order_no', $orderNo)->find();
        if (!$order) return '订单不存在';
        if ($order['status'] == 1) return '订单已支付';

        // Check Expiration
        if (\app\model\Config::isOrderExpired($order)) {
            return '订单已超时并关闭，请重新下单';
        }

        // Get YPay Channel Config
        $channel = Db::name('blog_pay_channel')->where('channel_key', 'ypay')->where('status', 1)->find();
        if (!$channel) return '未配置YPay通道';

        // Parameters
        $data = [
            'pid'          => $channel['mch_id'],
            'type'         => 'alipay', // Default to alipay, or let user select if supported by YPay param
            'out_trade_no' => $order['order_no'],
            'notify_url'   => request()->domain() . (string)url('Pay/ypayNotify'),
            'return_url'   => request()->domain() . (string)url('Pay/ypayReturn'),
            'name'         => 'VIP Purchase',
            'money'        => $order['price'],
            'sitename'     => 'My Blog'
        ];

        // Sign
        $data['sign'] = $this->ypaySign($data, $channel['mch_secret']);
        $data['sign_type'] = 'MD5';

        // Submit Form
        $gateway = "https://ypay.skzcn.com/submit.php"; // Hardcoded as requested
        
        $sHtml = "<form id='ypaysubmit' name='ypaysubmit' action='" . $gateway . "' method='POST'>";
        foreach ($data as $key => $val) {
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }
        $sHtml .= "<input type='submit' value='ok' style='display:none;'></form>";
        $sHtml .= "<script>document.forms['ypaysubmit'].submit();</script>";
        
        return $sHtml;
    }

    private function ypaySign($params, $key)
    {
        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null && $k != 'sign' && $k != 'sign_type') {
                $signStr .= $k . '=' . $v . '&';
            }
        }
        $signStr = substr($signStr, 0, -1);
        $signStr .= $key;
        return md5($signStr);
    }

    /**
     * sky pay alipay
     */
    public function alipay()
    {
        $orderNo = $this->request->param('order_no');
        if (empty($orderNo)) {
            return '订单号不能为空';
        }

        // 1. 获取订单信息
        $order = Db::name('blog_order')->where('order_no', $orderNo)->find();
        if (!$order) {
            return '订单不存在';
        }
        if ($order['status'] == 1) {
            return '订单已支付';
        }

        // Check Expiration
        if (\app\model\Config::isOrderExpired($order)) {
            return '订单已超时并关闭，请重新下单';
        }

        // 2. 获取支付配置 (这里假设使用第一个开启的支付宝通道，或者根据逻辑选择)
        // 简单起见，我们查找 channel_key 为 'alipay' 或者 type 为 'pc'/'wap' 的通道
        $channel = Db::name('blog_pay_channel')
            ->where('status', 1)
            ->where('channel_key', 'alipay') // 或者根据实际 key 查找
            ->find();

        if (!$channel) {
            // 尝试找任意一个支付宝通道
            $channel = Db::name('blog_pay_channel')
                ->where('status', 1)
                ->where('type', 'IN', ['scan', 'jump', 'pc', 'wap'])
                ->where('channel_key', 'LIKE', '%alipay%') // 模糊匹配
                ->find();
        }

        if (!$channel) {
            return '未配置支付宝通道';
        }

        // 3. 构造支付宝参数
        $params = [
            'app_id'      => $channel['mch_id'], // 这里假设 mch_id 存的是 AppID
            'method'      => 'alipay.trade.page.pay',
            'format'      => 'JSON',
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'notify_url'  => request()->domain() . (string)url('Pay/alipayNotify'),
            'return_url'  => request()->domain() . (string)url('Pay/alipayReturn'),
            'biz_content' => json_encode([
                'out_trade_no' => $order['order_no'],
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
                'total_amount' => $order['price'],
                'subject'      => '订单支付-' . $order['order_no'],
            ], JSON_UNESCAPED_UNICODE),
        ];

        // 4.计算签名
        $params['sign'] = $this->generateSign($params, $channel['mch_secret']); // mch_secret 存私钥

        // 5. 构建表单自动提交
        return $this->buildForm($params);
    }

    /**
     * 生成签名
     */
    private function generateSign($params, $privateKey)
    {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($privateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        
        if ("RSA2" == $params['sign_type']) {
            openssl_sign($stringToBeSigned, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($stringToBeSigned, $sign, $res);
        }
        
        return base64_encode($sign);
    }

    /**
     * YPay Notify Handler
     */
    public function ypayNotify()
    {
        $params = $this->request->param();
        $channel = Db::name('blog_pay_channel')->where('channel_key', 'ypay')->where('status', 1)->find();
        
        if (!$channel) {
            \think\facade\Log::error('[YPayNotify] YPay channel not found or disabled');
            return 'fail';
        }

        $sign = $this->ypaySign($params, $channel['mch_secret']);
        
        if (isset($params['sign']) && $params['sign'] === $sign) {
            try {
                $orderNo = $params['out_trade_no'] ?? '';
                $orderService = new \app\service\OrderService();
                $orderService->completeOrder($orderNo, 'ypay');
                \think\facade\Log::info('[YPayNotify] Order completed: ' . $orderNo);
                return 'success';
            } catch (\Exception $e) {
                \think\facade\Log::error('[YPayNotify] completeOrder failed: ' . $e->getMessage());
                return 'fail';
            }
        }
        \think\facade\Log::error('[YPayNotify] Signature mismatch');
        return 'fail';
    }

    /**
     * YPay Return Handler
     */
    public function ypayReturn()
    {
        $params = $this->request->param();
        $channel = Db::name('blog_pay_channel')->where('channel_key', 'ypay')->where('status', 1)->find();
        
        if (!$channel) return 'Payment Channel Error';

        $sign = $this->ypaySign($params, $channel['mch_secret']);
        
        if (isset($params['sign']) && $params['sign'] === $sign) {
             return redirect((string)url('user/index'));
        }
        return 'Payment Verification Failed';
    }

    /**
     * Alipay Notify Handler
     */
    public function alipayNotify()
    {
        $params = $this->request->param();
        $orderNo = $params['out_trade_no'] ?? '';

        if (empty($orderNo)) {
            \think\facade\Log::error('[AlipayNotify] Missing out_trade_no');
            return 'fail';
        }

        // 验证交易状态
        $tradeStatus = $params['trade_status'] ?? '';
        if (!in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            \think\facade\Log::info('[AlipayNotify] Trade not success, status: ' . $tradeStatus . ', order: ' . $orderNo);
            return 'success'; // 返回success避免支付宝重复通知
        }

        // RSA签名验证（如果配置了支付宝公钥）
        $channel = Db::name('blog_pay_channel')->where('channel_key', 'alipay')->where('status', 1)->find();
        if ($channel && !empty($channel['mch_public_key'])) {
            $sign = $params['sign'] ?? '';
            $signType = $params['sign_type'] ?? 'RSA2';
            unset($params['sign'], $params['sign_type']);
            ksort($params);
            
            $stringToBeSigned = '';
            $i = 0;
            foreach ($params as $k => $v) {
                if (!$this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                    $stringToBeSigned .= ($i == 0 ? '' : '&') . "$k=$v";
                    $i++;
                }
            }

            $pubKey = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($channel['mch_public_key'], 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
            
            $algorithm = ($signType === 'RSA2') ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
            $result = openssl_verify($stringToBeSigned, base64_decode($sign), $pubKey, $algorithm);
            
            if ($result !== 1) {
                \think\facade\Log::error('[AlipayNotify] RSA signature verification FAILED for order: ' . $orderNo);
                return 'fail';
            }
            \think\facade\Log::info('[AlipayNotify] Signature verified for order: ' . $orderNo);
        } else {
            \think\facade\Log::warning('[AlipayNotify] No public key configured, skipping signature verification for order: ' . $orderNo);
        }

        try {
            $orderService = new \app\service\OrderService();
            $orderService->completeOrder($orderNo, 'alipay');
            \think\facade\Log::info('[AlipayNotify] Order completed: ' . $orderNo);
            return 'success';
        } catch (\Exception $e) {
            \think\facade\Log::error('[AlipayNotify] completeOrder failed: ' . $e->getMessage());
            return 'fail';
        }
    }

    /**
     * Alipay Return Handler
     */
    public function alipayReturn()
    {
        $params = $this->request->param();
        $orderNo = $params['out_trade_no'] ?? '';
        
        if (!empty($orderNo)) {
            $channel = Db::name('blog_pay_channel')->where('channel_key', 'alipay')->where('status', 1)->find();
            if ($channel && !empty($channel['mch_public_key'])) {
                $sign = $params['sign'] ?? '';
                $signType = $params['sign_type'] ?? 'RSA2';
                unset($params['sign'], $params['sign_type']);
                ksort($params);
                
                $stringToBeSigned = '';
                $i = 0;
                foreach ($params as $k => $v) {
                    if (!$this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                        $stringToBeSigned .= ($i == 0 ? '' : '&') . "$k=$v";
                        $i++;
                    }
                }

                $pubKey = "-----BEGIN PUBLIC KEY-----\n" .
                    wordwrap($channel['mch_public_key'], 64, "\n", true) .
                    "\n-----END PUBLIC KEY-----";
                
                $algorithm = ($signType === 'RSA2') ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
                $result = openssl_verify($stringToBeSigned, base64_decode($sign), $pubKey, $algorithm);
                
                if ($result === 1) {
                    // Signature valid, sync complete order
                    try {
                        $orderService = new \app\service\OrderService();
                        $orderService->completeOrder($orderNo, 'alipay');
                        \think\facade\Log::info('[AlipayReturn] Order synchronously completed: ' . $orderNo);
                    } catch (\Exception $e) {
                        \think\facade\Log::error('[AlipayReturn] completeOrder failed: ' . $e->getMessage());
                    }
                }
            }
        }
        
        return redirect((string)url('user/index'));
    }

    /**
     * sky pay alipay
     */
    private function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }

    /**
     * Personal Alipay
     */
    public function personal_alipay()
    {
        return $this->scanPay('personal_alipay', '支付宝');
    }

    /**
     * Personal WeChat
     */
    public function personal_wx()
    {
        return $this->scanPay('personal_wx', '微信');
    }

    /**
     * Lakala
     */
    public function lakala()
    {
        return $this->scanPay('lakala', '拉卡拉');
    }

    /**
     * Jialian
     */
    public function jialian()
    {
        return $this->scanPay('jialian', '嘉联支付');
    }

    /**
     * Helper for Scan Payment
     */
    private function scanPay($channelType, $typeName)
    {
        $orderNo = $this->request->param('order_no');
        if (empty($orderNo)) return '订单号不能为空';

        $order = Db::name('blog_order')->where('order_no', $orderNo)->find();
        if (!$order) return '订单不存在';
        if ($order['status'] == 1) return '订单已支付';

        // Check Expiration
        if (\app\model\Config::isOrderExpired($order)) {
            return '订单已超时并关闭，请重新下单';
        }

        // Find Channel by Type (assuming only one active per type for simplicity, or find specific by key if passed)
        // Correct logic: Pay::index dispatches by KEY. But here I mapped methods by TYPE for convenience? 
        // Actually Pay::index dispatches by channel_key. 
        // If user names the channel "my_alipay", it calls my_alipay().
        // Since I can't predict user keys, I should rely on the caller or standard keys.
        // However, standard practice here: user creates channel with key 'personal_alipay'.
        // So this method name matches the standard key. 
        
        $channel = Db::name('blog_pay_channel')
            ->where('channel_key', $channelType) // strict key match
            ->where('status', 1)
            ->find();

        if (!$channel) {
             return '未配置该支付通道';
        }

        return view('pay/scan', [
            'order_no'  => $orderNo,
            'price'     => $order['price'],
            'qr_content'=> $channel['mch_key'], // Stored QR string
            'type'      => strpos($channelType, 'wx') !== false ? 'wxpay' : 'alipay',
            'typeName'  => $typeName
        ]);
    }

    /**
     * 构建提交表单
     */
    private function buildForm($params)
    {
        $gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . $gatewayUrl . "?charset=utf-8' method='POST'>";
        foreach ($params as $key => $val) {
            $val = str_replace("'", "&apos;", $val);
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }
        $sHtml .= "<input type='submit' value='ok' style='display:none;'></form>";
        $sHtml .= "<script>document.forms['alipaysubmit'].submit();</script>";
        return $sHtml;
    }
    /**
     * Magic Method to handle dynamic channel keys
     */
    public function __call($method, $args)
    {
        // 安全校验：只允许字母数字下划线，防止注入
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $method)) {
            return '非法请求';
        }
        
        $channel = Db::name('blog_pay_channel')
            ->where('channel_key', $method) // Try exact match first
            ->where('status', 1)
            ->find();
            
        if ($channel) {
            // If it's a personal/scan type, use scanPay
            if (in_array($channel['type'], ['personal_alipay', 'personal_wx', 'lakala', 'jialian', 'scan'])) {
                 $typeName = $channel['name'];
                 return $this->scanPay($method, $typeName);
            }
            // If it's a jump type (direct redirect)
            if ($channel['type'] == 'jump') {
                return $this->jumpPay($method, $channel);
            }

            // For PC/WAP, if no specific method exists, we might default to a generic gateway if applicable,
            // or return a friendly error.
            if (in_array($channel['type'], ['pc', 'wap'])) {
                 return '该支付通道 (' . $channel['name'] . ') 需要特定的集成逻辑，暂未找到对应的处理方法: ' . $method;
            }

            return '该通道类型暂不支持自动路由，请检查 Payment Controller 实现';
        }
        
        return '支付通道方法未定义: ' . $method;
    }

    /**
     * Jump / Redirect Payment
     */
    private function jumpPay($method, $channel)
    {
        $orderNo = $this->request->param('order_no');
        if (empty($orderNo)) return '订单号不能为空';

        $order = Db::name('blog_order')->where('order_no', $orderNo)->find();
        if (!$order) return '订单不存在';
        if ($order['status'] == 1) return '订单已支付';

        // Check Expiration
        if (\app\model\Config::isOrderExpired($order)) {
            return '订单已超时并关闭，请重新下单';
        }

        // Assuming mch_key holds the target URL for jump types
        $url = $channel['mch_key'];
        
        if (empty($url)) {
            return '支付跳转链接未配置';
        }

        // Basic parameter replacement if supported, otherwise just append
        // We append standard params just in case: order_no, price, name
        $params = [
            'order_no' => $orderNo,
            'price'    => $order['price'],
            'name'     => 'VIP Purchase'
        ];
        
        $query = http_build_query($params);
        if (strpos($url, '?') !== false) {
            $url .= '&' . $query;
        } else {
            $url .= '?' . $query;
        }
        
        return redirect($url);
    }

    /**
     * Dispatcher for Return URL callbacks
     */
    public function returnDispatch()
    {
        $action = $this->request->param('action', '');
        // 安全校验
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
            return redirect((string)url('user/index'));
        }
        $method = $action . 'Return';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return redirect((string)url('user/index'));
    }

    /**
     * Dispatcher for Notify URL callbacks
     */
    public function notifyDispatch()
    {
        $action = $this->request->param('action', '');
        // 安全校验
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $action)) {
            return 'fail';
        }
        $method = $action . 'Notify';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return 'fail';
    }
}
