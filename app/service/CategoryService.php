<?php
declare(strict_types=1);

namespace app\service;

use app\model\Category;
use think\facade\Db;

/**
 * 分类管理服务类
 */
class CategoryService
{
    /**
     * 获取所有分类（树状结构）
     */
    public function getAll()
    {
        $all = Category::order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        return $this->flattenTree($all);
    }

    /**
     * 将树形数据打平供列表显示
     */
    protected function flattenTree(array $list, int $pid = 0)
    {
        $arr = [];
        foreach ($list as $item) {
            if ($item['pid'] == $pid) {
                $arr[] = $item;
                $children = $this->flattenTree($list, $item['id']);
                if ($children) {
                    $arr = array_merge($arr, $children);
                }
            }
        }
        return $arr;
    }

    /**
     * 构建无限级分类树
     */
    public function buildTree(array $list, int $pid = 0, int $level = 0)
    {
        $tree = [];
        foreach ($list as $item) {
            if ($item['pid'] == $pid) {
                $item['level'] = $level;
                $item['children'] = $this->buildTree($list, $item['id'], $level + 1);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * 保存或更新分类
     */
    public function save(array $data)
    {
        if (isset($data['id']) && $data['id'] > 0) {
            $category = Category::find($data['id']);
            if (!$category) throw new \Exception('分类不存在');
            return $category->save($data);
        } else {
            return Category::create($data);
        }
    }

    /**
     * 删除分类
     */
    public function delete(int $id)
    {
        $category = Category::find($id);
        if (!$category) throw new \Exception('分类不存在');
        
        // 检查是否有子分类
        $hasChild = Category::where('pid', $id)->count();
        if ($hasChild > 0) {
            throw new \Exception('该分类下还有二级分类，无法删除');
        }

        // 检查是否有文章
        $hasArticle = Db::name('blog_article')->where('category_id', $id)->count();
        if ($hasArticle > 0) {
            throw new \Exception('该分类下还有文章，无法删除');
        }

        return $category->delete();
    }
}
