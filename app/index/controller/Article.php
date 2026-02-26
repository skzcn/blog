<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;
use app\model\Article as ArticleModel;
use app\model\Config;
use think\facade\View;

/**
 * 前台文章详情
 */
class Article extends BaseController
{
    /**
     * 文章详情页
     */
    public function details()
    {
        $id = (int)$this->request->param('id', 0);
        $article = ArticleModel::with(['category', 'user'])->where('id', $id)->find();
        
        if (!$article) {
            abort(404, '内容不存在');
        }

        // 获取访问IP，并基于IP增加浏览量
        $ip = $this->request->ip();
        $hasViewed = \think\facade\Db::name('blog_article_views')
            ->where('article_id', $article->id)
            ->where('ip', $ip)
            ->find();
            
        if (!$hasViewed) {
            // 记录访问
            \think\facade\Db::name('blog_article_views')->insert([
                'article_id' => $article->id,
                'ip' => $ip,
                'create_time' => time()
            ]);
            // 真正增加浏览量
            $article->inc('views')->save();
        }

        $site_config = Config::getAll();
        if (!isset($site_config['article_default_image'])) {
            $site_config['article_default_image'] = '/static/images/default.jpg';
        }
        $allCategories = \app\model\Category::order('sort', 'desc')->select()->toArray();
        $categories = [];
        foreach ($allCategories as $catItem) {
            if ($catItem['pid'] == 0) {
                $catItem['children'] = [];
                foreach ($allCategories as $sub) {
                    if ($sub['pid'] == $catItem['id']) {
                        $catItem['children'][] = $sub;
                    }
                }
                $categories[] = $catItem;
            }
        }

        // 鉴权逻辑：判断用户是否可以查看内容
        // 核心规则：未登录用户无法查看任何内容（包括免费）
        $sessionUser = \think\facade\Session::get('user');
        $canView = false;

        if ($sessionUser) {
            // 已登录用户
            if ($article->price == 0) {
                // 免费文章，登录即可看
                $canView = true;
            } else {
                // 付费文章，需检查购买记录或VIP
                $isBought = \app\model\Order::where('user_id', $sessionUser['id'])
                    ->where('article_id', $article->id)
                    ->where('status', 1)
                    ->find();
                
                // 检查VIP身份
                $currUser = \app\model\User::find($sessionUser['id']);
                $isVip = $currUser && $currUser->vip_level > 0;

                if ($isBought || $isVip || $article->author_id == $sessionUser['id']) {
                    $canView = true;
                }
            }
        }
        // 未登录用户，$canView 保持 false

        // 获取热门推荐
        $hotLimit = isset($site_config['hot_article_limit']) ? intval($site_config['hot_article_limit']) : 10;
        $hotArticles = \app\model\Article::order('views', 'desc')
            ->limit($hotLimit)
            ->select();

        // Get Related Articles
        $relatedArticles = ArticleModel::where('category_id', $article->category_id)
            ->where('id', '<>', $article->id)
            ->order('create_time', 'desc')
            ->limit(4) // Changed to 4 for the new grid layout
            ->select();

        // Previous/Next Article (Scoped to current category)
        $prevArticle = ArticleModel::where('category_id', $article->category_id)
            ->where('id', '<', $id)
            ->order('id', 'desc')
            ->find();
            
        $nextArticle = ArticleModel::where('category_id', $article->category_id)
            ->where('id', '>', $id)
            ->order('id', 'asc')
            ->find();

        // Get Comments (Server-side fetch for direct rendering)
        $limit = isset($site_config['comment_per_page']) ? (int)$site_config['comment_per_page'] : 10;
        $comments = \app\model\Comment::with(['user'])
            ->where('article_id', $article->id)
            ->where('status', 1)
            ->order('create_time', 'desc')
            ->paginate(['list_rows' => $limit > 0 ? $limit : 10, 'query' => request()->param()])
            ->fragment('content-comments');

        // 获取详情页广告
        $ads_pos2 = \app\model\Ad::where('position', 2)->where('status', 1)->order('sort', 'desc')->select();
        $ads_pos3 = \app\model\Ad::where('position', 3)->where('status', 1)->order('sort', 'desc')->select();
        $ads_pos4 = \app\model\Ad::where('position', 4)->where('status', 1)->order('sort', 'desc')->select();

        // Social Stats
        $likeCount = 0;
        $isLiked = false;
        try {
            $likeCount = \think\facade\Db::name('blog_like')->where('article_id', $article->id)->count();
            if ($sessionUser) {
                $isLiked = \think\facade\Db::name('blog_like')
                    ->where('user_id', $sessionUser['id'])
                    ->where('article_id', $article->id)
                    ->find();
            }
        } catch (\Exception $e) {
            $this->_createLikeTable();
        }

        $shareCount = 0;
        try {
            $shareCount = \think\facade\Db::name('blog_share')->where('article_id', $article->id)->count();
        } catch (\Exception $e) {
            $this->_createShareTable();
        }

        $collectCount = 0;
        $isCollected = false;
        try {
            $collectCount = \think\facade\Db::name('blog_favorite')->where('article_id', $article->id)->count();
            if ($sessionUser) {
                $isCollected = \think\facade\Db::name('blog_favorite')
                    ->where('user_id', $sessionUser['id'])
                    ->where('article_id', $article->id)
                    ->find();
            }
        } catch (\Exception $e) {
            $this->_createFavoriteTable();
        }

        // Author Statistics
        $authorId = isset($article->author_id) ? $article->author_id : 0;
        $authorArticlesCount = 0;
        $authorCommentsCount = 0;
        $authorFansCount = 0;

        if ($authorId > 0) {
            // Calculate actual stats for specific author
            $authorArticlesCount = \app\model\Article::where('author_id', $authorId)->where('status', 1)->count();
            
            $authorArticleIds = \app\model\Article::where('author_id', $authorId)->column('id');
            if (!empty($authorArticleIds)) {
                 $authorCommentsCount = \app\model\Comment::whereIn('article_id', $authorArticleIds)->where('status', 1)->count();
                 
                 try {
                     // Fans = unique users who have collected the author's articles
                     $authorFansCount = \think\facade\Db::name('blog_favorite')
                        ->whereIn('article_id', $authorArticleIds)
                        ->count('DISTINCT user_id');
                 } catch (\Exception $e) {
                     $authorFansCount = 0;
                 }
            }
        } else {
            // Default Admin Author: Show global stats with duplicate protection
            $authorArticlesCount = \app\model\Article::where('status', 1)->count();
            $authorCommentsCount = \app\model\Comment::where('status', 1)->count();
            try {
                // Unique users who favorited any article
                $authorFansCount = \think\facade\Db::name('blog_favorite')->count('DISTINCT user_id');
            } catch (\Exception $e) {
                $authorFansCount = 0;
            }
        }

        View::assign([
            'config'           => $site_config,
            'article'          => $article,
            'title'            => $article->title,
            'keywords'         => $article->keywords ?: $article->title,
            'description'      => $article->description ?: $article->title,
            'categories'       => $categories,
            'hot_articles'     => $hotArticles,
            'related_articles' => $relatedArticles,
            'comments'         => $comments,
            'ads_pos2'         => $ads_pos2,
            'ads_pos3'         => $ads_pos3,
            'ads_pos4'         => $ads_pos4,
            'user'             => $sessionUser,
            'can_view'         => $canView,
            'prev_article'     => $prevArticle,
            'next_article'     => $nextArticle,
            'like_count'       => $likeCount,
            'share_count'      => $shareCount,
            'collect_count'    => $collectCount,
            'is_liked'         => !empty($isLiked),
            'is_collected'     => !empty($isCollected),
            'author_articles_count' => $authorArticlesCount,
            'author_comments_count' => $authorCommentsCount,
            'author_fans_count'     => $authorFansCount
        ]);
        return view();
    }

