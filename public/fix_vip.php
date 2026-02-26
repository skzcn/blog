<?php
namespace think;

require __DIR__ . '/../vendor/autoload.php';

// Initialize App but don't run HTTP layer
$app = new App();
$app->initialize();

// Custom Logic
try {
    $rateConfig = \app\model\Config::where('key', 'exchange_rate')->find();
    $rate = $rateConfig ? floatval($rateConfig->value) : 100;
    if ($rate <= 0) $rate = 100;

    echo "Current Exchange Rate: " . $rate . "\n";

    // Assume current prices are based on 100
    // If user set 1:1000, and prices are still 1:100 based (e.g. 1000 Gold = 10 RMB, but should be 10000 Gold = 10 RMB)
    $ratio = $rate / 100;

    if ($ratio == 1) {
        echo "Ratio is 1. No change needed.\n";
    } else {
        echo "Applying fix with ratio: $ratio\n";
        $vips = \app\model\VipLevel::select();
        foreach ($vips as $vip) {
             $oldPrice = $vip->price;
             $newPrice = ceil($oldPrice * $ratio);
             echo "ID {$vip->id}: {$oldPrice} -> {$newPrice}\n";
             
             \think\facade\Db::name('blog_vip_level')
                 ->where('id', $vip->id)
                 ->update(['price' => $newPrice]);
        }
        echo "Done.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
