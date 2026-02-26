<?php
declare(strict_types=1);

namespace app\service;

use app\model\CollectNode;
use app\model\CollectDraft;
use app\model\Article;
use QL\QueryList;
use think\facade\Db;

class CollectService
{
    /**
     * 测试单页采集配置提取
     */
    public function testCollect(int $nodeId, string $testUrl)
    {
        $node = CollectNode::find($nodeId);
        if (!$node) {
            throw new \Exception('采集节点不存在');
        }

        try {
            $ql = QueryList::get($testUrl, [], [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'
                ],
                'timeout' => 15
            ]);
            
            // Handle charset
            $charset = strtolower($node->charset);
            if ($charset != 'utf-8' && $charset != 'utf8') {
                $ql->encoding('UTF-8', strtoupper($charset));
            }

            $rules = [
                'title' => [$node->title_selector, 'text'],
                'content' => [$node->content_selector, 'html']
            ];
            
            if (!empty($node->download_selector)) {
                $rules['download_url'] = [$node->download_selector, 'href'];
            }

            $data = $ql->rules($rules)->query()->getData();
            $result = $data->all();

            $title = $result[0]['title'] ?? '';
            $content = $result[0]['content'] ?? '';
            $downloadUrl = $result[0]['download_url'] ?? '';
            
            if (empty($title) && empty($content)) {
                // Re-evaluate counts for the error message if needed, or simplify
                $titleCount = $ql->find($node->title_selector)->count();
                $contentCount = $ql->find($node->content_selector)->count();
                return ['success' => false, 'msg' => "无法匹配到数据。标题选择器「{$node->title_selector}」匹配到 {$titleCount} 个元素，内容选择器「{$node->content_selector}」匹配到 {$contentCount} 个元素。请检查选择器是否正确（注意：h1 是标签选择器，.h1 是class选择器，两者不同）"];
            }

            // Apply filter words
            if (!empty($node->filter_words)) {
                $content = $this->filterContent($content, $node->filter_words);
            }
            
            return ['success' => true, 'data' => ['title' => $title, 'content' => $content]];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => '请求失败：' . $e->getMessage()];
        }
    }

    /**
     * 测试列表采集配置提取
     */
    public function testCollectList(int $nodeId, string $testUrl)
    {
        $node = CollectNode::find($nodeId);
        if (!$node) {
            throw new \Exception('采集节点不存在');
        }

        try {
            $ql = QueryList::get($testUrl);
            
            $charset = strtolower($node->charset);
            if ($charset != 'utf-8' && $charset != 'utf8') {
                $ql->encoding('UTF-8', strtoupper($charset));
            }

            $elements = $ql->find($node->list_selector);
            $count = $elements->count();
            
            if ($count == 0) {
                return ['success' => false, 'msg' => "列表选择器「{$node->list_selector}」未匹配到任何元素，请检查选择器"];
            }

            $result = [];
            $elements->map(function ($item) use (&$result) {
                $href = $item->attr('href');
                if (!empty($href)) {
                    $result[] = ['link' => $href];
                }
            });
            
            if (empty($result)) {
                return ['success' => false, 'msg' => "找到 {$count} 个元素但未提取到链接，请确认选择器指向的是 a 标签"];
            }
            
            return ['success' => true, 'data' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => '请求失败：' . $e->getMessage()];
        }
    }

    /**
     * 检查链接是否已存在（草稿表 + 文章表）
     */
    protected function isUrlExists(string $url): string
    {
        $inArticle = Db::name('blog_article')->where('resource_url', $url)->find();
        if ($inArticle) return 'article';
        $inDraft = Db::name('blog_collect_draft')->where('resource_url', $url)->where('status', '<>', 2)->find();
        if ($inDraft) return 'draft';
        return '';
    }

    /**
     * 提取内容中的下载链接
     */
    protected function extractDownloadLinks(string $content): array
    {
        $downloadUrl = null;
        $isPaid = false;

        // Match <a> tags containing download-related text
        $keywords = ['下载', 'download', 'Download'];
        preg_match_all('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $m) {
            $href = $m[1];
            $text = strip_tags($m[2]);
            $found = false;
            foreach ($keywords as $kw) {
                if (mb_stripos($text, $kw) !== false) {
                    $found = true;
                    break;
                }
            }
            if ($found && !empty($href) && $href !== '#' && $href !== 'javascript:;') {
                $downloadUrl = $href;
                // Detect paid download indicators
                $paidKeywords = ['付费', '收费', 'vip', 'VIP', '会员', '购买', 'pay', '积分'];
                foreach ($paidKeywords as $pk) {
                    if (mb_stripos($text, $pk) !== false || mb_stripos($href, $pk) !== false) {
                        $isPaid = true;
                        break;
                    }
                }
                break;
            }
        }

        return ['url' => $downloadUrl, 'is_paid' => $isPaid];
    }

    /**
     * 执行采集（写入草稿表）
     */
    public function executeCollect(int $nodeId, int $pages = 1)
    {
        $node = CollectNode::find($nodeId);
        if (!$node) {
            throw new \Exception('采集节点不存在');
        }

        $successCount = 0;
        $failCount = 0;
        $skipCount = 0;
        $delay = max(1, (int)($node->request_delay ?: 2));

        for ($i = 1; $i <= $pages; $i++) {
            $listUrl = str_replace('[page]', (string)$i, $node->url);
            
            $listRes = $this->testCollectList($nodeId, $listUrl);
            if (!$listRes['success']) {
                $failCount++;
                continue;
            }

            $links = array_column($listRes['data'], 'link');
            $links = array_filter(array_unique($links));

            $parsedUrl = parse_url($listUrl);
            $baseDomain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

            foreach ($links as $link) {
                if (strpos($link, 'http') !== 0) {
                    if (strpos($link, '/') === 0) {
                        $link = $baseDomain . $link;
                    } else {
                        $link = rtrim($listUrl, '/') . '/' . ltrim($link, '/');
                    }
                }

                $existsIn = $this->isUrlExists($link);
                if ($existsIn) {
                    $skipCount++;
                    continue;
                }

                $detailRes = $this->testCollect($nodeId, $link);
                if (!$detailRes['success'] || empty($detailRes['data']['title'])) {
                    $failCount++;
                    continue;
                }

                $title = trim($detailRes['data']['title']);
                $content = $detailRes['data']['content'];
                // 如果配置了下载选择器，则优先用它解析的内容；否则使用智能识别
                if (!empty($node->download_selector) && !empty($detailRes['data']['download_url'])) {
                    $dlInfo = ['url' => $detailRes['data']['download_url'], 'is_paid' => false];
                } else {
                    $dlInfo = $this->extractDownloadLinks($content);
                }

                if ($node->image_download) {
                    $content = $this->downloadImages($content);
                }

                try {
                    Db::name('blog_collect_draft')->insert([
                        'node_id' => $nodeId,
                        'title' => $title,
                        'content' => $content,
                        'resource_url' => $link,
                        'download_url' => $dlInfo['url'],
                        'is_paid_download' => $dlInfo['is_paid'] ? 1 : 0,
                        'price' => $node->price ?? 0.00,
                        'status' => 0,
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                }
                
                sleep($delay);
            }
        }

        return ['successCount' => $successCount, 'failCount' => $failCount, 'skipCount' => $skipCount];
    }

    /**
     * SSE 流式执行采集（实时进度反馈）
     */
    public function executeCollectStream(int $nodeId, int $pages = 1)
    {
        $node = CollectNode::find($nodeId);
        if (!$node) {
            throw new \Exception('采集节点不存在');
        }

        $delay = max(1, (int)($node->request_delay ?: 2));
        $successCount = 0;
        $failCount = 0;
        $skipCount = 0;

        $this->sendSSE('start', ['msg' => "开始采集，共 {$pages} 页，请求间隔 {$delay} 秒"]);

        for ($i = 1; $i <= $pages; $i++) {
            $listUrl = str_replace('[page]', (string)$i, $node->url);
            $this->sendSSE('page', ['page' => $i, 'totalPages' => $pages, 'msg' => "正在抓取第 {$i}/{$pages} 页列表..."]);

            $listRes = $this->testCollectList($nodeId, $listUrl);
            if (!$listRes['success']) {
                $this->sendSSE('error', ['msg' => "第 {$i} 页列表抓取失败：" . $listRes['msg']]);
                $failCount++;
                continue;
            }

            $links = array_column($listRes['data'], 'link');
            $links = array_filter(array_unique($links));
            $totalLinks = count($links);
            $this->sendSSE('page_links', ['page' => $i, 'count' => $totalLinks, 'msg' => "第 {$i} 页找到 {$totalLinks} 条链接"]);

            $parsedUrl = parse_url($listUrl);
            $baseDomain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $linkIndex = 0;

            foreach ($links as $link) {
                $linkIndex++;
                if (strpos($link, 'http') !== 0) {
                    if (strpos($link, '/') === 0) {
                        $link = $baseDomain . $link;
                    } else {
                        $link = rtrim($listUrl, '/') . '/' . ltrim($link, '/');
                    }
                }

                $existsIn = $this->isUrlExists($link);
                if ($existsIn) {
                    $skipCount++;
                    $where = $existsIn === 'article' ? '文章表' : '草稿表';
                    $this->sendSSE('skip', [
                        'page' => $i, 'index' => $linkIndex, 'total' => $totalLinks,
                        'success' => $successCount, 'fail' => $failCount, 'skip' => $skipCount,
                        'msg' => "[{$linkIndex}/{$totalLinks}] 已存在于{$where}，跳过"
                    ]);
                    continue;
                }

                $this->sendSSE('fetching', [
                    'page' => $i, 'index' => $linkIndex, 'total' => $totalLinks,
                    'msg' => "[{$linkIndex}/{$totalLinks}] 正在抓取详情..."
                ]);

                $detailRes = $this->testCollect($nodeId, $link);
                if (!$detailRes['success'] || empty($detailRes['data']['title'])) {
                    $failCount++;
                    $this->sendSSE('fail', [
                        'page' => $i, 'index' => $linkIndex, 'total' => $totalLinks,
                        'success' => $successCount, 'fail' => $failCount, 'skip' => $skipCount,
                        'msg' => "[{$linkIndex}/{$totalLinks}] 详情抓取失败"
                    ]);
                    sleep($delay);
                    continue;
                }

                $title = trim($detailRes['data']['title']);
                $content = $detailRes['data']['content'];
                // 如果配置了下载选择器，则优先用它解析的内容；否则使用智能识别
                if (!empty($node->download_selector) && !empty($detailRes['data']['download_url'])) {
                    $dlInfo = ['url' => $detailRes['data']['download_url'], 'is_paid' => false];
                } else {
                    $dlInfo = $this->extractDownloadLinks($content);
                }

                if ($node->image_download) {
                    $content = $this->downloadImages($content);
                }

                // Report paid download detection
                if ($dlInfo['is_paid']) {
                    $this->sendSSE('warning', [
                        'page' => $i, 'index' => $linkIndex, 'total' => $totalLinks,
                        'msg' => "[{$linkIndex}/{$totalLinks}] ⚠️ 检测到收费下载链接: {$dlInfo['url']}"
                    ]);
                } elseif ($dlInfo['url']) {
                    $this->sendSSE('info', [
                        'page' => $i, 'index' => $linkIndex, 'total' => $totalLinks,
                        'msg' => "[{$linkIndex}/{$totalLinks}] 📥 发现下载链接: {$dlInfo['url']}"
                    ]);
                }

                try {
                    Db::name('blog_collect_draft')->insert([
                        'node_id' => $nodeId,
                        'title' => $title,
                        'content' => $content,
                        'resource_url' => $link,
                        'download_url' => $dlInfo['url'],
                        'is_paid_download' => $dlInfo['is_paid'] ? 1 : 0,
                        'price' => $node->price ?? 0.00,
                        'status' => 0,
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                    $successCount++;
                    $this->sendSSE('success', [
                        'page' => $i, 'index' => $linkIndex, 'total' => $totalLinks,
                        'title' => $title,
                        'success' => $successCount, 'fail' => $failCount, 'skip' => $skipCount,
                        'msg' => "[{$linkIndex}/{$totalLinks}] ✓ {$title}" . ($dlInfo['is_paid'] ? ' [收费下载]' : '')
                    ]);
                } catch (\Exception $e) {
                    $failCount++;
                    $this->sendSSE('fail', [
                        'page' => $i, 'index' => $linkIndex, 'total' => $totalLinks,
                        'success' => $successCount, 'fail' => $failCount, 'skip' => $skipCount,
                        'msg' => "[{$linkIndex}/{$totalLinks}] 入库失败：" . $e->getMessage()
                    ]);
                }

                sleep($delay);
            }
        }

        $this->sendSSE('done', [
            'success' => $successCount, 'fail' => $failCount, 'skip' => $skipCount,
            'msg' => "采集完成！成功 {$successCount} 篇（进入草稿），失败 {$failCount} 篇，跳过 {$skipCount} 篇。请到草稿管理中审核入库。"
        ]);
    }

    /**
     * 发送 SSE 事件
     */
    protected function sendSSE(string $event, array $data)
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * 过滤内容中的屏蔽词元素
     */
    protected function filterContent(string $content, string $filterWords): string
    {
        $words = array_filter(array_map('trim', explode("\n", $filterWords)));
        if (empty($words)) {
            return $content;
        }

        // Use DOMDocument to remove elements containing filter words
        $dom = new \DOMDocument();
        @$dom->loadHTML('<meta charset="utf-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new \DOMXPath($dom);
        $nodesToRemove = [];

        foreach ($words as $word) {
            // Find all elements containing the filter word in their text
            $nodes = $xpath->query("//*[contains(text(), '{$word}')]");
            foreach ($nodes as $node) {
                $nodesToRemove[] = $node;
            }
        }

        foreach ($nodesToRemove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        $result = $dom->saveHTML();
        // Remove the meta charset tag we added
        $result = preg_replace('/<meta charset="utf-8">/', '', $result, 1);
        return trim($result);
    }

    /**
     * 自动下载并替换文章中的图片
     */
    protected function downloadImages(string $content)
    {
        preg_match_all("/<img.*?src=[\"|\']?(.*?)[\"|\']?\s.*?>/i", $content, $matches);
        if (empty($matches[1])) {
            return $content;
        }

        $uploadDir = root_path() . 'public/storage/topic/' . date('Ymd') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $search = [];
        $replace = [];

        foreach (array_unique($matches[1]) as $imgUrl) {
            
            if (strpos($imgUrl, 'http') !== 0 && strpos($imgUrl, '//') !== 0) {
                // relative paths are tricky to complete without base URL, just skip for simplicity
                continue; 
            }
            if (strpos($imgUrl, '//') === 0) {
               $imgUrl = 'http:' . $imgUrl;
            }

            try {
                $imageData = @file_get_contents($imgUrl);
                if ($imageData) {
                    $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
                    $ext = $ext ?: 'jpg'; // fallback
                    // limit length to avoid crazy long query strings
                    if (strlen($ext) > 4) $ext = 'jpg'; 

                    $filename = md5(uniqid('', true)) . '.' . $ext;
                    $savePath = $uploadDir . $filename;
                    
                    file_put_contents($savePath, $imageData);
                    
                    $localUrl = '/storage/topic/' . date('Ymd') . '/' . $filename;
                    $search[] = $imgUrl;
                    $replace[] = $localUrl;
                }
            } catch (\Exception $e) {
                // ignore image download errors
            }
        }

        return str_replace($search, $replace, $content);
    }
}
