<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\Ad as AdModel;
use think\facade\View;

/**
 * 广告管理控制器
 */
class Ad extends AdminBase
{
    /**
     * 广告列表
     */
    public function index()
    {
        $list = AdModel::order('sort', 'desc')->order('id', 'desc')->paginate(15);
        return view('index', ['list' => $list]);
    }

    /**
     * 添加广告
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['create_time'] = time();
            $res = AdModel::create($data);
            if ($res) {
                return json(['code' => 1, 'msg' => '添加成功']);
            }
            return json(['code' => 0, 'msg' => '添加失败']);
        }
        return view('form', ['info' => null]);
    }

    /**
     * 编辑广告
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $info = AdModel::find($id);
        if (!$info) {
            return $this->error('广告不存在');
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            $res = $info->save($data);
            if ($res) {
                return json(['code' => 1, 'msg' => '更新成功']);
            }
            return json(['code' => 0, 'msg' => '更新失败']);
        }
        return view('form', ['info' => $info]);
    }

    /**
     * 删除广告
     */
    public function delete()
    {
        $id = $this->request->post('id');
        $res = AdModel::destroy($id);
        if ($res) {
            return json(['code' => 1, 'msg' => '删除成功']);
        }
        return json(['code' => 0, 'msg' => '删除失败']);
    }

    /**
     * 切换状态
     */
    public function status()
    {
        $id = $this->request->post('id');
        $status = $this->request->post('status');
        $info = AdModel::find($id);
        if ($info) {
            $info->status = $status;
            $info->save();
            return json(['code' => 1, 'msg' => '操作成功']);
        }
        return json(['code' => 0, 'msg' => '操作失败']);
    }
}
