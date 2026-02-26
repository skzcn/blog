<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\BaseController;
use app\model\Admin;
use think\facade\Session;

/**
 * 后台登录控制器
 */
class Login extends BaseController
{
    /**
     * 登录界面
     */
    public function index()
    {
        if (Session::has('admin_user')) {
            return redirect((string) url('index/index'));
        }
        return view();
    }

    /**
     * 登录处理
     */
    public function doLogin()
    {
        $params = $this->request->post();
        $username = $params['username'] ?? '';
        $password = $params['password'] ?? '';

        if (empty($username) || empty($password)) {
            return json(['code' => 0, 'msg' => '请输入账号密码']);
        }

        // 登录速率限制：同一IP 15分钟内最多5次失败
        $ip = $this->request->ip();
        $cacheKey = 'admin_login_fail_' . md5($ip . $username);
        $failCount = cache($cacheKey) ?: 0;
        if ($failCount >= 5) {
            return json(['code' => 0, 'msg' => '登录失败次数过多，请15分钟后再试']);
        }

        $admin = Admin::where('username', $username)->find();
        
        if (!$admin || !password_verify($password, $admin['password'])) {
            cache($cacheKey, $failCount + 1, 900);
            return json(['code' => 0, 'msg' => '账号或密码错误']);
        }

        // 登录成功，清除失败计数
        cache($cacheKey, null);

        // 记录登录状态
        Session::set('admin_user', $admin->toArray());
        
        // 更新登录信息
        $admin->save([
            'last_login_time' => time(),
            'last_login_ip'   => $ip
        ]);

        return json(['code' => 1, 'msg' => '登录成功']);
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        Session::delete('admin_user');
        return redirect((string) url('login/index'));
    }
}
