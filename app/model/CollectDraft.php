<?php
namespace app\model;

use think\Model;

/**
 * 采集草稿模型
 */
class CollectDraft extends Model
{
    protected $table = 'blog_collect_draft';
    protected $autoWriteTimestamp = true;

    /**
     * 关联采集节点
     */
    public function node()
    {
        return $this->belongsTo(CollectNode::class, 'node_id');
    }
}
