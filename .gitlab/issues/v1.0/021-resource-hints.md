# Implement resource hints system

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::3" ~"Area::Backend"

## Problem Statement

Resource hints (preload, preconnect, prefetch, dns-prefetch) help browsers load resources earlier.

## Proposed Solution

Create resource hint system with automatic and manual hint generation.

## Acceptance Criteria

- [ ] Create `src/Contracts/ResourceHintProvider.php`
- [ ] Create `src/Output/ResourceHintInjector.php`
- [ ] Support: preload, preconnect, prefetch, dns-prefetch
- [ ] Automatic hint generation for third-party domains
- [ ] Manual hint configuration via config
- [ ] Blade directives: `@preconnect`, `@dnsPrefetch`, `@preload`, `@prefetch`
- [ ] Component: `<x-perf-resource-hints>`
- [ ] Unit tests for hint generation

## Use Cases

1. Preconnect to fonts.googleapis.com
2. Preload critical font files
3. Prefetch next page resources
4. DNS prefetch for analytics domains

## Additional Context

```blade
{{-- Individual hints --}}
@preconnect('https://fonts.googleapis.com')
@dnsPrefetch('https://analytics.example.com')
@preload('/fonts/inter.woff2', 'font', 'font/woff2')
@prefetch('/js/next-page.js')

{{-- Component --}}
<x-perf-resource-hints :hints="[
    ['rel' => 'preconnect', 'href' => 'https://fonts.googleapis.com'],
    ['rel' => 'preload', 'href' => '/fonts/inter.woff2', 'as' => 'font'],
]" />
```

**Config:**
```php
'resource_hints' => [
    'auto_generate' => true,
    'preconnect' => ['https://fonts.googleapis.com'],
    'dns_prefetch' => ['https://www.google-analytics.com'],
],
```

---

**Related Issues:**
- #003 (Performance Facade)
