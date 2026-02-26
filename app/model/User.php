<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户模型
 */
class User extends Model
{
    protected $table = 'blog_user';
    protected $autoWriteDate = true;

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
