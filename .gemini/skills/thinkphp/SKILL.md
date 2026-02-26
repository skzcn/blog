---
name: thinkphp8.1.4 ThinkPHP 框架开发
description: ThinkPHP 8.1.4 标准化开发流程。涵盖环境初始化、核心架构配置、控制器与模型深度开发、业务逻辑封装（Service层）、路由高级应用、性能优化（缓存、数据库）、单元测试及生产环境自动化部署。
---

## 1. 环境准备与项目初始化

### 开发内容

1. 环境配置：安装 PHP8.0+（推荐8.1）、MySQL5.7+/8.0、Composer、Nginx/Apache，确保开启PDO、mbstring、openssl等扩展；
2. 基础配置：修改 `.env` 文件配置数据库连接（DB_HOST/DB_NAME/DB_USER/DB_PWD）、应用调试模式（APP_DEBUG=true）、默认时区（default_timezone=PRC）；
3. 目录梳理：熟悉 TP8 核心目录结构（app/控制器/模型、config/配置、public/入口、runtime/运行缓存、vendor/依赖），确认权限（runtime/可写）。

## 2. 核心架构与基础配置

### 开发内容

1. 应用配置：在 config/app.php 中设置默认应用（default_app）、默认控制器（default_controller）、默认操作（default_action）；
2. 数据库配置：优化 config/database.php，配置数据库连接池（connections）、查询日志（log=true）、慢查询阈值（slow_query_log=1）；
3. 路由配置：初始化 route/app.php，定义基础路由（如首页路由 `Route::get('/', 'Index/index');`），开启路由强制模式（url_route_must=true）；
4. 全局中间件：注册通用中间件（如跨域处理、请求过滤、登录验证），在 app/middleware.php 中配置全局/应用/路由级中间件。

## 3. 控制器与模型开发

### 开发内容

1. 控制器开发：在 app/controller 下创建业务控制器（如 UserController.php），遵循 TP8 控制器规范（继承 Controller 类、方法返回响应），实现基础 CURD 方法（index/列表、save/新增、read/详情、update/编辑、delete/删除）；
2. 模型开发：在 app/model 下创建对应模型（如 User.php），继承 Model 类，定义表名（protected $name = 'user'）、主键（protected $pk = 'id'）、字段过滤（protected $field = ['username', 'email', 'status']），实现关联模型（如用户-订单关联）；
3. 验证器开发：创建 app/validate 验证器（如 UserValidate.php），定义字段验证规则（如用户名必填、邮箱格式、密码长度），在控制器中调用验证（$this->validate($data, UserValidate::class)）；
4. 响应规范：统一接口返回格式（如 `return json(['code' => 200, 'msg' => 'success', 'data' => $data]);`），封装通用响应类简化返回逻辑。

## 4. 业务逻辑与功能实现

### 开发内容

1. 业务层封装：复杂业务逻辑抽离到 app/service 服务层（如 UserService.php），控制器调用服务层方法，降低耦合；
2. 模板渲染（如需）：使用 TP8 模板引擎，在 app/view 下创建模板文件，通过控制器 `return view('index', $data);` 渲染，使用模板标签（{$变量}、{volist 循环}、{if 条件}）；
3. 数据操作：使用模型进行 CURD（如 User::where('status', 1)->select()、User::create($data)、User::update($data, ['id' => $id])），避免原生 SQL 注入，使用查询构造器优化复杂查询；
4. 文件处理：实现文件上传（使用 Request 类的 file 方法）、图片裁剪（结合 think-image 扩展），配置上传路径（public/upload/）并限制文件类型/大小。

## 5. 路由与接口调试

### 开发内容

1. 路由细化：完善路由配置，实现路由分组（如 `Route::group('user', function () { Route::get('list', 'User/index'); Route::post('add', 'User/save'); });`）、参数绑定（如 `Route::get('user/:id', 'User/read')->pattern(['id' => '\d+']);`）、RESTful 路由；
2. 接口调试：使用 Postman/Swagger 调试接口，验证参数传递、返回格式、异常处理（如参数错误返回400、数据不存在返回404）；
3. 异常处理：自定义异常类（继承 Exception），在 app/exception/ 下创建，配置 config/exception.php 统一异常捕获，返回友好错误信息；
4. 日志记录：配置 config/log.php，记录业务日志（如用户操作、接口请求），使用 Log 类（Log::info('用户登录', ['user_id' => $id])）记录关键行为。

## 6. 缓存与性能优化

### 开发内容

1. 缓存配置：配置 Redis/Memcached 缓存驱动（config/cache.php），使用缓存类（Cache::set('user*'.$id, $user, 3600)、Cache::get('user*'.$id)）缓存高频查询数据；
2. 数据库优化：添加数据库索引（主键、常用查询字段），使用模型延迟加载/预加载（with()）避免 N+1 查询问题，分页查询（paginate()）优化大数据列表；
3. 代码优化：避免循环内数据库查询、精简模板渲染逻辑、关闭调试模式（APP_DEBUG=false）减少性能损耗；
4. 静态资源：将 CSS/JS/图片等静态资源部署到 CDN，配置 Nginx 缓存静态资源。

## 7. 测试与问题修复

### 开发内容

1. 单元测试：使用 PHPUnit 编写控制器/模型/服务层单元测试，验证核心方法逻辑正确性；
2. 功能测试：覆盖所有业务场景（正常流程、异常流程、边界条件），验证功能完整性；
3. 兼容性测试：测试不同 PHP 版本、浏览器、设备下的运行效果，确保兼容性；
4. Bug 修复：根据测试反馈修复功能缺陷、性能问题、安全漏洞（如 XSS/CSRF/SQL 注入）。

## 8. 上线部署与运维

### 开发内容

1. 环境切换：修改 `.env` 文件，关闭调试模式（APP_DEBUG=false）、开启生产环境配置、关闭查询日志；
2. 代码打包：清理开发环境文件（测试用例、调试代码、runtime/缓存），压缩代码包；
3. 服务器部署：将代码上传至生产服务器，配置 Nginx 虚拟主机（伪静态规则 `location / { try_files $uri $uri/ /index.php$is_args$args; }`），设置运行目录为 public/；
4. 数据迁移：导入生产环境数据库，执行数据库升级脚本（如有）；
5. 监控运维：配置服务器监控（CPU/内存/磁盘）、应用日志监控，定期备份数据库和代码。

## 9.语言

### 开发中注释

1. 在开发中请使用中文注释，包括前端。写清楚功能注释

## 10.开发总流程

1. ThinkPHP8.1.4 开发流程核心遵循「环境初始化→基础配置→核心开发→功能实现→优化测试→上线部署」的标准化步骤，每个阶段需聚焦对应核心配置和开发规范；
2. 开发中需重点关注 TP8 的核心特性（如路由机制、模型操作、中间件、异常处理），同时做好缓存优化、安全防护和环境隔离；
3. 上线前必须关闭调试模式、清理冗余文件、配置生产环境的 Nginx 伪静态和数据库权限，确保项目稳定运行。
