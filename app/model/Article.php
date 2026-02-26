<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 文章模型
 */
class Article extends Model
{
    protected $table = 'blog_article';
    protected $autoWriteDate = true;

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