    /**
     * 创建购买订单
     */
    public function buy()
    {
        $id = $this->request->post('id', 0);
        $user = \think\facade\Session::get('user');
        
        if (!$user) {
            return json(['code' => -1, 'msg' => '请先登录']);
        }

        $article = ArticleModel::where('id', $id)->find();
        if (!$article) return json(['code' => 0, 'msg' => '资源不存在']);

        // 检查是否已经购买过
        $bought = \app\model\Order::where('user_id', $user['id'])
            ->where('article_id', $id)
            ->where('status', 1)
            ->find();
        
        if ($bought) return json(['code' => 1, 'msg' => '您已购买过该资源', 'action' => 'reload']);

        // 创建订单
        $orderNo = 'O' . date('YmdHis') . rand(1000, 9999);
        $order = \app\model\Order::create([
            'order_no'   => $orderNo,
            'user_id'    => $user['id'],
            'article_id' => $id,
            'type'       => 1,
            'price'      => $article['price'],
            'status'     => 0,
            'create_time'=> time()
        ]);

        return json(['code' => 1, 'msg' => '订单创建成功', 'order_no' => $orderNo]);
    }

    /**
     * 资源下载
     */
    public function download()
    {
        $id = (int)$this->request->param('id', 0);
        $userSession = \think\facade\Session::get('user');
        
        if (!$userSession) {
            $this->error('请先登录', (string)url('user/login'));
        }
        
        $article = ArticleModel::find($id);
        if (!$article) {
            $this->error('资源不存在');
        }
        
        $user = \app\model\User::find($userSession['id']);
        if (!$user) {
            $this->error('用户异常，请重新登录');
        }

        // 如果资源链接本身为空，直接提示，不需要进行后面的权限验证
        if (empty($article->resource_url)) {
            $this->error('该文章暂无资源下载链接', (string)url('article/details', ['id' => $id]));
        }

        // 检查是否已购买
        $isBought = \app\model\Order::where('user_id', $user->id)
            ->where('article_id', $article->id)
            ->where('status', 1)
            ->find();
            
        // 检查VIP状态
        $isVip = $user->vip_level > 0 && $user->vip_expire_time > time();
        
        // 权限判断
        if ($article->price > 0 && !$isBought && !$isVip) {
             $this->error('请购买或开通VIP后观看/下载', (string)url('article/details', ['id' => $id]));
        }
        
        // 如果未购买（即使用VIP权限或免费资源），则检查下载限制
        if (!$isBought) {
            // 获取配置限制
            $limit = $isVip ? Config::getVal('vip_download_limit', 10) : Config::getVal('common_download_limit', 1);
            
            // 获取今日已下载总数
            $today = strtotime(date('Y-m-d'));
            $count = \think\facade\Db::name('blog_download_log')
                ->where('user_id', $user->id)
                ->where('create_time', '>=', $today)
                ->count();
            
            // 检查是否今日已下载过此资源 (重复下载不扣除次数)
            $hasDownloadedToday = \think\facade\Db::name('blog_download_log')
                ->where('user_id', $user->id)
                ->where('article_id', $article->id)
                ->where('create_time', '>=', $today)
                ->find();
                
            if (!$hasDownloadedToday && $count >= $limit) {
                $this->error('今日下载次数已达上限 (' . $count . '/' . $limit . ')');
            }
            
            // 记录日志 (仅首次下载扣除)
            if (!$hasDownloadedToday) {
                \think\facade\Db::name('blog_download_log')->insert([
                    'user_id'     => $user->id,
                    'article_id'  => $article->id,
                    'create_time' => time()
                ]);
            }
        }
        
        // 执行跳转下载
        return redirect($article->resource_url);
    }

