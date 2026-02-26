<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Comment extends Model
{
    protected $name = 'blog_comment';
    protected $autoWriteTimestamp = true;

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id', 'id');
    }
}
