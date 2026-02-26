<?php
namespace app\index\controller;

use app\BaseController;
use think\facade\Db;

class UpdatePrice extends BaseController
{
    public function index()
    {
        // Multiply all VIP level prices by 100
        Db::execute("UPDATE blog_vip_level SET price = price * 100");
        return "VIP Prices Updated Successfully";
    }
}
