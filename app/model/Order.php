<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 订单模型
 */
class Order extends Model
{
    protected $table = 'blog_order';
    protected $autoWriteDate = true;

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联文章
     */
    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    /**
     * 关联VIP等级
     */
    public function vipLevel()
    {
        return $this->belongsTo(VipLevel::class, 'vip_id');
    }
}
