<?php
declare(strict_types=1);

namespace app\service;

use app\model\PayChannel;

class PayChannelService
{
    /**
     * 获取列表
     */
    public function getList(array $where = [], int $page = 1, int $limit = 10)
    {
        // Handle Group Filter
        if (isset($where['group'])) {
            $group = $where['group'];
            unset($where['group']); // Remove custom param
            
            if ($group === 'personal') {
                $where[] = ['type', 'in', ['personal_alipay', 'personal_wx', 'lakala', 'jialian']];
            } else {
                $where[] = ['type', 'in', ['scan', 'jump', 'pc', 'wap']];
            }
        }

        $query = PayChannel::where($where)->order('create_time', 'desc');
        
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        
        // Append type_text for display
        foreach ($list as &$item) {
            $types = [
                'scan' => '扫码', 'jump' => '跳转', 'pc' => 'PC网页', 'wap' => '手机网页',
                'personal_alipay' => '个人支付宝', 'personal_wx' => '个人微信',
                'lakala' => '拉卡拉', 'jialian' => '嘉联支付'
            ];
            $item['type_text'] = $types[$item['type']] ?? $item['type'];
        }
        
        return [
            'count' => $count,
            'list'  => $list
        ];
    }

    /**
     * 保存数据
     */
    public function save(array $data)
    {
        if (isset($data['id']) && $data['id'] > 0) {
            $model = PayChannel::find($data['id']);
            if (!$model) {
                throw new \Exception('记录不存在');
            }
            $model->save($data);
        } else {
            // 检查标识是否存在
            if (PayChannel::where('channel_key', $data['channel_key'])->find()) {
                throw new \Exception('支付标识已存在');
            }
            PayChannel::create($data);
        }
    }

    /**
     * 删除记录
     */
    public function delete(int $id)
    {
        $model = PayChannel::find($id);
        if ($model) {
            $model->delete();
        }
    }
}
