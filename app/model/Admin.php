<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 管理员模型
 */
class Admin extends Model
{
    protected $table = 'blog_admin';

    /**
     * 密码哈希加密存入
     * @param string $value
     * @return string
     */
    public function setPasswordAttr($value)
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }
}
