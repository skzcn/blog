<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 基础配置模型
 */
class Config extends Model
{
    // 系统配置表
    protected $table = 'blog_config';

    /**
     * 请求级静态缓存，避免同一请求内重复查询数据库
     */
    protected static $configCache = null;

    /**
     * 获取全站配置（带请求级缓存）
     * @return array
     */
    public static function getAll(): array
    {
        if (self::$configCache === null) {
            self::$configCache = self::column('value', 'key');
        }
        return self::$configCache;
    }

    /**
     * 获取配置值（从缓存中读取，避免逐项查询）
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getVal(string $key, $default = null)
    {
        $all = self::getAll();
        return $all[$key] ?? $default;
    }

    /**
     * 清除静态缓存（在后台修改配置后调用）
     */
    public static function clearCache(): void
    {
        self::$configCache = null;
    }
    /**
     * 判断订单是否已超时
     * @param array|\think\Model $order 订单数据对象或数组
     * @return bool
     */
    public static function isOrderExpired($order)
    {
        if (!$order) {
            return false;
        }
        
        $createTime = is_array($order) ? ($order['create_time'] ?? 0) : ($order->create_time ?? 0);
        if (!$createTime) {
            return false;
        }

        // 如果 create_time 是格式化后的字符串（例如 2024-01-01 10:00:00），需要转为时间戳
        if (is_string($createTime) && !is_numeric($createTime)) {
            $createTime = strtotime($createTime);
        }

        // 获取全局超时设置（分钟），默认为30分钟。如果设置为0代表不超时。
        $expireSetting = (int) self::getVal('order_expiration_time', 30);
        if ($expireSetting <= 0) {
            return false; 
        }

        $expireTime = (int)$createTime + ($expireSetting * 60);
        return time() > $expireTime;
    }
}
