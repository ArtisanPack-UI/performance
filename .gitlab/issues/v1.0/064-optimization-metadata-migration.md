# Create optimization metadata migration

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Phase::9" ~"Area::Backend"

## Problem Statement

Optimization data needs to be stored with media records.

## Proposed Solution

Create migration that adds optimization columns to media table (if using media-library).

## Acceptance Criteria

- [ ] Add `dominant_color` column
- [ ] Add `optimization_status` column
- [ ] Add `optimized_at` timestamp
- [ ] Add `optimized_formats` JSON column
- [ ] Add `optimized_sizes` JSON column
- [ ] Migration checks for media table existence
- [ ] Unit tests for migration

## Use Cases

1. Store dominant color with media
2. Track optimization status
3. Store available formats and sizes

## Additional Context

```php
// Migration
Schema::table('media', function (Blueprint $table) {
    $table->string('dominant_color', 7)->nullable()->after('metadata');
    $table->string('optimization_status')->default('pending')->after('dominant_color');
    $table->timestamp('optimized_at')->nullable()->after('optimization_status');
    $table->json('optimized_formats')->nullable()->after('optimized_at');
    $table->json('optimized_sizes')->nullable()->after('optimized_formats');
});
```

**Optimization Status Values:**
- `pending` - Not yet processed
- `processing` - Currently being optimized
- `completed` - All optimizations done
- `failed` - Optimization failed

**Optimized Formats JSON:**
```json
{
    "webp": "/storage/media/1/optimized/image.webp",
    "avif": "/storage/media/1/optimized/image.avif"
}
```

**Optimized Sizes JSON:**
```json
{
    "320": "/storage/media/1/optimized/image-320.jpg",
    "640": "/storage/media/1/optimized/image-640.jpg",
    "1024": "/storage/media/1/optimized/image-1024.jpg"
}
```

---

**Related Issues:**
- #062 (Media Library Listeners)
