---
name: web-design-guidelines Web 界面设计指南
description: Vercel 风格 Web 界面审查指南。包含无障碍访问（A11y）准则、焦点状态管理、表单交互规范、动画性能标准、排版与内容处理、图片加载优化以及深色模式和本地化适配。
---

# Web界面指南

请检查以下文件是否符合规范：$ARGUMENTS

读取文件，并对照以下规则进行检查。输出内容应简洁明了，但需全面全面——为追求简洁可牺牲一些语法。信噪比要高。

## 规则

### 无障碍访问

- 仅图标按钮需要 `aria-label`
- 表单控件需要 `<label>` 或 `aria-label`
- 交互元素需要键盘事件处理程序（`onKeyDown`/`onKeyUp`）
- 使用 `<button>` 表示操作，使用 `<a>`/`<Link>` 表示导航（而不是 `<div onClick>`）。
  图片需要 `alt` 属性（如果是装饰性图片，则需要 `alt=""` 属性）。
  装饰性图标需要 `aria-hidden="true"`
- 异步更新（提示信息、验证）需要 `aria-live="polite"`
- 在 ARIA 之前使用语义化的 HTML（`<button>`、`<a>`、`<label>`、`<table>`）。
- 标题采用层级结构 `<h1>`–`<h6>`；包含跳转至主要内容的链接
- 标题锚点上的 `scroll-margin-top`

### 焦点状态

- 交互元素需要可见焦点：`focus-visible:ring-*` 或等效项
- 切勿在未进行焦点替换的情况下使用 `outline-none` 或 `outline: none`。
- 使用 `:focus-visible` 代替 `:focus`（避免点击时出现对焦环）
- 使用 `:focus-within` 进行复合控件的分组聚焦

### 表格

输入框需要具备“自动完成”功能和有意义的“名称”。

- 使用正确的`type`（`email`、`tel`、`url`、`number`）和`inputmode`
- 永远不要阻止粘贴（`onPaste` + `preventDefault`）
- 标签可点击（`htmlFor` 或包装控件）
- 禁用电子邮件、代码和用户名中的拼写检查（`spellCheck={false}`）
- 复选框/单选按钮：标签 + 控制共享单个命中目标（无死角）
- 提交按钮在请求开始前保持启用状态；请求期间显示加载指示器
- 错误信息直接显示在字段旁边；提交时优先显示第一个错误信息。
- 占位符以 `…` 结尾，并显示示例模式
- 在非身份验证字段上禁用 `autocomplete="off"`，以避免触发密码管理器
- 在导航前发出警告，提示尚未保存的更改（`beforeunload` 或路由保护）

＃＃＃ 动画片

- 遵循“prefers-reduced-motion”（提供减少动作的版本或禁用）
- 仅对 `transform`/`opacity` 进行动画处理（对合成器友好）
- 永远不要使用 `transition: all`——显式列出属性
- 设置正确的 `transform-origin`
- SVG：对 `<g>` 包装器进行变换，使用 `transform-b​​ox: fill-box; transform-origin: center`
- 动画可中断——在动画播放过程中响应用户输入

### 排版

- `…` 而不是 `…`
- 卷曲的引号 `"` `"` 不是直的 `"`
- 不间断空格：`10MB`、`⌘K`、品牌名称
- 加载状态以 `…` 结尾：`"正在加载…"`，`"正在保存…"`
- `font-variant-numeric: tabular-nums` 用于数字列/比较
- 对标题使用 `text-wrap: balance` 或 `text-pretty`（防止出现孤行）

### 内容处理

- 文本容器处理长内容：`truncate`、`line-clamp-*` 或 `break-words`
- Flex 子元素需要 `min-w-0` 以允许文本截断
- 处理空状态——不要为空字符串/数组渲染错误的 UI
- 用户生成内容：预计会有短篇、中篇和长篇三种输入内容

### 图片

- `<img>` 需要显式指定 `width` 和 `height`（防止 CLS）
- 下方图片：`loading="lazy"`
- 首屏关键图片：`priority` 或 `fetchpriority="high"`

＃＃＃ 表现

- 大型列表（>50 项）：virtua(`virtua`, `content-visibility: auto`)
- 渲染时未读取布局（`getBoundingClientRect`、`offsetHeight`、`offsetWidth`、`scrollTop`）
- 批量读取/写入 DOM；避免交错执行
  优先选择非受控输入；受控输入的每次按键成本必须很低。
