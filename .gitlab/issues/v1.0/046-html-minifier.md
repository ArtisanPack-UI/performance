# Implement HTML minifier

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::7" ~"Area::Backend"

## Problem Statement

Reducing HTML size improves transfer time and TTFB.

## Proposed Solution

Create `HtmlMinifier` class to remove whitespace and comments from HTML output.

## Acceptance Criteria

- [ ] Create `src/Output/HtmlMinifier.php`
- [ ] Remove HTML comments
- [ ] Collapse whitespace
- [ ] Preserve content in pre/code/textarea/script
- [ ] Optional: preserve line breaks
- [ ] Configurable via config
- [ ] Unit tests for minification

## Use Cases

1. Reduce HTML response size by 10-20%
2. Preserve code examples and form inputs
3. Improve transfer time

## Additional Context

```php
use ArtisanPackUI\Performance\Output\HtmlMinifier;

$minifier = app(HtmlMinifier::class);
$minified = $minifier->minify($html);

// Typical reduction: 10-20%
```

**Config:**
```php
'html_minification' => [
    'enabled' => true,
    'remove_comments' => true,
    'remove_whitespace' => true,
    'preserve_line_breaks' => false,
    'exclude_elements' => ['pre', 'code', 'textarea', 'script'],
],
```

**Before:**
```html
<div>
    <p>Hello    World</p>
    <!-- Comment -->
</div>
```

**After:**
```html
<div><p>Hello World</p></div>
```

---

**Related Issues:**
- #002 (Configuration)
