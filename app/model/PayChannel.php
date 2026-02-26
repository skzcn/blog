<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 支付通道模型
 */
class PayChannel extends Model
{
    protected $table = 'blog_pay_channel';

    protected $autoWriteTimestamp = true;
}
