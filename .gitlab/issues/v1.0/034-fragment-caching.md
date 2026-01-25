# Implement fragment caching

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::5" ~"Area::Backend"

## Problem Statement

Caching expensive view partials improves performance without full-page caching.

## Proposed Solution

Create `FragmentCache` class and `@cache` Blade directive.

## Acceptance Criteria

- [ ] Create `src/Cache/FragmentCache.php`
- [ ] Create `@cache` / `@endcache` Blade directives
- [ ] Cache key from directive argument
- [ ] TTL configuration
- [ ] Tag support for invalidation
- [ ] Dynamic cache keys (variables)
- [ ] Programmatic usage via service
- [ ] Tag-based invalidation
- [ ] Unit tests for fragment caching

## Use Cases

1. Cache expensive sidebar for 1 hour
2. Cache user-specific content with user-based key
3. Invalidate all "products" tagged fragments

## Additional Context

```blade
{{-- Cache expensive partial for 1 hour --}}
@cache('sidebar-popular-posts', 3600)
    @foreach($popularPosts as $post)
        <x-post-card :post="$post" />
    @endforeach
@endcache

{{-- Cache with dynamic key --}}
@cache("user-{$user->id}-notifications", 300)
    @include('partials.notifications')
@endcache

{{-- Cache with tags for easy invalidation --}}
@cache('homepage-featured', 3600, ['homepage', 'products'])
    @include('partials.featured-products')
@endcache
```

```php
// Programmatic usage
Performance::fragmentCache('expensive-widget', 3600, function () {
    return view('widgets.expensive')->render();
}, tags: ['widgets']);

// Invalidate by tag
Performance::invalidateFragmentsByTag('products');
```

---

**Related Issues:**
- #002 (Configuration)
