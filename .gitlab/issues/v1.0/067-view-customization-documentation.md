# Create view customization documentation

/label ~"Type::Task" ~"Status::Backlog" ~"Priority::High" ~"Phase::10" ~"Area::Documentation"

## Problem Statement

Developers need comprehensive documentation on how to customize package views and components.

## Proposed Solution

Create detailed documentation covering all view customization options.

## Acceptance Criteria

### Publishing Views Guide
- [ ] Document `php artisan vendor:publish --tag=performance-views` command
- [ ] Explain published file locations
- [ ] Describe customization workflow

### Component Props Reference
- [ ] Document all component props with types
- [ ] Provide examples for each component
- [ ] Explain default values

### Slots Documentation
- [ ] Document all available slots per component
- [ ] Provide slot content examples
- [ ] Show slot usage patterns

### CSS Variables Reference
- [ ] List all CSS custom properties
- [ ] Document theming approach
- [ ] Provide dark mode examples
- [ ] Show CSS variable override examples

### Component Extension Examples
- [ ] Document extending base components
- [ ] Show custom chart implementations
- [ ] Explain method overriding

### JavaScript Customization Guide
- [ ] Document event hooks
- [ ] Show Alpine.js customization
- [ ] Explain JS configuration options

## Use Cases

1. Developer wants to match package UI to their brand
2. Developer needs to add custom functionality
3. Developer wants to extend components

## Additional Context

```markdown
## Example Documentation Structure

### Publishing Views

To customize the package views, publish them:

\`\`\`bash
php artisan vendor:publish --tag=performance-views
\`\`\`

Views are published to:
\`resources/views/vendor/artisanpack-ui/performance/\`

### Component Props

#### Performance Dashboard

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| class | string | '' | Additional CSS classes |
| card-classes | string | '' | Classes for metric cards |
| ...

### Available Slots

| Slot | Component | Description |
|------|-----------|-------------|
| header | Dashboard | Custom header content |
| before-metrics | Dashboard | Content before metrics |
| ...
```

---

**Related Issues:**
- #059 (View Customization Support)
- #066 (Complete Documentation)
