<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;
use app\model\User as UserModel;
use app\model\Config;
use think\facade\Session;
use think\facade\View;
use think\facade\Db;

/**
 * 鍓嶅彴鐢ㄦ埛涓績涓庨壌鏉?
 */
class User extends BaseController
{
    /**
     * 申请友链页面
     */
    public function applyFriend()
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return redirect((string) url('user/login'));
        }
        
        $id = $this->request->param('id/d', 0);
        $link = null;
        if ($id > 0) {
            $link = \think\facade\Db::name('blog_friend_link')
                ->where('id', $id)
                ->where('user_id', $sessionUser['id'])
                ->find();
        }

        return view('apply_friend', [
            'config' => Config::getAll(),
            'link'   => $link
        ]);
    }

    /**
     * 个人中心
     */
    public function index()
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return redirect((string) url('user/login'));
        }

        $user = UserModel::find($sessionUser['id']);
        if (!$user) {
            Session::delete('user');
            return redirect((string) url('user/login'));
        }

        // 鑷姩鍏抽棴瓒呮椂鏈敮浠樿鍗?(15鍒嗛挓 = 900绉?
        \think\facade\Db::name('blog_order')
            ->where('user_id', $user->id)
            ->where('status', 0)
            ->where('create_time', '<', time() - 900)
            ->update(['status' => 2]);
            
        // 妫€鏌?VIP 鏄惁杩囨湡
        if ($user->vip_level > 0 && $user->vip_expire_time < time()) {
            $user->vip_level = 0;
            $user->save();
        }

        $tab = $this->request->get('tab', 'index');
        $tab = str_replace('.html', '', (string)$tab);
        $data = [];

        switch ($tab) {
            case 'orders':
                $data = \think\facade\Db::name('blog_order')
                    ->alias('o')
                    ->join('blog_article a', 'o.article_id = a.id', 'LEFT')
                    ->where('o.user_id', $user->id)
                    ->field('o.*, a.title')
                    ->order('o.create_time', 'desc')
                    ->paginate(10)
                    ->appends(['tab' => 'orders'])
                    ->each(function($item) {
                        $item['is_expired'] = \app\model\Config::isOrderExpired($item);
                        // Calculate expire_seconds for countdown
                        $createTime = $item['create_time'] ?? 0;
                        if (is_string($createTime) && !is_numeric($createTime)) {
                            $createTime = strtotime($createTime);
                        }
                        $expireSetting = (int) \app\model\Config::getVal('order_expiration_time', 30);
                        if ($expireSetting > 0 && $createTime > 0) {
                            $remaining = ((int)$createTime + ($expireSetting * 60)) - time();
                            $item['expire_seconds'] = $remaining > 0 ? $remaining : 0;
                        } else {
                            $item['expire_seconds'] = 0;
                        }
                        return $item;
                    });
                break;
            case 'downloads':
                $data = \think\facade\Db::name('blog_download_log')
                    ->alias('d')
                    ->join('blog_article a', 'd.article_id = a.id')
                    ->where('d.user_id', $user->id)
                    ->field('d.*, a.title')
                    ->order('d.create_time', 'desc')
                    ->paginate(10)
                    ->appends(['tab' => 'downloads']);
                break;
            case 'favorites':
                $data = \think\facade\Db::name('blog_favorite')
                    ->alias('f')
                    ->join('blog_article a', 'f.article_id = a.id')
                    ->where('f.user_id', $user->id)
                                ->field('f.*, a.title, a.thumbnail')
                    ->order('f.create_time', 'desc')
                    ->paginate(10)
                    ->appends(['tab' => 'favorites']);
                break;
            case 'tickets':
                $data = \think\facade\Db::name('blog_ticket')
                    ->where('user_id', $user->id)
                    ->order('create_time', 'desc')
                    ->paginate(10)
                    ->appends(['tab' => 'tickets']);
                break;
            case 'contributions':
                $data = \think\facade\Db::name('blog_contribution')
                    ->where('user_id', $user->id)
                    ->order('create_time', 'desc')
                    ->paginate(10)
                    ->appends(['tab' => 'contributions']);
                break;
            case 'friend_links':
                $data = \think\facade\Db::name('blog_friend_link')
                    ->where('user_id', $user->id)
                    ->order('create_time', 'desc')
                    ->paginate(10)
                    ->appends(['tab' => 'friend_links'])
                    ->each(function($item) {
                    $item['is_expired'] = false;
                    $time = is_numeric($item['create_time']) ? $item['create_time'] : strtotime($item['create_time']);
                    if ($item['is_paid'] == 1 && $item['status'] == 1) {
                        $expire_time = $time + 30 * 86400;
                        $item['expire_date'] = date('Y-m-d', $expire_time);
                        if (time() > $expire_time) {
                            $item['is_expired'] = true;
                        }
                    } else {
                        $item['expire_date'] = '长期有效';
                    }
                    return $item;
                });
                break;
            case 'promotion':
                // 鏆傛椂杩斿洖绌猴紝鍚庣画鍙互鎵╁睍鎺ㄥ箍閫昏緫
                $data = [];
                break;
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

        // Check In Status
        $today = strtotime(date('Y-m-d'));
        $has_checkin = \think\facade\Db::name('blog_signin_log')
            ->where('user_id', $user['id'])
            ->where('create_time', '>=', $today)
            ->count() > 0;

        $vipLevels = \app\model\VipLevel::order('price', 'asc')->select();
        
        // 鑾峰彇涓嬭浇闄愬埗閰嶇疆
        $commonLimit = Config::getVal('common_download_limit', 1);
        $vipLimit    = Config::getVal('vip_download_limit', 10);
        
        // 鑾峰彇浠婃棩宸蹭笅杞芥鏁?
        $todayTimestamp = strtotime(date('Y-m-d'));
        $todayDownloadCount = \think\facade\Db::name('blog_download_log')
            ->where('user_id', $user->id)
            ->where('create_time', '>=', $todayTimestamp)
            ->count();

        $viewData = [
            'user'        => $user,
            'tab'         => $tab,
            'list'        => $data,
            'categories'  => $categories,
            'vipLevels'   => $vipLevels,
            'has_checkin' => $has_checkin,
            'config'      => $site_config,
            'common_limit'=> $commonLimit,
            'vip_limit'   => $vipLimit,
            'today_download_count' => $todayDownloadCount
        ];

        if ($this->request->isMobile() || $this->request->param('mobile') == 1) {
            return view('m_index', $viewData);
        }

        return view('index', $viewData);
    }

    /**
     * 修改资料
     */
    public function profile()
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            
            // 解决找不到UserModel的报错 (应为 \app\model\User)
            $user = \app\model\User::find($sessionUser['id']);
            
            if (!$user) return json(['code' => 0, 'msg' => '用户不存在']);

            // 只有传输了值才修改
            if (!empty($data['nickname'])) {
                $user->nickname = $data['nickname'];
            }
            if (!empty($data['email'])) {
                $user->email = $data['email'];
            }
            if (!empty($data['password'])) {
                $user->password = $data['password']; // 模型 setPasswordAttr 会自动哈希
            }

            $user->save();
            
            // 更新当前 Session 数据
            Session::set('user', $user->toArray());
            
            return json(['code' => 1, 'msg' => '个人资料已更新']);
        }
        
        return json(['code' => 0, 'msg' => '请求非法']);
    }

    /**
     * 登录页面
     */
    public function login()
    {
        if (Session::has('user')) {
             return redirect((string) url('index/index'));
        }
        $site_config = Config::getAll();
        return view('login', ['config' => $site_config]);
    }

    /**
     * 注册页面
     */
    public function register()
    {
        $site_config = Config::getAll();
        return view('register', ['config' => $site_config]);
    }

    /**
     * 登录处理
     */
    public function doLogin()
    {
        $params = $this->request->post();
        $username = $params['username'] ?? '';
        $password = $params['password'] ?? '';

        if (empty($username) || empty($password)) {
            return json(['code' => 0, 'msg' => '请输入账号密码']);
        }

        // 登录速率限制：同一IP 15分钟内最多5次失败
        $ip = $this->request->ip();
        $cacheKey = 'login_fail_' . md5($ip . $username);
        $failCount = cache($cacheKey) ?: 0;
        if ($failCount >= 5) {
            return json(['code' => 0, 'msg' => '登录失败次数过多，请15分钟后再试']);
        }

        $user = \app\model\User::where('username', $username)->find();
        
        if (!$user || !password_verify($password, $user['password'])) {
            cache($cacheKey, $failCount + 1, 900); // 15分钟过期
            return json(['code' => 0, 'msg' => '账号或密码错误']);
        }

        if ($user['status'] == 0) {
            return json(['code' => 0, 'msg' => '该账号已被禁用']);
        }

        // 登录成功，清除失败计数
        cache($cacheKey, null);

        // Session 只存储必要字段，不存储密码哈希等敏感信息
        $userData = $user->toArray();
        unset($userData['password']);
        Session::set('user', $userData);
        return json(['code' => 1, 'msg' => '登录成功']);
    }

    /**
     * 注册处理
     */
    public function doRegister()
    {
        $params = $this->request->post();
        
        if (empty($params['email'])) return json(['code' => 0, 'msg' => '邮箱必填']);
        if (empty($params['code'])) return json(['code' => 0, 'msg' => '请输入验证码']);

        // 验证验证码(10分钟有效)
        $codeRecord = \think\facade\Db::name('blog_email_code')
            ->where('email', $params['email'])
            ->where('code', $params['code'])
            ->where('type', 'reg')
            ->where('status', 0)
            ->where('create_time', '>', time() - 600)
            ->find();
        
        if (!$codeRecord) {
            return json(['code' => 0, 'msg' => '验证码错误或已过期']);
        }

        if (\app\model\User::where('username', $params['username'])->find()) {
            return json(['code' => 0, 'msg' => '用户名已存在']);
        }

        if (\app\model\User::where('email', $params['email'])->find()) {
            return json(['code' => 0, 'msg' => '该邮箱已被注册']);
        }

        $user = new \app\model\User();
        $user->save([
            'username' => $params['username'],
            'password' => $params['password'],
            'email'    => $params['email'],
            'status'   => 1,
            'create_time' => time()
        ]);

        // 标记验证码已使用
        \think\facade\Db::name('blog_email_code')->where('id', $codeRecord['id'])->update(['status' => 1]);

        // 发送欢迎邮件
        $siteName = Config::getVal('site_name', 'Blog');
        \app\common\Mail::send($params['email'], "恭喜注册成功 - {$siteName}", "亲爱的用户，恭喜您在 {$siteName} 注册成功！欢迎探索我们的资源。");

        return json(['code' => 1, 'msg' => '注册成功']);
    }



    /**
     * 閫€鍑虹櫥褰?
     */
    public function logout()
    {
        Session::delete('user');
        return redirect((string) url('index/index'));
    }

    /**
     * 每日签到
     */
    public function checkin()
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        $reward = (float)Config::getVal('checkin_reward_amount', 0.5);
        $today = strtotime(date('Y-m-d'));

        // 使用事务 + 行锁防止并发重复签到
        \think\facade\Db::startTrans();
        try {
            // 行锁：锁定用户记录防止并发
            $user = \app\model\User::where('id', $sessionUser['id'])->lock(true)->find();
            if (!$user) {
                \think\facade\Db::rollback();
                return json(['code' => 0, 'msg' => '用户不存在']);
            }

            $count = \think\facade\Db::name('blog_signin_log')
                ->where('user_id', $user->id)
                ->where('create_time', '>=', $today)
                ->count();

            if ($count > 0) {
                \think\facade\Db::rollback();
                return json(['code' => 0, 'msg' => '您今天已经签到过了']);
            }

            $user->money += $reward;
            $user->save();

            \think\facade\Db::name('blog_signin_log')->insert([
                'user_id'     => $user->id,
                'reward'      => $reward,
                'create_time' => time()
            ]);

            \think\facade\Db::commit();

            return json([
                'code' => 1,
                'msg' => '签到成功，获得 ' . $reward . ' 金币',
                'new_balance' => $user->money
            ]);
        } catch (\Exception $e) {
            \think\facade\Db::rollback();
            return json(['code' => 0, 'msg' => '签到失败，请稍后再试']);
        }
    }

    /**
     * 余额充值 (创建订单)
     */
    public function recharge()
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        $money = (float)$this->request->post('money', 0);
        if ($money <= 0 || $money > 10000) {
            return json(['code' => 0, 'msg' => '请输入有效的充值金额(0.01~10000)']);
        }
        $money = round($money, 2);

        // 安全随机订单号
        $orderNo = date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
        $orderId = \think\facade\Db::name('blog_order')->insertGetId([
            'order_no'    => $orderNo,
            'user_id'     => $sessionUser['id'],
            'article_id'  => 0,
            'vip_id'      => 0,
            'type'        => 4,
            'price'       => $money,
            'status'      => 0,
            'create_time' => time()
        ]);

        if ($orderId) {
            return json([
                'code' => 1, 
                'msg'  => '订单创建成功', 
                'url'  => (string)url('index/Pay/index', ['order_no' => $orderNo])
            ]);
        } else {
            return json(['code' => 0, 'msg' => '订单创建失败']);
        }
    }

    /**
     * 发送邮箱验证码
     */
    public function sendEmailCode()
    {
        $email = $this->request->post('email');
        $type = $this->request->post('type', 'reg'); // reg:注册, find:找回密码

        if (empty($email)) return json(['code' => 0, 'msg' => '请输入邮箱']);

        // 检查发送频率(1分钟内只能发一次)
        $last = \think\facade\Db::name('blog_email_code')
            ->where('email', $email)
            ->where('type', $type)
            ->order('create_time', 'desc')
            ->find();
        
        if ($last && time() - $last['create_time'] < 60) {
            return json(['code' => 0, 'msg' => '发送太频繁，请稍后再试']);
        }

        // 如果是找回密码，检查邮箱是否存在
        if ($type == 'find') {
            $exists = \app\model\User::where('email', $email)->find();
            if (!$exists) {
                return json(['code' => 0, 'msg' => '该邮箱未注册']);
            }
        }

        $code = (string)mt_rand(100000, 999999);
        
        \think\facade\Db::name('blog_email_code')->insert([
            'email' => $email,
            'code'  => $code,
            'type'  => $type,
            'status' => 0,
            'create_time' => time()
        ]);

        $siteName = Config::getVal('site_name', 'Blog');
        $subject = ($type == 'reg' ? '用户注册' : '找回密码') . '验证码 - ' . $siteName;
        $content = "<p>您好，</p><p>您正在进行" . ($type == 'reg' ? "注册" : "找回密码") . "操作。</p><p>您的验证码是：<strong style='color:#ff4444; font-size:18px;'>{$code}</strong></p><p>有效期10分钟，如非本人操作请忽略。</p>";

        $res = \app\service\EmailService::send($email, $subject, $content);

        if ($res === true) {
            return json(['code' => 1, 'msg' => '验证码已发送至您的邮箱']);
        } else {
            return json(['code' => 0, 'msg' => '发送失败：' . $res]);
        }
    }

    /**
     * 找回密码页面
     */
    public function retrievePassword()
    {
        $site_config = Config::getAll();
        return view('retrieve_password', ['config' => $site_config]);
    }

    /**
     * 执行找回密码
     */
    public function doRetrievePassword()
    {
        $params = $this->request->post();
        $email = $params['email'] ?? '';
        $code = $params['code'] ?? '';
        $new_password = $params['password'] ?? '';

        if (empty($email) || empty($code) || empty($new_password)) {
            return json(['code' => 0, 'msg' => '参数不完整']);
        }

        // 验证码校验
        $codeRecord = \think\facade\Db::name('blog_email_code')
            ->where('email', $email)
            ->where('code', $code)
            ->where('type', 'find')
            ->where('status', 0)
            ->where('create_time', '>', time() - 600)
            ->find();
        
        if (!$codeRecord) {
            return json(['code' => 0, 'msg' => '验证码错误或已过期']);
        }

        $user = \app\model\User::where('email', $email)->find();
        if (!$user) {
            return json(['code' => 0, 'msg' => '该邮箱未注册账号']);
        }

        // 修改密码 (自动哈希由模型处理，或手动处理)
        // 这里假设模型会自动处理 modify accessors or trigger
        // 如果没有模型事件，则需要手动: $user->password = password_hash($new_password, PASSWORD_DEFAULT);
        // 查看 User 模型确认... 假设 User 模型有 setPasswordAttr
        $user->password = $new_password; 
        $user->save();

        // 标记验证码已使用
        \think\facade\Db::name('blog_email_code')->where('id', $codeRecord['id'])->update(['status' => 1]);

        return json(['code' => 1, 'msg' => '密码已成功重置']);
    }
    /**
     * 提交工单
     */
    public function submitTicket()
    {
        if ($this->request->isPost()) {
            $user = \think\facade\Session::get('user');
            if (!$user) {
                return json(['code' => 0, 'msg' => '请先登录']);
            }
            
            $title = $this->request->post('title', '');
            $content = $this->request->post('content', '');
            
            if (empty($title) || empty($content)) {
                return json(['code' => 0, 'msg' => '主题和内容不能为空']);
            }
            
            \think\facade\Db::name('blog_ticket')->insert([
                'user_id' => $user['id'],
                'title' => htmlspecialchars($title),
                'content' => htmlspecialchars($content),
                'status' => 0, // 0 待处理
                'create_time' => time(),
                'update_time' => time()
            ]);
            
            return json(['code' => 1, 'msg' => '工单提交成功']);
        }
        return json(['code' => 0, 'msg' => '非法请求']);
    }

    /**
     * 发布投稿
     */
    public function submitContribution()
    {
        if ($this->request->isPost()) {
            $user = \think\facade\Session::get('user');
            if (!$user) {
                return json(['code' => 0, 'msg' => '请先登录']);
            }
            
            $id = $this->request->post('id', 0, 'intval');
            $title = $this->request->post('title', '');
            $content = $this->request->post('content', '');
            
            if (empty($title) || empty($content)) {
                return json(['code' => 0, 'msg' => '标题和正文不能为空']);
            }
            
            $data = [
                'user_id'     => $user['id'],
                'category_id' => $this->request->post('category_id', 0, 'intval'),
                'price'       => $this->request->post('price', 0, 'floatval'),
                'title'       => htmlspecialchars($title),
                'content'     => $content,
                'resource_url'=> $this->request->post('resource_url', ''),
                'resource_pwd'=> $this->request->post('resource_pwd', ''),
                'is_vip_free' => 1, // 默认为1，VIP会员拥有权限
                'status'      => 0, // 0 审核中
                'update_time' => time()
            ];

            if ($id > 0) {
                // 编辑现有投稿
                $res = \think\facade\Db::name('blog_contribution')
                    ->where('id', $id)
                    ->where('user_id', $user['id'])
                    ->update($data);
                
                if ($res !== false) {
                    return json(['code' => 1, 'msg' => '投稿修改成功，请等待重新审核']);
                } else {
                    return json(['code' => 0, 'msg' => '修改失败，请稍后再试']);
                }
            } else {
                // 新增投稿
                $data['create_time'] = time();
                \think\facade\Db::name('blog_contribution')->insert($data);
                
                // Attachment Binding Logic
                preg_match_all('/\/storage\/[^\s"\'>]+/', $content, $matches);
                if (!empty($matches[0])) {
                    $urls = array_unique($matches[0]);
                    Db::name('attachment')->where('url', 'in', $urls)->update(['status' => 1]);
                }
                
                return json(['code' => 1, 'msg' => '鎶曠鎻愪氦鎴愬姛锛岃绛夊緟瀹℃牳']);
            }
        }
        return json(['code' => 0, 'msg' => '闈炴硶璇锋眰']);
    }

    /**
     * 获取投稿详情
     */
    public function getContribution()
    {
        $user = \think\facade\Session::get('user');
        if (!$user) {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        $id = $this->request->get('id', 0, 'intval');
        if (!$id) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $info = \think\facade\Db::name('blog_contribution')
            ->where('id', $id)
            ->where('user_id', $user['id'])
            ->find();

        if (!$info) {
            return json(['code' => 0, 'msg' => '投稿内容不存在']);
        }

        return json(['code' => 1, 'data' => $info]);
    }

    /**
     * 提交友链申请
     */
    public function submitFriendLink()
    {
        if ($this->request->isPost()) {
            $user = \think\facade\Session::get('user');
            if (!$user) {
                return json(['code' => 0, 'msg' => '请先登录']);
            }
            
            $id = $this->request->post('id', 0, 'intval');
            $name = $this->request->post('name', '');
            $url = $this->request->post('url', '');
            
            if (empty($name) || empty($url)) {
                return json(['code' => 0, 'msg' => '网站名称和链接不能为空']);
            }
            
            $data = [
                'user_id'     => $user['id'],
                'name'        => htmlspecialchars($name),
                'url'         => htmlspecialchars($url),
                'description' => htmlspecialchars($this->request->post('description', '')),
                'logo'        => htmlspecialchars($this->request->post('logo', '')),
                'update_time' => time()
            ];

            if ($id > 0) {
                // 修改已存在的申请 (只有待审核和被拒绝的可以修改，修改后重置为待审核)
                $link = \think\facade\Db::name('blog_friend_link')
                    ->where('id', $id)
                    ->where('user_id', $user['id'])
                    ->find();
                    
                if (!$link || $link['status'] == 1 || $link['is_paid'] == 1) {
                     return json(['code' => 0, 'msg' => '此状态下的友链无法修改']);
                }
                
                $data['status'] = 0; // 重置为待审核
                $res = \think\facade\Db::name('blog_friend_link')
                    ->where('id', $id)
                    ->update($data);
                
                if ($res !== false) {
                    return json(['code' => 1, 'msg' => '修改成功，请等待重新审核']);
                } else {
                    return json(['code' => 0, 'msg' => '修改失败，请稍后再试']);
                }
            } else {
                // 新增免费申请
                $data['create_time'] = time();
                $data['status'] = 0; // 待审核
                $data['is_paid'] = 0;
                $data['pay_amount'] = 0;
                
                \think\facade\Db::name('blog_friend_link')->insert($data);
                
                return json(['code' => 1, 'msg' => '申请提交成功，请等待管理员审核']);
            }
        }
        return json(['code' => 0, 'msg' => '非法请求']);
    }

    /**
     * 获取自己的友链详情
     */
    public function getFriendLink()
    {
        $user = \think\facade\Session::get('user');
        if (!$user) {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        $id = $this->request->get('id', 0, 'intval');
        if (!$id) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $info = \think\facade\Db::name('blog_friend_link')
            ->where('id', $id)
            ->where('user_id', $user['id'])
            ->find();

        if (!$info) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }

        return json(['code' => 1, 'data' => $info]);
    }

    /**
     * 付费购买友情链接 (直接上架)
     */
    public function buyFriendLink()
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        $name = $this->request->post('name', '');
        $url = $this->request->post('url', '');
        $description = $this->request->post('description', '');
        $logo = $this->request->post('logo', '');
        
        if (empty($name) || empty($url)) {
            return json(['code' => 0, 'msg' => '网站名称和链接不能为空']);
        }

        $price = (float)Config::getVal('friend_link_price', 0);
        if ($price <= 0) {
            return json(['code' => 0, 'msg' => '当前未开启付费直通功能']);
        }

        // 1. 创建友链记录 (状态先设为待审核，支付成功后改为已通过)
        $linkId = \think\facade\Db::name('blog_friend_link')->insertGetId([
            'user_id'     => $sessionUser['id'],
            'name'        => htmlspecialchars($name),
            'url'         => htmlspecialchars($url),
            'description' => htmlspecialchars($description),
            'logo'        => htmlspecialchars($logo),
            'status'      => 0, // 支付成功后在 OrderService 中修改为1
            'is_paid'     => 1, // 标记为付费通道
            'pay_amount'  => $price,
            'create_time' => time(),
            'update_time' => time()
        ]);

        if (!$linkId) {
            return json(['code' => 0, 'msg' => '处理失败，请稍后重试']);
        }

        // 2. 创建支付订单 (type = 5 表示友链购买)
        $orderNo = date('YmdHis') . mt_rand(1000, 9999);
        $orderId = \think\facade\Db::name('blog_order')->insertGetId([
            'order_no'    => $orderNo,
            'user_id'     => $sessionUser['id'],
            'article_id'  => $linkId, // 用 article_id 暂存友链表中的记录ID
            'vip_id'      => 0,
            'type'        => 5, // 自定义类型: 5 = 购买友链
            'price'       => $price,
            'status'      => 0, 
            'create_time' => time()
        ]);

        if ($orderId) {
            return json([
                'code' => 1, 
                'msg'  => '订单创建成功，前往支付...', 
                'url'  => (string)url('index/Pay/index', ['order_no' => $orderNo])
            ]);
        } else {
            return json(['code' => 0, 'msg' => '订单创建失败']);
        }
    }

    /**
     * 取消订单
     */
    public function cancelOrder()
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        $orderNo = $this->request->post('order_no');
        if (empty($orderNo)) {
            return json(['code' => 0, 'msg' => '订单号不能为空']);
        }

        $order = Db::name('blog_order')
            ->where('order_no', $orderNo)
            ->where('user_id', $sessionUser['id'])
            ->where('status', 0)
            ->find();

        if (!$order) {
            return json(['code' => 0, 'msg' => '订单不存在或无法取消']);
        }

        Db::name('blog_order')
            ->where('order_no', $orderNo)
            ->update(['status' => 2]);

        return json(['code' => 1, 'msg' => '订单已取消']);
    }
}

