<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\service\VipService;
use app\model\AuthGroup;
use think\App;

/**
 * VIP等级设置
 */
class Vip extends AdminBase
{
    protected VipService $vipService;

    public function __construct(App $app, VipService $vipService)
    {
        parent::__construct($app);
        $this->vipService = $vipService;
    }

    /**
     * 等级列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = $this->vipService->getList();
            return json(['code' => 0, 'data' => $list]);
        }
        return view();
    }

    /**
     * 编辑窗体
     */
    public function form()
    {
        $id = $this->request->param('id', 0);
        $info = null;
        if ($id > 0) {
            $info = \app\model\VipLevel::find($id);
        }
        
        return view('form', [
            'info' => $info
        ]);
    }

    /**
     * 保存设置
     */
    public function save()
    {
        try {
            $data = $this->request->post();
            $this->vipService->save($data);
            return json(['code' => 1, 'msg' => '操作成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 删除VIP等级
     */
    public function delete()
    {
        try {
            $id = $this->request->post('id', 0);
            $this->vipService->delete((int)$id);
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
