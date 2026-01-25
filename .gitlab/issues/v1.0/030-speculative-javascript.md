# Create speculative loading JavaScript

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::4" ~"Area::Frontend"

## Problem Statement

Speculative loading features need JavaScript for dynamic behavior and fallbacks.

## Proposed Solution

Create JavaScript module for speculative loading functionality.

## Acceptance Criteria

- [ ] Create `resources/js/speculative-rules.js`
- [ ] Feature detection for Speculation Rules API
- [ ] Fallback prefetching for unsupported browsers
- [ ] Dynamic rule injection
- [ ] Embed facade click handling
- [ ] Lazy embed loading
- [ ] Published with package assets
- [ ] Unit tests for JavaScript

## Use Cases

1. Fallback prefetch for older browsers
2. Dynamic speculation rule updates
3. Click-to-load embed handling

## Additional Context

```javascript
// speculative-rules.js
class SpeculativeLoader {
    constructor() {
        this.supportsSpeculationRules = 'speculationrules' in HTMLScriptElement.prototype;
    }

    init() {
        if (!this.supportsSpeculationRules) {
            this.initFallbackPrefetch();
        }
        this.initEmbedFacades();
    }

    initFallbackPrefetch() {
        // Use <link rel="prefetch"> for older browsers
        document.querySelectorAll('a[data-prefetch]').forEach(link => {
            const prefetch = document.createElement('link');
            prefetch.rel = 'prefetch';
            prefetch.href = link.href;
            document.head.appendChild(prefetch);
        });
    }

    initEmbedFacades() {
        document.querySelectorAll('.perf-embed-facade').forEach(facade => {
            facade.addEventListener('click', () => this.loadEmbed(facade));
        });
    }
}
```

---

**Related Issues:**
- #026 (SpeculativeRulesGenerator)
- #028 (Embed Optimizer)
