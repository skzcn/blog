<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 友情链接模型
 */
class FriendLink extends Model
{
    protected $table = 'blog_friend_link';
    protected $autoWriteDate = true;

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 关联用户 (0 表示后台添加)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
