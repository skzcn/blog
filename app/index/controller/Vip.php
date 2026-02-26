<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;
use app\model\VipLevel;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;

class Vip extends BaseController
{
    /**
     * VIP Center / Pricing Page
     */
    /**
     * VIP Center / Pricing Page
     */
    public function index()
    {
        return redirect((string) url('user/index', ['tab' => 'vip']));
    }

    /**
     * Process VIP Purchase
     */
    /**
     * Process VIP Purchase (Using Balance)
     */
    public function pay()
    {
        // 1. Check Login
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
             return json(['code' => 0, 'msg' => '请先登录']);
        }
        $user = \app\model\User::find($sessionUser['id']);

        // 2. Validate VIP Level
        $vipId = $this->request->param('id');
        $vip = VipLevel::find($vipId);
        if (!$vip) {
             return json(['code' => 0, 'msg' => 'VIP等级不存在']);
        }

        // 3. Check Balance
        if ($user->money < $vip->price) {
             return json(['code' => 0, 'msg' => '余额不足，请先充值']);
        }

        Db::startTrans();
        try {
            // 4. Deduct Balance
            $user->money -= $vip->price;
            $user->save();

            // 5. Create "Paid" Order Log
            $orderNo = date('YmdHis') . mt_rand(1000, 9999);
            $orderId = Db::name('blog_order')->insertGetId([
                'order_no'    => $orderNo,
                'user_id'     => $user->id,
                'article_id'  => 0,
                'vip_id'      => $vip->id,
                'type'        => 3, // VIP Purchase
                'price'       => $vip->price, // Stored as Coins
                'status'      => 1, // Paid
                'pay_type'    => 'balance',
                'create_time' => time(),
                'pay_time'    => time()
            ]);

            // 6. Grant VIP Logic (Reusing OrderService logic or duplicating for simplicity)
            $currentExpire = $user->vip_expire_time;
            if ($user->vip_level > 0 && $currentExpire > time()) {
                $startTime = $currentExpire;
            } else {
                $startTime = time();
            }
            if ($vip->duration == 0) {
                 $expireTime = 9999999999; // Permanent
            } else {
                 $expireTime = $startTime + ($vip->duration * 86400);
            }
            
            $user->vip_level = $vip->id;
            $user->vip_expire_time = $expireTime;
            $user->save();

            Db::commit();

            // Refresh Session
            Session::set('user', $user->toArray());

            return json(['code' => 1, 'msg' => '开通成功']);

        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '开通失败:' . $e->getMessage()]);
        }
    }
    /**
     * VIP Purchase (Direct Cashier)
     */
    public function buy()
    {
        // 1. Check Login
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
             return json(['code' => 0, 'msg' => '请先登录']);
        }
        $user = \app\model\User::find($sessionUser['id']);

        // 2. Validate VIP Level
        $vipId = $this->request->param('id');
        $vip = VipLevel::find($vipId);
        if (!$vip) {
             return json(['code' => 0, 'msg' => 'VIP等级不存在']);
        }

        Db::startTrans();
        try {
            // 3. Create "Unpaid" Order Log
            $orderNo = date('YmdHis') . mt_rand(1000, 9999);
            
            // Get Exchange Rate
            $config = \app\model\Config::where('key', 'exchange_rate')->value('value');
            $rate = $config ? floatval($config) : 100;
            if ($rate <= 0) $rate = 100; // Prevent division by zero

            // Calculate RMB price: Gold / Rate (e.g. 1000 Gold / 100 = 10 RMB)
            $rmbPrice = $vip->price / $rate;
            
            // Ensure price is formatted to 2 decimal places if needed, but float is fine for DB usually.
            // insertGetId expects array
            $orderId = Db::name('blog_order')->insertGetId([
                'order_no'    => $orderNo,
                'user_id'     => $user->id,
                'article_id'  => 0,
                'vip_id'      => $vip->id,
                'type'        => 3, // VIP Purchase
                'price'       => $rmbPrice, // Stored as RMB for payment gateway
                'status'      => 0, // Unpaid
                'create_time' => time()
            ]);

            Db::commit();

            return json([
                'code' => 1, 
                'msg' => '订单创建成功', 
                'url' => (string)url('index/Pay/index', ['order_no' => $orderNo])
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '创建订单失败:' . $e->getMessage()]);
        }
    }
}
