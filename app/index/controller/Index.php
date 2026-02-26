<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;
use app\model\Config;
use think\facade\View;
use think\facade\Session;

/**
 * 前台主页
 */
class Index extends BaseController
{


    public function index()
    {
        $site_config = Config::getAll();
        if (!isset($site_config['article_default_image'])) {
            $site_config['article_default_image'] = '/static/images/default.jpg';
        }

        // 获取最新文章
        $catId = (int)$this->request->param('cat', 0);
        $keyword = $this->request->param('keyword', '');
        
        $query = \app\model\Article::with(['category']);
        
        // 关键词搜索
        if (!empty($keyword)) {
            $query->where('title|content', 'like', "%{$keyword}%");
        }
        
        if ($catId > 0) {
            // 获取该分类及其所有子分类ID
            $catIds = [$catId];
            $children = \app\model\Category::where('pid', $catId)->column('id');
            if (!empty($children)) {
                $catIds = array_merge($catIds, $children);
            }
            $query->whereIn('category_id', $catIds);
        }

        $currentCategory = null;
        if ($catId > 0) {
            $currentCategory = \app\model\Category::find($catId);
        }

        $articleLimit = isset($site_config['index_article_limit']) ? intval($site_config['index_article_limit']) : 12;
        $articles = $query->order('create_time', 'desc')
            ->limit($articleLimit)
            ->select();

        // 获取分类并构建树形结构
        $allCategories = \app\model\Category::withCount(['articles'])
            ->order('sort', 'desc')
            ->select()
            ->toArray();
        
        $categories = [];
        foreach ($allCategories as $catItem) {
            if ($catItem['pid'] == 0) {
                // 计算顶级分类的总数（包含子分类）
                $totalCount = $catItem['articles_count'];
                $catItem['children'] = [];
                foreach ($allCategories as $sub) {
                    if ($sub['pid'] == $catItem['id']) {
                        $catItem['children'][] = $sub;
                        $totalCount += $sub['articles_count'];
                    }
                }
                $catItem['total_articles'] = $totalCount;
                $categories[] = $catItem;
            }
        }

        // 获取最新订单作为网站动态
        $realOrders = \app\model\Order::with(['user', 'article'])
            ->where('status', 1) // 已支付
            ->order('create_time', 'desc')
            ->limit(10)
            ->select();
        
        $activities = [];
        foreach ($realOrders as $order) {
            if ($order->user && $order->article) {
                // 处理用户昵称隐私
                $nickname = $order->user->nickname ?: $order->user->username;
                if (mb_strlen($nickname) > 2) {
                    $nickname = mb_substr($nickname, 0, 1) . '**' . mb_substr($nickname, -1);
                } else {
                    $nickname = mb_substr($nickname, 0, 1) . '**';
                }
                
                $activities[] = [
                    'user'   => $nickname,
                    'action' => '购买了 ' . $order->article->title,
                    'time'   => $this->formatTime((int)$order->create_time)
                ];
            }
        }

        // 如果订单不足，取一些最新的文章浏览数据补充 (使用真实用户填充以增强真实感)
        if (count($activities) < 5) {
            $recentViews = \think\facade\Db::name('blog_article_views')
                ->order('create_time', 'desc')
                ->limit(10 - count($activities))
                ->select();
            
            // 获取一些真实用户用于补充
            $randomUsers = \app\model\User::orderRaw('rand()')->limit(5)->select();
            $userIndex = 0;

            foreach ($recentViews as $v) {
                $art = \app\model\Article::find($v['article_id']);
                if ($art) {
                    // 尝试使用真实用户，如果没有则用 游客
                    $nickname = '游客';
                    if (!$randomUsers->isEmpty()) {
                        $u = $randomUsers[$userIndex % count($randomUsers)];
                        $nickname = $u->nickname ?: $u->username;
                        $userIndex++;
                    }

                    // 隐私处理
                    if (mb_strlen($nickname) > 2) {
                        $nickname = mb_substr($nickname, 0, 1) . '**' . mb_substr($nickname, -1);
                    } else if (mb_strlen($nickname) > 1) {
                        $nickname = mb_substr($nickname, 0, 1) . '**';
                    }

                    $activities[] = [
                        'user'   => $nickname,
                        'action' => '查看了 ' . $art->title,
                        'time'   => $this->formatTime((int)$v['create_time'])
                    ];
                }
            }
        }

        // 获取热门推荐 (优先设定热门，其次点击量)
        $hotLimit = isset($site_config['hot_article_limit']) ? intval($site_config['hot_article_limit']) : 10;
        $hotArticles = \app\model\Article::order('views', 'desc')
            ->limit($hotLimit)
            ->select();

        // 获取每个分类下的最新 4 篇文章用于首页板块展示
        foreach ($categories as &$cat) {
            $catIds = [$cat['id']];
            if (isset($cat['children']) && !empty($cat['children'])) {
                foreach ($cat['children'] as $sub) {
                    $catIds[] = $sub['id'];
                }
            }
            
            $cat['latest_articles'] = \app\model\Article::whereIn('category_id', $catIds)
                ->order('create_time', 'desc')
                ->limit(4)
                ->select();
        }

        // 获取位置 1 的广告 (加载更多下方)
        $ads_pos1 = \app\model\Ad::where('position', 1)->where('status', 1)->order('sort', 'desc')->select();

        // 获取已通过的友情链接
        $oneMonthAgo = time() - 30 * 86400;
        $friendLinks = \think\facade\Db::name('blog_friend_link')
            ->where('status', 1)
            ->whereRaw('(is_paid = 0 OR (is_paid = 1 AND create_time >= ' . $oneMonthAgo . '))')
            ->order('sort', 'asc')
            ->order('create_time', 'desc')
            ->select();


        View::assign([
            'config'       => $site_config,
            'articles'     => $articles,
            'categories'   => $categories,
            'hot_articles' => $hotArticles,
            'activities'   => $activities,
            'ads_pos1'     => $ads_pos1,
            'friendLinks'  => $friendLinks,
            'user'         => Session::get('user'),
            'catId'        => $catId,
            'currentCategory' => $currentCategory
        ]);
        return view();
    }

