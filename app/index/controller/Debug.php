<?php
namespace app\controller;
use app\BaseController;
use think\facade\Db;

class Debug extends BaseController {
    public function check() {
        try {
            $tables = Db::getTables();
            return json([
                'code' => 1,
                'msg' => 'Database connected',
                'tables' => $tables
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 0,
                'msg' => $e->getMessage()
            ]);
        }
    }
}
