<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;

class Alipay extends BaseController
{
    public function index()
    {
        // Forward to Pay::alipay
        return (new Pay($this->app))->alipay();
    }

    public function __call($method, $args)
    {
        return (new Pay($this->app))->alipay();
    }
}