- 为 CDN/资产域名添加 `<link rel="preconnect">`
- 关键字体：`<link rel="preload" as="font">`，并设置 `font-display: swap`

### 导航与状态

- URL 反映了状态——查询参数中的筛选器、选项卡、分页、展开面板
- 链接使用 `<a>`/`<Link>`（支持 Cmd/Ctrl+单击、鼠标中键单击）
- 对所有有状态的 UI 进行深度链接（如果使用 `useState`，请考虑通过 nuqs 或类似工具进行 URL 同步）
- 破坏性操作需要确认模态框或撤销窗口，绝不能立即执行。

### 触控与交互

- `touch-action: manipulation`（防止双击缩放延迟）
- 故意设置了 `-webkit-tap-highlight-color`
- 在模态框/抽屉/表格中设置 `overscroll-behavior: contain`
- 拖动过程中：禁用文本选择，对拖动的元素保持“惰性”状态
- 谨慎使用“自动对焦”功能——仅限桌面端，且仅支持单一主要输入源；移动设备上应避免使用。

### 安全区域及布局

- 全出血布局需要使用 `env(safe-area-inset-*)` 来设置凹槽
- 避免出现不必要的滚动条：在容器上启用 `overflow-x-hidden`，修复内容溢出问题
- 基于 JS 的 Flex/grid 布局测量

### 深色模式和主题

- 在 `<html>` 中添加 `color-scheme: dark` 以启用深色主题（修复滚动条和输入框问题）
- `<meta name="theme-color">` 与页面背景色匹配
- 原生 `<select>`：显式设置 `background-color` 和 `color`（Windows 深色模式）

### 本地化和国际化

- 日期/时间：使用 `Intl.DateTimeFormat` 格式，而不是硬编码格式。
- 数字/货币：使用 `Intl.NumberFormat` 格式，而不是硬编码格式。
- 通过 `Accept-Language` / `navigator.languages` 检测语言，而非 IP 地址。

### 补水安全

- 带有 `value` 属性的输入需要添加 `onChange` 事件处理程序（或者对于不受控制的情况，可以使用 `defaultValue`）。
- 日期/时间渲染：防止水合状态不匹配（服务器端与客户端）
- 仅在真正需要的地方启用 `suppressHydrationWarning`

### 悬停和交互式状态

- 按钮/链接需要 `hover:` 状态（视觉反馈）
- 交互状态增强对比度：悬停/激活/专注状态比静止状态更突出

### 内容与文案

- 主动语态：“安装 CLI”，而不是“CLI 将被安装”。
- 标题/按钮采用芝加哥格式（Title Case）
- 计数应使用数字：“8 次部署”，而不是“八次”。
- 按钮标签应改为“保存 API 密钥”，而不是“继续”。
- 错误信息包含修复方法/下一步步骤，而不仅仅是问题描述。
- 使用第二人称；避免使用第一人称
- 在空间受限的情况下，`&` 代替“and”

### 反模式（请标记这些）

- `user-scalable=no` 或 `maximum-scale=1` 禁用缩放
- `onPaste` 与 `preventDefault`
- `transition: all`
- 没有 focus-visible 替换的 `outline-none`
- 不使用 `<a>` 的内联 `onClick` 导航
- 使用带有点击事件处理程序的 `<div>` 或 `<span>`（应该使用 `<button>`）
- 无尺寸图像
- 不使用虚拟化的大型数组 `.map()`
- 没有标签的表单输入
- 没有 `aria-label` 的图标按钮
- 硬编码的日期/数字格式（使用 `Intl.*`）
- 没有明确理由的“自动对焦”

## 输出格式

按文件分组。使用 `file:line` 格式（VS Code 可点击）。结果简明扼要。

```文本
## src/Button.tsx

src/Button.tsx:42 - 图标按钮缺少 aria-label 属性
src/Button.tsx:18 - 输入框缺少标签
src/Button.tsx:55 - 缺少动画 prefers-reduced-motion
src/Button.tsx:67 - transition: all → list properties

## src/Modal.tsx

src/Modal.tsx:12 - 缺少过度滚动行为：包含
src/Modal.tsx:34 - "..." → "…"

## src/Card.tsx

✓ 通过
```

说明问题及地点。除非解决方法不明显，否则无需解释。无需前言。
