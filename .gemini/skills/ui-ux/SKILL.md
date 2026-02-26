---
name: UI/UX UI/UX 交互设计
description: 全栈 UI/UX 设计与落地指南。从需求分析与风格定位出发，涵盖色彩体系建立、低保真原型设计、主流前端框架（layui/Bootstrap/Tailwind）组件化开发、响应式布局适配以及视觉走查与功能验证。
---

## 1. 需求分析与风格定位

### 开发内容

1. **需求拆解**：梳理产品核心场景（ToB/ToC）、目标用户（年龄/使用习惯）、核心功能模块，输出《UI/UX需求文档》，明确设计边界；
2. **风格选型**：
   - 极简风：适配企业后台/工具类产品，优先用 Tailwind CSS 原子类（`bg-slate-50 text-slate-900`）+ Vue 单文件组件实现；
   - 商务风：适配金融/政务产品，基于 Bootstrap 预设样式（`btn-primary`/`card`）+ jQuery 控制交互状态；
   - 轻量风：适配中小型管理系统，基于 layui 内置组件（`layui-btn`/`layui-table`）快速搭建基础样式；
3. **技术栈选型**：
   - 快速原型/中小型项目：layui + jQuery（低成本、文档完善）；
   - 通用企业级项目：Bootstrap + jQuery/Vue（兼容性强、组件丰富）；
   - 现代化定制化项目：Tailwind CSS + Vue（高度定制、适配响应式）；
4. **竞品参考**：分析同行业产品的配色、布局、交互逻辑，输出《风格参考手册》。

## 2. 色彩体系设计与技术落地

### 开发内容

1. **色彩规范制定**：
   - 主色：1种（如 #165DFF），适配品牌调性，用于按钮、导航、强调元素；
   - 辅助色：3-5种（成功#00B42A/警告#FF7D00/危险#F53F3F），用于状态提示；
   - 中性色：5-8种（浅灰#F2F3F5/中灰#C9CDD4/深灰#1D2129），用于背景、文本、边框；
2. **技术端落地**：
   - layui：修改 `layui/css/modules/layui/default/css.css` 替换预设色值，或通过 `layui.config({base: '自定义样式路径/'})` 覆盖；
   - Bootstrap：自定义 `_variables.scss` 修改变量（`$primary: #165DFF;`）后重新编译，或直接用 `!important` 覆盖全局样式；
   - Tailwind CSS：在 `tailwind.config.js` 中配置主题色（`theme: {extend: {colors: {primary: '#165DFF'}}}`），直接调用 `bg-primary`/`text-primary`；
   - Vue：封装色彩常量文件（`src/assets/colors.js`），通过 `import` 引入组件，结合 `:style` 动态绑定；
3. **无障碍校验**：确保文本与背景色对比度≥4.5:1，使用 Tailwind CSS 内置的 `contrast-*` 类或在线工具校验。

## 3. 原型设计与前端框架搭建

### 开发内容

1. **低保真原型**：用 Axure/Figma 绘制页面布局、交互流程，明确页面跳转、组件位置；
2. **前端项目初始化**：
   - layui + jQuery：

     ```html
     <link rel="stylesheet" href="layui/css/layui.css" />
     <script src="jquery.min.js"></script>
     <script src="layui/layui.js"></script>
     <script>
       layui.use(['form', 'table'], function(){...})
     </script>
     ```

   - Bootstrap + jQuery：  
      <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
     <script src="jquery.min.js"></script>
     <script src="bootstrap/js/bootstrap.bundle.min.js"></script>

   - Tailwind CSS + Vue：
     npm create vue@latest my-project
     cd my-project
     npm install -D tailwindcss postcss autoprefixer
     npx tailwindcss init -p
     配置 tailwind.config.js 后在 main.js 引入 @import 'tailwindcss/base';；

## 基础布局搭建：

1. 通用布局：头部（导航）+ 侧边栏（菜单）+ 主体（内容）+ 底部（版权）；
2. layui/Bootstrap：用栅格系统（layui-row/layui-col/row/col）实现响应式；
3. Tailwind CSS + Vue：用 flex/grid 原子类（flex flex-col md:flex-row）+ Vue 路由（router-view）实现页面切换。

## 开发内容

## 通用组件设计：

1. 按钮：定义主按钮（btn-primary）、次要按钮（btn-secondary）、危险按钮（btn-danger），统一尺寸（高 40px、圆角 4px）；
2. layui：<button class="layui-btn layui-btn-primary">按钮</button>；
3. Tailwind CSS：<button class="bg-primary text-white h-10 rounded px-4">按钮</button>；
4. 表单：输入框、下拉框、复选框，统一间距（上下 16px、左右 12px），适配 Vue 双向绑定（v-model）；
5. 表格：统一表头样式、行高、斑马纹，layui/Bootstrap 用内置表格组件，Vue 结合 el-table（Element Plus）实现；

## 交互逻辑开发：

1. jQuery：处理按钮点击、表单校验、弹窗显示（$('#btn').click(function(){...})）；
2. Vue：用 v-on/@ 绑定事件、v-if/v-show 控制元素显隐、watch/computed 处理数据联动；
3. 动效：基于 CSS3（transition/animation）+ Tailwind CSS 动效类（transition-all duration-300）实现 hover / 加载动效；

## 响应式适配：

1. layui/Bootstrap：利用栅格系统适配移动端（col-xs-12 col-md-6/layui-col-xs12 layui-col-md6）；
2. Tailwind CSS：用断点类（sm:/md:/lg:）适配不同屏幕（w-full md:w-1/2 lg:w-1/3）。
3. 视觉走查与功能测试

## 视觉走查：

1. 核对色彩、字体、间距是否符合设计规范，统一字体（微软雅黑 / Inter，正文 14px、标题 18px）；
2. 检查组件样式一致性（按钮圆角、输入框边框、表格行高）；
3. 校验明 / 暗模式适配（Tailwind CSS 用 dark: 前缀，dark:bg-slate-900 dark:text-white）；

## 功能测试：

1. 交互测试：按钮点击、表单提交、弹窗关闭、分页切换等逻辑是否正常；
2. 兼容性测试：适配 Chrome/Firefox/Edge 浏览器，移动端适配 iOS/Android；
3. 性能测试：图片懒加载（loading="lazy"）、接口请求防抖（jQuery/Vue 实现）；
4. 问题修复：记录视觉 / 功能问题，优先修复核心流程（登录、核心表单），再优化细节。

## 交付与迭代优化

## 交付物整理：

1. 设计稿：Figma/Axure 源文件、切图资源；
2. 代码：前端源码、部署文档、接口对接文档；
3. 设计规范：《UI 设计手册》（色彩、组件、交互规则）；
4. 部署上线：
5. 静态页面：部署到 Nginx，配置 gzip 压缩、静态资源缓存；
6. Vue 项目：npm run build 打包后部署，结合 Nginx 配置路由重写；
7. 迭代优化：收集用户反馈，迭代组件样式、交互逻辑，更新设计规范。

### 总结

1. UI/UX 设计流程核心遵循「需求分析→风格/色彩定义→原型/框架搭建→组件/交互开发→测试走查→交付迭代」，每个阶段需结合选定的前端技术栈（layui/Bootstrap/Tailwind CSS + jQuery/Vue）落地；
2. 色彩体系需统一主色/辅助色/中性色，不同技术栈有对应的落地方式（layui 改预设样式、Tailwind CSS 配置主题、Vue 封装常量）；
3. 组件设计要保证样式一致性，交互开发优先用原生 CSS3/JS 或框架内置能力，同时做好响应式适配和无障碍校验。
