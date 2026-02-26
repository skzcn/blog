<?php
declare(strict_types=1);

namespace app\admin\controller;

/**
 * 后台辅助控制器
 */
class Index extends AdminBase
{
    public function index()
    {
        return view();
    }

    /**
     * 欢迎页
     */
    public function welcome()
    {
        $today = strtotime(date('Y-m-d'));
        
        // 统计数据
        $stats = [
            'total_article' => \app\model\Article::count(),
            'total_user'    => \app\model\User::count(),
            'today_order'   => \app\model\Order::whereTime('create_time', 'today')->count(),
            'today_money'   => \app\model\Order::whereTime('create_time', 'today')->where('status', 1)->sum('price'),
        ];

        // 获取最近5条订单记录
        $recent_orders = \app\model\Order::with(['user'])
            ->order('create_time', 'desc')
            ->limit(5)
            ->select();

        // 系统信息 - 获取首位管理员创建时间作为运营起点
        $install_time = \app\model\Admin::order('id', 'asc')->value('create_time');
        if (is_numeric($install_time) && $install_time > 0) {
            $start_timestamp = (int)$install_time;
        } elseif ($install_time) {
            $start_timestamp = strtotime((string)$install_time);
        } else {
            $start_timestamp = time(); // 兜底
        }

        $sys_info = [
            'php_version' => PHP_VERSION,
            'tp_version'  => \think\facade\App::version(),
            'mysql_version' => \think\facade\Db::query("SELECT VERSION() as ver")[0]['ver'],
            'os'          => PHP_OS,
            'server'      => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'start_time'  => $start_timestamp, 
        ];

        return view('welcome', [
            'stats'         => $stats,
            'recent_orders' => $recent_orders,
            'sys_info'      => $sys_info
        ]);
    }
}
