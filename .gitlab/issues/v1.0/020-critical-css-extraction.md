# Implement critical CSS extraction

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Render-blocking CSS delays page display. Inlining critical (above-the-fold) CSS improves LCP.

## Proposed Solution

Create `CriticalCssExtractor` to extract and inline above-the-fold CSS.

## Acceptance Criteria

- [ ] Create `src/Css/CriticalCssExtractor.php`
- [ ] Extract CSS needed for above-the-fold content
- [ ] Configurable viewport dimensions
- [ ] Cache extracted CSS per route
- [ ] Inline critical CSS in `<head>`
- [ ] Load remaining CSS asynchronously
- [ ] Artisan command: `php artisan perf:critical-css`
- [ ] Blade directive: `@criticalCss`
- [ ] Unit tests for extraction

## Use Cases

1. Developer generates critical CSS for homepage
2. Critical CSS is inlined in `<head>`
3. Non-critical CSS loads asynchronously

## Additional Context

```bash
# Generate critical CSS for routes
php artisan perf:critical-css --route=home
php artisan perf:critical-css --all
```

```blade
{{-- In layout head --}}
@criticalCss

{{-- Or with component --}}
<x-perf-critical-css :route="request()->route()->getName()" />
```

**Config:**
```php
'css' => [
    'critical' => [
        'enabled' => true,
        'width' => 1300,
        'height' => 900,
        'cache' => true,
    ],
]
```

---

**Related Issues:**
- #003 (Performance Facade)
