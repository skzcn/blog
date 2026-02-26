<?php
declare(strict_types=1);

namespace app\service;

use app\model\Order;
use think\facade\Db;

/**
 * 订单管理服务类
 */
class OrderService
{
    /**
     * 获取订单列表
     */
    public function getList(array $where = [], int $page = 1, int $limit = 20)
    {
        $query = Order::with(['user', 'article', 'vipLevel'])->where($where);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('id', 'desc')->select();
        
        $list->each(function($item){
            $item['is_expired'] = \app\model\Config::isOrderExpired($item);
            
            // Calculate expire_time and expire_seconds for countdown
            $createTime = $item->create_time ?? 0;
            if (is_string($createTime) && !is_numeric($createTime)) {
                $createTime = strtotime($createTime);
            }
            $expireSetting = (int) \app\model\Config::getVal('order_expiration_time', 30);
            if ($expireSetting > 0 && $createTime > 0) {
                $item['expire_time'] = (int)$createTime + ($expireSetting * 60);
                $remaining = $item['expire_time'] - time();
                $item['expire_seconds'] = $remaining > 0 ? $remaining : 0;
            } else {
                $item['expire_time'] = 0;
                $item['expire_seconds'] = 0;
            }
            
            $item->append(['is_expired', 'expire_time', 'expire_seconds']);
        });

        return [
            'count' => $count,
            'list'  => $list
        ];
    }

    /**
     * 完成订单支付 (用于手动修改状态或回调)
     */
    public function completeOrder(string $orderNo, string $payType)
    {
        return Db::transaction(function () use ($orderNo, $payType) {
            $order = Order::where('order_no', $orderNo)->lock(true)->find();
            if (!$order) throw new \Exception('订单不存在');
            if ($order->status == 1) return true;

            $order->status = 1;
            $order->pay_type = $payType;
            $order->pay_time = time();
            $order->save();

            // 如果是购买文章，增加销量和处理作者收益抽成
            if ($order->type == 1 && $order->article_id) {
                Db::name('blog_article')->where('id', $order->article_id)->inc('sales')->update();
                
                // 处理作者收益
                $article = Db::name('blog_article')->where('id', $order->article_id)->find();
                if ($article && !empty($article['author_id'])) {
                    $platformFeeRate = \app\model\Config::where('key', 'platform_fee')->value('value');
                    $feeRate = is_numeric($platformFeeRate) ? floatval($platformFeeRate) : 10;
                    if ($feeRate < 0) $feeRate = 0;
                    if ($feeRate > 100) $feeRate = 100;
                    
                    // author cut: original price * (100 - feeRate) / 100
                    $authorCut = round($order->price * ((100 - $feeRate) / 100), 2);
                    
                    if ($authorCut > 0) {
                        Db::name('blog_user')->where('id', $article['author_id'])
                            ->inc('money', $authorCut)
                            ->inc('author_earnings', $authorCut)
                            ->update();
                    }
                }
            }

            // 如果是购买VIP，给用户授予VIP等级
            if ($order->type == 3 && $order->vip_id) {
                $vipLevel = Db::name('blog_vip_level')->where('id', $order->vip_id)->find();
                if ($vipLevel) {
                    $user = Db::name('blog_user')->where('id', $order->user_id)->find();
                    $currentExpire = $user['vip_expire_time'] ?? 0;
                    
                    // 如果当前已是VIP且未过期，时间累加；否则从当前时间开始计算
                    if ($user['vip_level'] > 0 && $currentExpire > time()) {
                        $startTime = $currentExpire;
                    } else {
                        $startTime = time();
                    }
                    
                    // 计算新的过期时间
                    $expireTime = $startTime + ($vipLevel['duration'] * 86400);
                    
                    // 更新用户VIP等级
                    Db::name('blog_user')->where('id', $order->user_id)->update([
                        'vip_level'       => $order->vip_id,
                        'vip_expire_time' => $expireTime,
                        'update_time'     => time()
                    ]);
                }
            }

            // 如果是余额充值
            if ($order->type == 4) {
                // Get Exchange Rate
                $config = \app\model\Config::where('key', 'exchange_rate')->value('value');
                $rate = $config ? floatval($config) : 100;
                if ($rate <= 0) $rate = 100;

                // Calculate Coins: RMB * Rate
                $coins = $order->price * $rate;
                Db::name('blog_user')->where('id', $order->user_id)->inc('money', $coins)->update();
            }

            // 如果是购买友情链接
            if ($order->type == 5 && $order->article_id) {
                // 将对应友链的状态更新为通过 (status = 1)
                Db::name('blog_friend_link')
                    ->where('id', $order->article_id)
                    ->update([
                        'status' => 1,
                        'is_paid' => 1,
                        'update_time' => time()
                    ]);
            }

            return true;
        });
    }
}