    public function search()
    {
        $site_config = Config::getAll();
        if (!isset($site_config['article_default_image'])) {
            $site_config['article_default_image'] = '/static/images/default.jpg';
        }

        $keyword = $this->request->param('keyword', '');
        $catId = (int)$this->request->param('cat', 0);
        
        $query = \app\model\Article::with(['category']);
        
        // 关键词搜索
        if (!empty($keyword)) {
            $query->where('title|content', 'like', "%{$keyword}%");
        }
        
        if ($catId > 0) {
            // 获取该分类及其所有子分类ID
            $catIds = [$catId];
            $children = \app\model\Category::where('pid', $catId)->column('id');
            if (!empty($children)) {
                $catIds = array_merge($catIds, $children);
            }
            $query->whereIn('category_id', $catIds);
        }

        // 分页获取搜索结果
        $limit = isset($site_config['index_article_limit']) ? intval($site_config['index_article_limit']) : 12;
        $articles = $query->order('create_time', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'query' => request()->param()
            ]);

        // 获取分类并构建树形结构用于侧边栏或导航栏
        $allCategories = \app\model\Category::withCount(['articles'])
            ->order('sort', 'desc')
            ->select()
            ->toArray();
        
        $categories = [];
        foreach ($allCategories as $catItem) {
            if ($catItem['pid'] == 0) {
                // 计算顶级分类的总数（包含子分类）
                $totalCount = $catItem['articles_count'];
                $catItem['children'] = [];
                foreach ($allCategories as $sub) {
                    if ($sub['pid'] == $catItem['id']) {
                        $catItem['children'][] = $sub;
                        $totalCount += $sub['articles_count'];
                    }
                }
                $catItem['total_articles'] = $totalCount;
                $categories[] = $catItem;
            }
        }

        // 网站动态(侧边栏展示)
        $activities = [];
        $realOrders = \app\model\Order::with(['user', 'article'])
            ->where('status', 1) // 已支付
            ->order('create_time', 'desc')
            ->limit(10)
            ->select();
        foreach ($realOrders as $order) {
            if ($order->user && $order->article) {
                $nickname = $order->user->nickname ?: $order->user->username;
                if (mb_strlen($nickname) > 2) {
                    $nickname = mb_substr($nickname, 0, 1) . '**' . mb_substr($nickname, -1);
                } else {
                    $nickname = mb_substr($nickname, 0, 1) . '**';
                }
                $activities[] = [
                    'user'   => $nickname,
                    'action' => '购买了 ' . $order->article->title,
                    'time'   => $this->formatTime((int)$order->create_time)
                ];
            }
        }
        if (count($activities) < 5) {
            $recentViews = \think\facade\Db::name('blog_article_views')
                ->order('create_time', 'desc')
                ->limit(10 - count($activities))
                ->select();
            $randomUsers = \app\model\User::orderRaw('rand()')->limit(5)->select();
            $userIndex = 0;
            foreach ($recentViews as $v) {
                $art = \app\model\Article::find($v['article_id']);
                if ($art) {
                    $nickname = '游客';
                    if (!$randomUsers->isEmpty()) {
                        $u = $randomUsers[$userIndex % count($randomUsers)];
                        $nickname = $u->nickname ?: $u->username;
                        $userIndex++;
                    }
                    if (mb_strlen($nickname) > 2) {
                        $nickname = mb_substr($nickname, 0, 1) . '**' . mb_substr($nickname, -1);
                    } else if (mb_strlen($nickname) > 1) {
                        $nickname = mb_substr($nickname, 0, 1) . '**';
                    }
                    $activities[] = [
                        'user'   => $nickname,
                        'action' => '查看了 ' . $art->title,
                        'time'   => $this->formatTime((int)$v['create_time'])
                    ];
                }
            }
        }

        View::assign([
            'config'       => $site_config,
            'articles'     => $articles,
            'categories'   => $categories,
            'keyword'      => $keyword,
            'catId'        => $catId,
            'activities'   => $activities,
            'user'         => Session::get('user')
        ]);
        
        return view('search');
    }

    /**
     * 简单的友好时间格式化
     */
    private function formatTime(int $timestamp)
    {
        if ($timestamp <= 0) return '刚刚';
        $diff = time() - $timestamp;
        if ($diff < 1) return '刚刚';
        if ($diff < 60) return $diff . ' 秒前';
        if ($diff < 3600) return floor($diff / 60) . ' 分钟前';
        if ($diff < 86400) return floor($diff / 3600) . ' 小时前';
        if ($diff < 2592000) return floor($diff / 86400) . ' 天前';
        return date('Y-m-d', $timestamp);
    }
}
