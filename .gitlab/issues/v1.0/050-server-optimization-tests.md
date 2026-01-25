# Create server-side optimization feature tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::7" ~"Area::Backend"

## Problem Statement

Server-side optimization features need comprehensive tests.

## Proposed Solution

Create feature tests for all Phase 7 functionality.

## Acceptance Criteria

- [ ] Tests for HtmlMinifier
- [ ] Tests for output buffer management
- [ ] Tests for MinifyHtml middleware
- [ ] Tests for EarlyHints middleware
- [ ] Tests for preserved elements (pre, code, textarea)
- [ ] Tests for excluded routes
- [ ] All tests pass

## Use Cases

1. CI validates server optimizations work correctly
2. Developers run tests after changes
3. Tests catch regressions

## Additional Context

```php
it('minifies HTML response', function () {
    $html = '<div>   <p>Hello</p>   </div>';

    $minifier = app(HtmlMinifier::class);
    $minified = $minifier->minify($html);

    expect($minified)->toBe('<div><p>Hello</p></div>');
});

it('preserves pre element content', function () {
    $html = '<pre>   code   </pre>';

    $minified = $minifier->minify($html);

    expect($minified)->toContain('   code   ');
});

it('removes HTML comments', function () {
    $html = '<div><!-- comment --><p>Text</p></div>';

    $minified = $minifier->minify($html);

    expect($minified)->not->toContain('comment');
});

it('applies minification via middleware', function () {
    $response = $this->get('/');

    $content = $response->getContent();
    expect($content)->not->toMatch('/>\s+</');
});

it('skips minification for excluded routes', function () {
    config(['artisanpack.performance.html_minification.exclude_routes' => ['admin/*']]);

    $response = $this->get('/admin/dashboard');

    // Check original formatting preserved
});
```

---

**Related Issues:**
All Phase 7 issues
