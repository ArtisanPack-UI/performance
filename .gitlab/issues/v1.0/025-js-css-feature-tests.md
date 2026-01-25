# Create JavaScript & CSS optimization feature tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

JavaScript and CSS optimization features need comprehensive tests.

## Proposed Solution

Create feature tests for all Phase 3 functionality.

## Acceptance Criteria

- [ ] Tests for ScriptManager registration
- [ ] Tests for each loading strategy output
- [ ] Tests for critical CSS extraction
- [ ] Tests for resource hint generation
- [ ] Tests for Blade directives
- [ ] Tests for Blade components
- [ ] Tests for InjectResourceHints middleware
- [ ] All tests pass

## Use Cases

1. CI validates script management works correctly
2. Developers run tests after changes
3. Tests catch regressions

## Additional Context

```php
it('registers script with defer strategy', function () {
    Performance::script('/js/app.js')->defer();

    $scripts = Performance::getScripts();

    expect($scripts)->toHaveCount(1);
    expect($scripts[0]->strategy)->toBe('defer');
});

it('renders defer script correctly', function () {
    $html = Blade::render('@deferScript("/js/app.js")');

    expect($html)->toContain('defer');
    expect($html)->toContain('/js/app.js');
});

it('injects resource hints into response', function () {
    config(['artisanpack.performance.resource_hints.preconnect' => [
        'https://fonts.googleapis.com',
    ]]);

    $response = $this->get('/');

    $response->assertSee('rel="preconnect"');
    $response->assertSee('https://fonts.googleapis.com');
});
```

---

**Related Issues:**
All Phase 3 issues
