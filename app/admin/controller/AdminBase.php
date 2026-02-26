<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\BaseController;
use think\facade\View;
use think\facade\Session;

/**
 * 后台基础控制器，包含鉴权
 */
class AdminBase extends BaseController
{
    /**
     * 控制器初始化
     */
    protected function initialize()
    {
        parent::initialize();
        
        // 简单鉴权
        if (!Session::has('admin_user')) {
            // 如果是AJAX请求，返回JSON，否则重定向
            if ($this->request->isAjax()) {
                exit(json_encode(['code' => -1, 'msg' => '请先登录']));
            }
            header('Location: ' . url('login/index'));
            exit;
        }

        // 全局共享管理员信息
        $admin = Session::get('admin_user');
        View::assign('admin', $admin);

        // RBAC 权限检查
        if ($admin['id'] != 1) { // 假设 ID 为 1 的是超级管理员
            $controller = strtolower($this->request->controller());
            $action = strtolower($this->request->action());
            $ruleName = $controller . '/' . $action;
            
            // 获取管理员所属组的所有权限规则
            $rules = \think\facade\Db::name('auth_group_access')
                ->alias('a')
                ->join('auth_group g', 'a.group_id = g.id')
                ->where('a.uid', $admin['id'])
                ->where('g.status', 1)
                ->value('rules');
            
            if ($rules) {
                $hasRule = \think\facade\Db::name('auth_rule')
                    ->where('id', 'in', $rules)
                    ->where('name', $ruleName)
                    ->where('status', 1)
                    ->find();
                
                if (!$hasRule && !in_array($ruleName, ['index/index', 'index/welcome', 'upload/image', 'upload/video', 'upload/file'])) {
                    if ($this->request->isAjax()) {
                        exit(json_encode(['code' => 0, 'msg' => '权限不足，请联系管理员']));
                    }
                    abort(403, '权限不足，请联系管理员');
                }
            }
        }
    }
}
