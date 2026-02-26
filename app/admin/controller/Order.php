<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\service\OrderService;

/**
 * 订单管理
 */
class Order extends AdminBase
{
    protected OrderService $orderService;

    public function __construct(\think\App $app, OrderService $orderService)
    {
        parent::__construct($app);
        $this->orderService = $orderService;
    }

    /**
     * 订单列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            $orderNo = $this->request->get('order_no', '');
            
            $where = [];
            // $where = [['type', '=', 1]]; // 移除限制，显示所有（VIP+资源）
            if ($orderNo) $where[] = ['order_no', 'like', "%$orderNo%"];
            
            $res = $this->orderService->getList($where, (int)$page, (int)$limit);
            return json([
                'code' => 0,
                'msg' => '',
                'count' => $res['count'],
                'data' => $res['list']
            ]);
        }
        return view();
    }

    /**
     * 余额记录 (充值订单)
     */
    public function balance()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            $orderNo = $this->request->get('order_no', '');
            
            $where = [['type', '=', 2]]; // 仅充值订单
            if ($orderNo) $where[] = ['order_no', 'like', "%$orderNo%"];
            
            $res = $this->orderService->getList($where, (int)$page, (int)$limit);
            return json([
                'code' => 0,
                'msg' => '',
                'count' => $res['count'],
                'data' => $res['list']
            ]);
        }
        return view();
    }

    /**
     * 删除订单
     */
    public function delete()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            if (\app\model\Order::destroy($id)) {
                return json(['code' => 1, 'msg' => '删除成功']);
            }
            return json(['code' => 0, 'msg' => '删除失败']);
        }
    }

    /**
     * 手动确认支付
     */
    public function confirmPay()
    {
        try {
            $orderNo = $this->request->post('order_no');
            $order = \app\model\Order::where('order_no', $orderNo)->find();
            if (!$order) {
                return json(['code' => 0, 'msg' => '订单不存在']);
            }
            if ($order->status == 0 && \app\model\Config::isOrderExpired($order)) {
                return json(['code' => 0, 'msg' => '订单已超时，无法确认支付']);
            }
            $this->orderService->completeOrder($orderNo, 'manual');
            return json(['code' => 1, 'msg' => '操作成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
