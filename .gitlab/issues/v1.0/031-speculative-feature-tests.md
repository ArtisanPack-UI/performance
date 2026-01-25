# Create speculative loading feature tests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::4" ~"Area::Backend"

## Problem Statement

Speculative loading features need comprehensive tests.

## Proposed Solution

Create feature tests for all Phase 4 functionality.

## Acceptance Criteria

- [ ] Tests for speculation rules JSON generation
- [ ] Tests for prefetch/prerender managers
- [ ] Tests for embed optimizer
- [ ] Tests for Blade components
- [ ] Tests for URL pattern matching
- [ ] Tests for eagerness level configuration
- [ ] All tests pass

## Use Cases

1. CI validates speculative loading works correctly
2. Developers run tests after changes
3. Tests catch regressions

## Additional Context

```php
it('generates speculation rules JSON', function () {
    $generator = app(SpeculativeRulesGenerator::class);

    $rules = $generator->generate([
        'prefetch' => ['eagerness' => 'moderate'],
    ]);

    expect($rules)->toBeJson();
    expect(json_decode($rules))->toHaveProperty('prefetch');
});

it('excludes patterns from speculation', function () {
    $rules = $generator->generate([
        'prefetch' => [
            'exclude_patterns' => ['/logout', '/admin/*'],
        ],
    ]);

    expect($rules)->not->toContain('/logout');
});

it('renders embed facade component', function () {
    $html = Blade::render('<x-perf-embed provider="youtube" id="abc123" />');

    expect($html)->toContain('perf-embed-facade');
    expect($html)->toContain('data-provider="youtube"');
});
```

---

**Related Issues:**
All Phase 4 issues
