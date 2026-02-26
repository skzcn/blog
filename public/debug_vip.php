<?php
require 'public/index.php';
use think\facade\Db;

try {
    echo "--- VIP Levels ---\n";
    $levels = Db::name('blog_vip_level')->select();
    foreach ($levels as $l) {
        print_r($l);
    }

    echo "\n--- Recent VIP Orders ---\n";
    $orders = Db::name('blog_order')->where('type', 3)->order('id', 'desc')->limit(5)->select();
    foreach ($orders as $o) {
        print_r($o);
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
