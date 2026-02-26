<?php
declare(strict_types=1);

namespace app\admin\controller;

use think\facade\Db;
use think\facade\View;

/**
 * 工单管理
 */
class Ticket extends AdminBase
{
    /**
     * 工单列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            
            $query = Db::name('blog_ticket')->alias('t')
                ->join('blog_user u', 't.user_id = u.id', 'LEFT')
                ->field('t.*, u.username');
                
            $count = $query->count();
            $list = $query->page((int)$page, (int)$limit)->order('t.id', 'desc')->select();
            
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
     * 回复工单
     */
    public function reply()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            $replyContent = $this->request->post('reply_content', '');
            
            if (empty($replyContent)) {
                return json(['code' => 0, 'msg' => '回复内容不能为空']);
            }
            
            $ticket = Db::name('blog_ticket')->where('id', $id)->find();
            if (!$ticket) {
                return json(['code' => 0, 'msg' => '工单不存在']);
            }
            
            Db::name('blog_ticket')->where('id', $id)->update([
                'reply_content' => $replyContent,
                'status' => 1, // 已回复
                'update_time' => time()
            ]);
            
            return json(['code' => 1, 'msg' => '回复成功']);
        }
    }

    /**
     * 关闭工单
     */
    public function close()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            
            $ticket = Db::name('blog_ticket')->where('id', $id)->find();
            if (!$ticket) {
                return json(['code' => 0, 'msg' => '工单不存在']);
            }
            
            Db::name('blog_ticket')->where('id', $id)->update([
                'status' => 2, // 已关闭
                'update_time' => time()
            ]);
            
            return json(['code' => 1, 'msg' => '操作成功']);
        }
    }
    
    /**
     * 删除工单
     */
    public function delete()
    {
        if ($this->request->isPost()) {
            $id = $this->request->post('id');
            if (Db::name('blog_ticket')->where('id', $id)->delete()) {
                return json(['code' => 1, 'msg' => '删除成功']);
            }
            return json(['code' => 0, 'msg' => '删除失败']);
        }
    }
}
