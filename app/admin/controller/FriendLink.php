<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\FriendLink as FriendLinkModel;
use app\model\User;
use think\facade\View;

class FriendLink extends AdminBase
{
    public function index()
    {
        if ($this->request->isAjax()) {
            $page  = $this->request->param('page', 1);
            $limit = $this->request->param('limit', 15);
            $query = FriendLinkModel::with(['user']);

            // 搜索过滤
            $status = $this->request->param('status', '');
            if ($status !== '') {
                $query->where('status', $status);
            }
            $keyword = $this->request->param('keyword', '');
            if (!empty($keyword)) {
                $query->where('name|url', 'like', "%{$keyword}%");
            }

            $count = $query->count();
            $list  = $query->page((int)$page, (int)$limit)
                           ->order('sort', 'asc')
                           ->order('create_time', 'desc')
                           ->select();

            return json([
                'code'  => 0,
                'msg'   => '',
                'count' => $count,
                'data'  => $list
            ]);
        }
        return view();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['user_id'] = 0; // 管理员后台添加
            $data['status']  = 1; // 默认直接通过
            $data['is_paid'] = 0; 

            try {
                FriendLinkModel::create($data);
                return json(['code' => 1, 'msg' => '添加成功']);
            } catch (\Exception $e) {
                return json(['code' => 0, 'msg' => '添加失败：' . $e->getMessage()]);
            }
        }
        return view('edit');
    }

    public function edit()
    {
        $id = $this->request->param('id');
        $model = FriendLinkModel::find($id);
        if (!$model) {
            return $this->request->isAjax() ? json(['code' => 0, 'msg' => '记录不存在']) : '记录不存在';
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            try {
                $model->save($data);
                return json(['code' => 1, 'msg' => '更新成功']);
            } catch (\Exception $e) {
                return json(['code' => 0, 'msg' => '更新失败：' . $e->getMessage()]);
            }
        }
        
        View::assign('info', $model);
        return view();
    }

    public function delete()
    {
        $id = $this->request->post('id');
        $model = FriendLinkModel::find($id);
        if ($model) {
            $model->delete();
            return json(['code' => 1, 'msg' => '删除成功']);
        }
        return json(['code' => 0, 'msg' => '记录不存在']);
    }

    /**
     * 审核操作
     */
    public function audit()
    {
        $id = $this->request->post('id');
        $status = $this->request->post('status'); // 1 通过, 2 拒绝
        
        $model = FriendLinkModel::find($id);
        if (!$model) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }

        if (!in_array($status, [1, 2])) {
            return json(['code' => 0, 'msg' => '状态异常']);
        }

        $model->status = $status;
        $model->save();

        return json(['code' => 1, 'msg' => '操作成功']);
    }
}
