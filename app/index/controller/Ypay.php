<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;

class Ypay extends BaseController
{
    public function index()
    {
        // Forward to Pay::ypay
        return (new Pay($this->app))->ypay();
    }

    public function __call($method, $args)
    {
        return (new Pay($this->app))->ypay();
    }
}
