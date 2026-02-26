<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\service\CategoryService;

/**
 * 分类管理
 */
class Category extends AdminBase
{
    protected CategoryService $categoryService;

    public function __construct(\think\App $app, CategoryService $categoryService)
    {
        parent::__construct($app);
        $this->categoryService = $categoryService;
    }

    /**
     * 分类列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = $this->categoryService->getAll();
            return json(['code' => 0, 'data' => $list]);
        }
        return view();
    }

    /**
     * 添加/编辑分类
     */
    public function form()
    {
        $id = $this->request->param('id', 0);
        $info = null;
        if ($id > 0) {
            $info = \app\model\Category::find($id);
        }
        
        // 获取一级分类供选择
        $parents = \app\model\Category::where('pid', 0)->where('id', '<>', $id)->select();

        return view('form', [
            'info' => $info,
            'parents' => $parents
        ]);
    }

    /**
     * 保存分类
     */
    public function save()
    {
        try {
            $data = $this->request->post();
            $this->categoryService->save($data);
            return json(['code' => 1, 'msg' => '操作成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 删除分类
     */
    public function delete()
    {
        try {
            $id = $this->request->post('id', 0);
            $this->categoryService->delete((int)$id);
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
