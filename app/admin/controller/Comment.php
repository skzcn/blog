<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\BaseController;
use app\model\Comment as CommentModel;
use think\facade\View;
use think\facade\Db;

class Comment extends AdminBase
{
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            
            $list = CommentModel::with(['user', 'article'])
                ->order('create_time', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);

            return json([
                'code' => 0,
                'msg' => '',
                'count' => $list->total(),
                'data' => $list->items()
            ]);
        }
        return view();
    }

    public function delete()
    {
        $id = $this->request->post('id');
        CommentModel::destroy($id);
        return json(['code' => 1, 'msg' => '删除成功']);
    }

    public function edit()
    {
        $id = $this->request->param('id');
        $info = CommentModel::find($id);
        if ($this->request->isAjax()) {
            $content = $this->request->post('content');
            $info->content = $content;
            $info->save();
            return json(['code' => 1, 'msg' => '修改成功']);
        }
        // Return a simple view or just use Layer prompt in index.html, 
        // but if we need a view:
        return view('edit', ['info' => $info]);
    }

    // Sensitive Words Management
    public function words()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            $list = Db::name('blog_sensitive_words')->paginate(['list_rows' => $limit, 'page' => $page]);
            
            return json([
                'code' => 0,
                'msg' => '',
                'count' => $list->total(),
                'data' => $list->items()
            ]);
        }
        return view('words');
    }

    public function addWord()
    {
        $word = $this->request->post('word');
        if($word){
            Db::name('blog_sensitive_words')->insert([
                'word' => trim($word),
                'create_time' => time()
            ]);
            return json(['code'=>1, 'msg'=>'添加成功']);
        }
        return json(['code'=>0, 'msg'=>'内容不能为空']);
    }

    public function deleteWord()
    {
        $id = $this->request->post('id');
        Db::name('blog_sensitive_words')->delete($id);
        return json(['code'=>1, 'msg'=>'删除成功']);
    }
}
