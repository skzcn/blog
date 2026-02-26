<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 文章分类模型
 */
class Category extends Model
{
    protected $table = 'blog_category';
    protected $autoWriteDate = true;

    /**
     * 关联文章
     */
    public function articles()
    {
        return $this->hasMany(Article::class, 'category_id');
    }
}
