---
name: responsive-design 响应式设计指南
description: 现代响应式设计技术。深入探讨移动优先策略、流式布局、CSS Grid 与 Flexbox 高级应用、容器查询（Container Queries）以及流式字号与间距缩放，确保界面在各种屏幕尺寸下均能完美呈现。
---

# Responsive Design

Master modern responsive design techniques to create interfaces that adapt seamlessly across all screen sizes and device contexts.

## When to Use This Skill

- Implementing mobile-first responsive layouts
- Using container queries for component-based responsiveness
- Creating fluid typography and spacing scales
- Building complex layouts with CSS Grid and Flexbox
- Designing breakpoint strategies for design systems
- Implementing responsive images and media

## Core Capabilities

### 1. Mobile-First Breakpoint Scale

Always start with mobile styles and use `min-width` queries to enhance for larger screens.

```css
/* Base: Mobile (< 640px) */
@media (min-width: 640px) {
  /* sm: Landscape phones */
}
@media (min-width: 768px) {
  /* md: Tablets */
}
@media (min-width: 1024px) {
  /* lg: Laptops */
}
@media (min-width: 1280px) {
  /* xl: Desktops */
}
```

### 2. Fluid Layouts & Spacing

- **CSS Clamp**: Use `clamp()` for typography and padding that scales between bounds.
  `font-size: clamp(1rem, 5vw, 2.5rem);`
- **Dynamic Viewport**: Use `dvh` and `svh` for full-height elements to account for mobile browser UI.
- **Intrinsic Sizing**: Favor `min-content`, `max-content`, and `fit-content` over fixed widths.

### 3. Container Queries

Enable component-level responsiveness independent of the global viewport.

```css
.card-container {
  container-type: inline-size;
}
@container (min-width: 400px) {
  .card {
    grid-template-columns: 1fr 2fr;
  }
}
```

### 4. Hardware-Accelerated Animations

- Use `transform` and `opacity` for responsive transitions to ensure high performance on mobile devices.
- Respect user preferences using `prefers-reduced-motion`.

## Best Practices

1. **Touch Targets**: Maintain a minimum 44x44px clickable area on mobile.
2. **Horizontal Overflow**: Never allow content to break the horizontal viewport (use `max-width: 100%`).
3. **Z-Index Scale**: Implement a consistent z-index management system (10, 20, 30...).
4. **Logic Layout**: Always test on real devices to verify touch interactions and keyboard overlays.
5. **Images**: Implement `srcset` and `sizes` for responsive image delivery.