    /**
     * 获取收藏状态
     */
    public function getFavoriteStatus()
    {
        $id = (int)$this->request->param('id', 0);
        $userSession = \think\facade\Session::get('user');
        
        if (!$userSession) {
            return json(['code' => 0, 'is_favorite' => false]);
        }
        
        try {
            $exists = \think\facade\Db::name('blog_favorite')
                ->where('user_id', $userSession['id'])
                ->where('article_id', $id)
                ->find();
                
            return json(['code' => 1, 'is_favorite' => !empty($exists)]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'is_favorite' => false]);
        }
    }

    /**
     * 切换收藏状态
     */
    public function toggleFavorite()
    {
        $id = (int)$this->request->param('id', 0);
        $userSession = \think\facade\Session::get('user');
        
        if (!$userSession) {
            return json(['code' => -1, 'msg' => '请先登录']);
        }
        
        $article = ArticleModel::find($id);
        if (!$article) {
             return json(['code' => 0, 'msg' => '资源不存在']);
        }
        
        try {
            return $this->_doToggleFavorite($userSession['id'], $id);
        } catch (\Exception $e) {
            // 如果表不存在，尝试创建
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                 $this->_createFavoriteTable();
                 try {
                     return $this->_doToggleFavorite($userSession['id'], $id);
                 } catch (\Exception $e2) {
                     return json(['code' => 0, 'msg' => '操作失败: ' . $e2->getMessage()]);
                 }
            }
            return json(['code' => 0, 'msg' => '操作失败']);
        }
    }

    private function _doToggleFavorite($userId, $articleId)
    {
        $exists = \think\facade\Db::name('blog_favorite')
            ->where('user_id', $userId)
            ->where('article_id', $articleId)
            ->find();
            
        if ($exists) {
            \think\facade\Db::name('blog_favorite')
                ->where('user_id', $userId)
                ->where('article_id', $articleId)
                ->delete();
            return json(['code' => 1, 'msg' => '已取消收藏', 'action' => 'remove']);
        } else {
            \think\facade\Db::name('blog_favorite')->insert([
                'user_id' => $userId,
                'article_id' => $articleId,
                'create_time' => time()
            ]);
            return json(['code' => 1, 'msg' => '收藏成功', 'action' => 'add']);
        }
    }

    private function _createFavoriteTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `blog_favorite` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL COMMENT '用户ID',
          `article_id` int(11) NOT NULL COMMENT '文章ID',
          `create_time` int(11) DEFAULT NULL COMMENT '创建时间',
          PRIMARY KEY (`id`),
          KEY `idx_user` (`user_id`),
          KEY `idx_article` (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户收藏表';";
        \think\facade\Db::execute($sql);
    }

    /**
     * Submit Comment
     */
    public function submitComment()
    {
        $articleId = $this->request->post('article_id');
        $content = $this->request->post('content');
        $userSession = \think\facade\Session::get('user');

        if (!$userSession) {
            return json(['code' => -1, 'msg' => '请先登录']);
        }
        if (empty($content)) {
            return json(['code' => 0, 'msg' => '评论内容不能为空']);
        }

        // 评论频率限制：同一用户60秒内只能评论一次
        $commentCacheKey = 'comment_limit_' . $userSession['id'];
        if (cache($commentCacheKey)) {
            return json(['code' => 0, 'msg' => '评论太频繁，请稍后再试']);
        }

        // XSS防护：HTML转义
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        // 敏感词过滤（使用缓存，避免每次全表查询）
        try {
            $words = cache('sensitive_words');
            if ($words === null) {
                $words = \think\facade\Db::name('blog_sensitive_words')->column('word');
                cache('sensitive_words', $words, 3600); // 缓存1小时
            }
            foreach ($words as $w) {
                if (strpos($content, $w) !== false) {
                    $content = str_replace($w, '**', $content);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            \app\model\Comment::create([
                'article_id' => $articleId,
                'user_id' => $userSession['id'],
                'content' => $content,
                'status' => 1,
                'create_time' => time()
            ]);
            cache($commentCacheKey, 1, 60); // 设置60秒冷却
            return json(['code' => 1, 'msg' => '评论成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '评论失败，请稍后再试']);
        }
    }

    /**
     * Get Comments List
     */
    public function getComments()
    {
        $articleId = $this->request->param('article_id');
        $page = $this->request->param('page', 1);
        
        $comments = \app\model\Comment::with(['user'])
            ->where('article_id', $articleId)
            ->where('status', 1)
            ->order('create_time', 'desc')
            ->paginate(['list_rows' => 5, 'page' => $page]);
            
        return json([
            'code' => 1,
            'data' => $comments->items(),
            'last_page' => $comments->lastPage(),
            'current_page' => $comments->currentPage()
        ]);
    }

    /**
     * Like Article
     */
    public function like()
    {
        $id = (int)$this->request->param('id', 0);
        $userSession = \think\facade\Session::get('user');
        
        if (!$userSession) {
            return json(['code' => -1, 'msg' => '请先登录']);
        }
        
        try {
            return $this->_doToggleLike($userSession['id'], $id);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                 $this->_createLikeTable();
                 try {
                     return $this->_doToggleLike($userSession['id'], $id);
                 } catch (\Exception $e2) {
                     return json(['code' => 0, 'msg' => '操作失败']);
                 }
            }
            return json(['code' => 0, 'msg' => '操作失败']);
        }
    }

    private function _doToggleLike($userId, $articleId)
    {
        $exists = \think\facade\Db::name('blog_like')
            ->where('user_id', $userId)
            ->where('article_id', $articleId)
            ->find();
            
        if ($exists) {
            \think\facade\Db::name('blog_like')
                ->where('user_id', $userId)
                ->where('article_id', $articleId)
                ->delete();
            return json(['code' => 1, 'msg' => '已取消点赞', 'action' => 'remove']);
        } else {
            \think\facade\Db::name('blog_like')->insert([
                'user_id' => $userId,
                'article_id' => $articleId,
                'create_time' => time()
            ]);
            return json(['code' => 1, 'msg' => '点赞成功', 'action' => 'add']);
        }
    }

    private function _createLikeTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `blog_like` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL COMMENT '用户ID',
          `article_id` int(11) NOT NULL COMMENT '文章ID',
          `create_time` int(11) DEFAULT NULL COMMENT '创建时间',
          PRIMARY KEY (`id`),
          KEY `idx_user` (`user_id`),
          KEY `idx_article` (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户点赞表';";
        \think\facade\Db::execute($sql);
    }

    /**
     * Share Article (Log share)
     */
    public function share()
    {
        $id = (int)$this->request->param('id', 0);
        // Usually share actions are hard to verify, so we just log it.
        // We can create a table if we want accurate counts, or just fake it/use generic logic.
        // For "Real Data" request, let's create a simple log table.
        try {
            return $this->_doShare($id);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                 $this->_createShareTable();
                 try {
                     return $this->_doShare($id);
                 } catch (\Exception $e2) {
                     return json(['code' => 0]);
                 }
            }
            return json(['code' => 0]);
        }
    }

    private function _doShare($articleId)
    {
        // For share, we just insert a record. No unique check per user to allow multiple shares?
        // Let's just log it.
        $ip = $this->request->ip();
        \think\facade\Db::name('blog_share')->insert([
            'user_id' => 0, // Guest or Logged in, doesn't matter much for share count usually
            'article_id' => $articleId,
            'ip' => $ip,
            'create_time' => time()
        ]);
        return json(['code' => 1, 'msg' => '分享成功']);
    }

    private function _createShareTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `blog_share` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) DEFAULT 0,
          `article_id` int(11) NOT NULL,
          `ip` varchar(50) DEFAULT '',
          `create_time` int(11) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_article` (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        \think\facade\Db::execute($sql);
    }
}
