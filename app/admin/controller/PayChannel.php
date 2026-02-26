<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\service\PayChannelService;
use think\App;

/**
 * 支付通道管理
 */
class PayChannel extends AdminBase
{
    protected PayChannelService $payChannelService;

    public function __construct(App $app, PayChannelService $payChannelService)
    {
        parent::__construct($app);
        $this->payChannelService = $payChannelService;
    }

    /**
     * 列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            
            $res = $this->payChannelService->getList([], (int)$page, (int)$limit);
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
     * 表单
     */
    public function form()
    {
        $id = $this->request->param('id', 0);
        $info = null;
        if ($id > 0) {
            $info = \app\model\PayChannel::find($id);
        }
        return view('form', ['info' => $info]);
    }

    /**
     * 保存
     */
    public function save()
    {
        try {
            $data = $this->request->post();
            $this->payChannelService->save($data);
            return json(['code' => 1, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 删除
     */
    public function delete()
    {
        try {
            $id = $this->request->post('id', 0);
            $this->payChannelService->delete((int)$id);
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
