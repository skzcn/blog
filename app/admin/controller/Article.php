<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\service\ArticleService;
use app\service\CategoryService;
use think\facade\Db;
use think\facade\Session;

/**
 * 文章管理
 */
class Article extends AdminBase
{
    protected ArticleService $articleService;
    protected CategoryService $categoryService;

    public function __construct(\think\App $app, ArticleService $articleService, CategoryService $categoryService)
    {
        parent::__construct($app);
        $this->articleService = $articleService;
        $this->categoryService = $categoryService;
    }

    /**
     * 文章列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = $this->request->get('page', 1);
            $limit = $this->request->get('limit', 10);
            $title = $this->request->get('title', '');
            
            $where = [];
            if ($title) $where[] = ['title', 'like', "%$title%"];
            
            $res = $this->articleService->getList($where, (int)$page, (int)$limit);
            return json([
                'code' => 0,
                'msg' => '',
                'count' => $res['count'],
                'data' => $res['list']
            ]);
        }
        return view();
    }

    /**
     * 添加/编辑文章
     */
    public function form()
    {
        $id = $this->request->param('id', 0);
        $info = null;
        if ($id > 0) {
            $info = \app\model\Article::find($id);
        }
        $categories = $this->categoryService->getAll();
        return view('form', [
            'info' => $info,
            'categories' => $categories
        ]);
    }

    /**
     * 保存文章
     */
    public function save()
    {
        try {
            $data = $this->request->post();
            $this->articleService->save($data);
            
            // Attachment Binding Logic
            $content = $data['content'] ?? '';
            $thumb = $data['image'] ?? '';
            
            // Extract all storage URLs from content
            preg_match_all('/\/storage\/[^\s"\'>]+/', $content . ' ' . $thumb, $matches);
            if (!empty($matches[0])) {
                $urls = array_unique($matches[0]);
                Db::name('attachment')->where('url', 'in', $urls)->update(['status' => 1]);
            }

            return json(['code' => 1, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 删除文章
     */
    public function delete()
    {
        try {
            $id = $this->request->post('id', 0);
            $this->articleService->delete((int)$id);
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
