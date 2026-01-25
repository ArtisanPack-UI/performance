# Implement embed optimizer

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Phase::4" ~"Area::Backend"

## Problem Statement

Third-party embeds (YouTube, Twitter) are heavy. Lazy loading them with facades improves performance.

## Proposed Solution

Create `EmbedOptimizer` service and `<x-perf-embed>` component for lazy-loading embeds.

## Acceptance Criteria

- [ ] Create `src/Services/EmbedOptimizer.php`
- [ ] Support YouTube embeds
- [ ] Support Twitter/X embeds
- [ ] Support Vimeo embeds
- [ ] Generate facade placeholders with thumbnails
- [ ] Lazy load actual embed on interaction
- [ ] Click-to-load behavior
- [ ] Accessible play buttons
- [ ] Create `<x-perf-embed>` component
- [ ] Unit tests for embed optimization

## Use Cases

1. Developer embeds YouTube video with lazy loading
2. Facade shows thumbnail until user clicks
3. Actual iframe loads on interaction

## Additional Context

```blade
{{-- Lazy load YouTube embed --}}
<x-perf-embed
    provider="youtube"
    id="dQw4w9WgXcQ"
    :lazy="true"
/>

{{-- Lazy load with facade placeholder --}}
<x-perf-embed
    provider="twitter"
    id="1234567890"
    :lazy="true"
    :show-facade="true"
/>
```

**Output (before interaction):**
```html
<div class="perf-embed-facade" data-provider="youtube" data-id="dQw4w9WgXcQ">
    <img src="/placeholders/youtube-dQw4w9WgXcQ.jpg" alt="Video thumbnail">
    <button class="play-button" aria-label="Play video">▶</button>
</div>
```

**After click:** Loads actual embed iframe.

---

**Related Issues:**
- #003 (Performance Facade)
