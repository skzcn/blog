<?php
use think\facade\Route;

Route::get('/', 'Index/index');

// 支付回调路由：/pay/return/:action -> Pay/returnDispatch
Route::rule('pay/return/:action', 'Pay/returnDispatch');
// 支付通知路由：/pay/notify/:action -> Pay/{action}Notify  
Route::rule('pay/notify/:action', 'Pay/notifyDispatch');

