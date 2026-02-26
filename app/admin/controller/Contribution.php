<?php
declare(strict_types=1);

namespace app\admin\controller;

use think\facade\Db;
use think\facade\View;

/**
 * 投稿审核管理
 */
class Contribution extends AdminBase
{
    /**
     * 投稿列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            
            $query = Db::name('blog_contribution')->alias('c')
                ->join('blog_user u', 'c.user_id = u.id', 'LEFT')
                ->join('blog_category cat', 'c.category_id = cat.id', 'LEFT')
                ->field('c.*, u.username, cat.name as category_name');
                
            $count = $query->count();
            $list = $query->page((int)$page, (int)$limit)->order('c.id', 'desc')->select();
            
            return json([
                'code' => 0,
                'msg' => '',
                'count' => $count,
                'data' => $list
            ]);
        }
        return view();
    }

    /**
     * 通过审核
     */
    public function approve()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            
            $contribution = Db::name('blog_contribution')->where('id', $id)->find();
            if (!$contribution) {
                return json(['code' => 0, 'msg' => '投稿记录不存在']);
            }
            if ($contribution['status'] == 1) {
                return json(['code' => 0, 'msg' => '该投稿已经通过了']);
            }
            
            Db::startTrans();
            try {
                // 1. Update contribution status
                Db::name('blog_contribution')->where('id', $id)->update([
                    'status' => 1, // 已通过
                    'update_time' => time(),
                    'reject_reason' => ''
                ]);
                
                // 2. Create standard article
                Db::name('blog_article')->insert([
                    'category_id'  => $contribution['category_id'] ?: 1,
                    'author_id'    => $contribution['user_id'],
                    'price'        => $contribution['price'] ?: 0.00,
                    'is_vip_free'  => $contribution['is_vip_free'] ?: 0,
                    'resource_url' => $contribution['resource_url'] ?: '',
                    'resource_pwd' => $contribution['resource_pwd'] ?: '',
                    'title'        => $contribution['title'],
                    'content'      => $contribution['content'],
                    'status'       => 1, // 直接发布
                    'create_time'  => time(),
                    'update_time'  => time()
                ]);
                
                Db::commit();
                return json(['code' => 1, 'msg' => '审核通过并发布自动成功']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '审核通过失败: ' . $e->getMessage()]);
            }
        }
    }

    /**
     * 拒绝审核 (打回)
     */
    public function reject()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $reason = $this->request->post('reject_reason', '');
            
            if (empty($reason)) {
                return json(['code' => 0, 'msg' => '必须填写打回原因']);
            }
            
            $contribution = Db::name('blog_contribution')->where('id', $id)->find();
            if (!$contribution) {
                return json(['code' => 0, 'msg' => '投稿记录不存在']);
            }
            
            Db::name('blog_contribution')->where('id', $id)->update([
                'status' => 2, // 2 = 打回/退回
                'reject_reason' => $reason,
                'update_time' => time()
            ]);
            
            return json(['code' => 1, 'msg' => '操纵成功，已打回']);
        }
    }
    
    /**
     * 删除记录
     */
    public function delete()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            if (Db::name('blog_contribution')->where('id', $id)->delete()) {
                return json(['code' => 1, 'msg' => '删除成功']);
            }
            return json(['code' => 0, 'msg' => '删除失败']);
        }
    }
}
