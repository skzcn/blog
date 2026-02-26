<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 广告模型
 */
class Ad extends Model
{
    protected $table = 'blog_ad';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = false;
}
