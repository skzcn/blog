<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\service\UserService;
use think\App;

/**
 * 用户管理
 */
class User extends AdminBase
{
    protected UserService $userService;

    public function __construct(App $app, UserService $userService)
    {
        parent::__construct($app);
        $this->userService = $userService;
    }

    /**
     * 用户列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            $username = $this->request->get('username', '');
            
            $where = [];
            if ($username) $where[] = ['username', 'like', "%$username%"];
            
            $res = $this->userService->getList($where, (int)$page, (int)$limit);
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
     * 用户编辑窗体
     */
    public function form()
    {
        $id = $this->request->param('id', 0);
        $info = null;
        if ($id > 0) {
            $info = \app\model\User::find($id);
        }
        // 获取所有VIP等级
        $vipLevels = \app\model\VipLevel::order('level', 'asc')->select();
        
        return view('form', [
            'info' => $info,
            'vipLevels' => $vipLevels
        ]);
    }

    /**
     * 保存用户
     */
    public function save()
    {
        try {
            $data = $this->request->post();
            $this->userService->save($data);
            return json(['code' => 1, 'msg' => '操作成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 删除用户
     */
    public function delete()
    {
        try {
            $id = $this->request->post('id', 0);
            $this->userService->delete((int)$id);
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
