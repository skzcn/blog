<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\CollectNode;
use app\service\CategoryService;
use app\service\CollectService;

class Collect extends AdminBase
{
    protected CollectService $collectService;
    protected CategoryService $categoryService;

    public function __construct(\think\App $app, CollectService $collectService, CategoryService $categoryService)
    {
        parent::__construct($app);
        $this->collectService = $collectService;
        $this->categoryService = $categoryService;
    }

    /**
     * 采集节点列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            
            $list = CollectNode::with(['category'])->page((int)$page, (int)$limit)->order('id', 'desc')->select();
            $count = CollectNode::count();
            
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
     * 添加/编辑节点
     */
    public function form()
    {
        $id = $this->request->param('id', 0);
        $info = null;
        if ($id > 0) {
            $info = CollectNode::find($id);
        }
        $categories = $this->categoryService->getAll();
        
        return view('form', [
            'info' => $info,
            'categories' => $categories
        ]);
    }

    /**
     * 保存节点
     */
    public function save()
    {
        try {
            $data = $this->request->post();
            
            if (empty($data['id'])) {
                CollectNode::create($data);
            } else {
                CollectNode::update($data);
            }
            return json(['code' => 1, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 删除节点
     */
    public function delete()
    {
        try {
            $id = $this->request->post('id', 0);
            CollectNode::destroy($id);
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 测试单页采集
     */
    public function test()
    {
        $id = $this->request->param('id', 0);
        $url = $this->request->param('url', '');
        
        if (empty($url)) {
             return json(['code' => 0, 'msg' => '测试地址不能为空']);
        }

        $res = $this->collectService->testCollect((int)$id, $url);
        
        if ($res['success']) {
            return json(['code' => 1, 'msg' => '测试成功', 'data' => $res['data']]);
        } else {
            return json(['code' => 0, 'msg' => $res['msg']]);
        }
    }

    /**
     * 测试列表采集
     */
    public function testList()
    {
         $id = $this->request->param('id', 0);
         $url = $this->request->param('url', '');
        
        if (empty($url)) {
             return json(['code' => 0, 'msg' => '测试地址不能为空']);
        }

        $res = $this->collectService->testCollectList((int)$id, $url);
        
        if ($res['success']) {
            return json(['code' => 1, 'msg' => '测试成功', 'data' => $res['data']]);
        } else {
            return json(['code' => 0, 'msg' => $res['msg']]);
        }
    }

    /**
     * 执行采集
     */
    public function execute()
    {
        $id = $this->request->param('id', 0);
        $pages = $this->request->param('pages', 1);

        try {
             $res = $this->collectService->executeCollect((int)$id, (int)$pages);
             return json(['code' => 1, 'msg' => "采集完成：成功 {$res['successCount']} 条，失败 {$res['failCount']} 条"]);
        } catch (\Exception $e) {
             return json(['code' => 0, 'msg' => '执行失败：' . $e->getMessage()]);
        }
    }

    /**
     * SSE 流式执行采集（实时进度反馈）
     */
    public function executeStream()
    {
        $id = $this->request->param('id', 0);
        $pages = $this->request->param('pages', 1);

        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Disable output buffering
        while (ob_get_level()) {
            ob_end_flush();
        }

        // Continue running even if client disconnects
        ignore_user_abort(true);

        // Set unlimited execution time for long-running collection
        set_time_limit(0);

        try {
            $this->collectService->executeCollectStream((int)$id, (int)$pages);
        } catch (\Exception $e) {
            echo "event: error\n";
            echo "data: " . json_encode(['msg' => '执行失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        exit;
    }

    /**
     * 草稿管理列表
     */
    public function draft()
    {
        if ($this->request->isAjax() || $this->request->param('page')) {
            $page = $this->request->param('page', 1);
            $limit = $this->request->param('limit', 15);
            $status = $this->request->param('status', '');

            $where = [];
            if ($status !== '') {
                $where[] = ['status', '=', (int)$status];
            }
            $count = \app\model\CollectDraft::where($where)->count();
            $list = \app\model\CollectDraft::where($where)->order('id', 'desc')
                ->page((int)$page, (int)$limit)->select()->each(function ($item) {
                $node = CollectNode::find($item->node_id);
                $item->node_name = $node ? $node->name : '未知';
                $item->status_text = ['待审核', '已入库', '已拒绝'][$item->status] ?? '未知';
                $item->create_time_text = empty($item->create_time) ? '未知时间' : date('Y-m-d H:i', (int)$item->create_time);
                return $item;
            });
            return json(['code' => 0, 'msg' => '', 'count' => $count, 'data' => $list]);
        }

        return view('collect/draft');
    }

    /**
     * 审核通过（单条）
     */
    public function draftApprove()
    {
        $id = $this->request->param('id', 0);
        $draft = \app\model\CollectDraft::find($id);
        if (!$draft) {
            return json(['code' => 0, 'msg' => '草稿不存在']);
        }
        if ($draft->status == 1) {
            return json(['code' => 0, 'msg' => '该草稿已入库']);
        }

        $node = CollectNode::find($draft->node_id);
        $categoryId = $node ? $node->category_id : 0;

        try {
            $thumbnail = '';
            if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $draft->content, $matches)) {
                $thumbnail = $matches[1];
            }

            \think\facade\Db::name('blog_article')->insert([
                'title' => $draft->title,
                'category_id' => $categoryId,
                'content' => $draft->content,
                'thumbnail' => $thumbnail,
                'author_id' => 0,
                'price' => $draft->price,
                'resource_url' => !empty($draft->download_url) ? $draft->download_url : $draft->resource_url,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            $draft->status = 1;
            $draft->save();
            return json(['code' => 1, 'msg' => '已入库']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '入库失败：' . $e->getMessage()]);
        }
    }

    /**
     * 批量审核通过
     */
    public function draftBatchApprove()
    {
        $ids = $this->request->param('ids', '');
        if (empty($ids)) {
            return json(['code' => 0, 'msg' => '请选择要审核的草稿']);
        }
        $idArr = explode(',', $ids);
        $success = 0;
        $fail = 0;

        foreach ($idArr as $id) {
            $draft = \app\model\CollectDraft::find((int)$id);
            if (!$draft || $draft->status == 1) {
                $fail++;
                continue;
            }
            $node = CollectNode::find($draft->node_id);
            $categoryId = $node ? $node->category_id : 0;

            try {
                $thumbnail = '';
                if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $draft->content, $matches)) {
                    $thumbnail = $matches[1];
                }

                \think\facade\Db::name('blog_article')->insert([
                    'title' => $draft->title,
                    'category_id' => $categoryId,
                    'content' => $draft->content,
                    'thumbnail' => $thumbnail,
                    'author_id' => 0,
                    'price' => $draft->price,
                    'resource_url' => !empty($draft->download_url) ? $draft->download_url : $draft->resource_url,
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
                $draft->status = 1;
                $draft->save();
                $success++;
            } catch (\Exception $e) {
                $fail++;
            }
        }

        return json(['code' => 1, 'msg' => "批量入库完成：成功 {$success} 篇，失败 {$fail} 篇"]);
    }

    /**
     * 删除草稿（单条）
     */
    public function draftDelete()
    {
        $id = $this->request->param('id', 0);
        $draft = \app\model\CollectDraft::find($id);
        if (!$draft) {
            return json(['code' => 0, 'msg' => '草稿不存在']);
        }
        $draft->delete();
        return json(['code' => 1, 'msg' => '已删除']);
    }

    /**
     * 批量删除草稿
     */
    public function draftBatchDelete()
    {
        $ids = $this->request->param('ids', '');
        if (empty($ids)) {
            return json(['code' => 0, 'msg' => '请选择要删除的草稿']);
        }
        $idArr = explode(',', $ids);
        \app\model\CollectDraft::destroy($idArr);
        return json(['code' => 1, 'msg' => '已删除 ' . count($idArr) . ' 条']);
    }

    /**
     * 草稿预览
     */
    public function draftPreview()
    {
        $id = $this->request->param('id', 0);
        $draft = \app\model\CollectDraft::find($id);
        if (!$draft) {
            return json(['code' => 0, 'msg' => '草稿不存在']);
        }
        return json(['code' => 1, 'data' => [
            'title' => $draft->title,
            'content' => $draft->content,
            'download_url' => $draft->download_url,
            'is_paid_download' => $draft->is_paid_download ?? 0,
        ]]);
    }
}
