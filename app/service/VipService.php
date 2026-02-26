<?php
declare(strict_types=1);

namespace app\service;

use app\model\VipLevel;

/**
 * VIP管理服务
 */
class VipService
{
    /**
     * 获取所有VIP等级
     */
    public function getList()
    {
        return VipLevel::order('level', 'asc')->select();
    }

    /**
     * 保存等级设置
     */
    public function save(array $data)
    {
        // 增加数据验证
        if (isset($data['duration']) && $data['duration'] < 0) {
            throw new \Exception('有效天数不能为负数');
        }
        if (isset($data['price']) && $data['price'] < 0) {
            throw new \Exception('销售价格不能为负数');
        }

        if (isset($data['id']) && $data['id'] > 0) {
            $vip = VipLevel::find($data['id']);
            $vip->save($data);
        } else {
            VipLevel::create($data);
        }
        return true;
    }

    /**
     * 删除VIP等级
     */
    public function delete(int $id)
    {
        return VipLevel::destroy($id);
    }
}
