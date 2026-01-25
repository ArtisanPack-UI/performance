# Implement SpeculativeRulesGenerator

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::4" ~"Area::Backend"

## Problem Statement

The Speculation Rules API enables instant page navigations by pre-rendering likely next pages. The package needs to generate these rules.

## Proposed Solution

Create `SpeculativeRulesGenerator` to generate speculation rules JSON based on configuration.

## Acceptance Criteria

- [ ] Create `src/Speculative/SpeculativeRulesGenerator.php`
- [ ] Generate prefetch rules
- [ ] Generate prerender rules
- [ ] Support eagerness levels: immediate, eager, moderate, conservative
- [ ] Support pattern matching for URLs
- [ ] Exclude patterns configuration
- [ ] Include patterns configuration
- [ ] Limit concurrent prerenders
- [ ] Unit tests for rule generation

## Use Cases

1. Generate prefetch rules for document links
2. Generate prerender rules for high-confidence navigations
3. Exclude logout/admin URLs from speculation

## Additional Context

```php
$generator = app(SpeculativeRulesGenerator::class);

$rules = $generator->generate([
    'prefetch' => [
        'eagerness' => 'moderate',
        'exclude_patterns' => ['/logout', '/admin/*'],
    ],
    'prerender' => [
        'eagerness' => 'conservative',
        'include_patterns' => ['/products/*', '/blog/*'],
        'limit' => 2,
    ],
]);

// Returns JSON for <script type="speculationrules">
```

**Output:**
```json
{
    "prefetch": [{
        "source": "document",
        "where": { "href_matches": "/*" },
        "eagerness": "moderate"
    }],
    "prerender": [{
        "source": "document",
        "where": { "selector_matches": "a[data-prerender]" },
        "eagerness": "conservative"
    }]
}
```

---

**Related Issues:**
- #002 (Configuration)
