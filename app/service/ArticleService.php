<?php
declare(strict_types=1);

namespace app\service;

use app\model\Article;
use think\facade\Db;

/**
 * 文章管理服务类
 */
class ArticleService
{
    /**
     * 获取文章列表
     */
    public function getList(array $where = [], int $page = 1, int $limit = 20)
    {
        $query = Article::with(['category'])->where($where);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('id', 'desc')->select();
        
        return [
            'count' => $count,
            'list'  => $list
        ];
    }

    /**
     * 保存或更新文章
     */
    public function save(array $data)
    {
        if (isset($data['id']) && $data['id'] > 0) {
            $article = Article::find($data['id']);
            if (!$article) throw new \Exception('文章不存在');
            return $article->save($data);
        } else {
            return Article::create($data);
        }
    }

    /**
     * 删除文章
     */
    public function delete(int $id)
    {
        $article = Article::find($id);
        if (!$article) throw new \Exception('文章不存在');
        return $article->delete();
    }
}
