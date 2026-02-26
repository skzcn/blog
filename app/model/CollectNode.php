<?php
namespace app\model;

use think\Model;

/**
 * 采集节点模型
 */
class CollectNode extends Model
{
    protected $table = 'blog_collect_node';
    protected $autoWriteDate = true;

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
