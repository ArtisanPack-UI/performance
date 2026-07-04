{{--
    Responsive image example.

    Uses the shipped <x-perf-responsive-image> component. The component
    reads registered image sizes from
    artisanpack.performance.image_optimization.sizes and generates a
    srcset covering every size, plus a sensible sizes attribute.

    Requires:
      'features' => [
          'image_optimization' => true,
      ],
--}}

<x-perf-responsive-image
    :src="asset( 'uploads/hero.jpg' )"
    alt="Product hero shot"
    sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
    fetchpriority="high"
    class="w-full h-auto"
/>

{{--
    Rendered output (illustrative):

    <picture>
        <source
            type="image/avif"
            srcset="/uploads/hero-320.avif 320w, /uploads/hero-768.avif 768w, /uploads/hero-1440.avif 1440w"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
        />
        <source
            type="image/webp"
            srcset="/uploads/hero-320.webp 320w, /uploads/hero-768.webp 768w, /uploads/hero-1440.webp 1440w"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
        />
        <img
            src="/uploads/hero.jpg"
            srcset="/uploads/hero-320.jpg 320w, /uploads/hero-768.jpg 768w, /uploads/hero-1440.jpg 1440w"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            alt="Product hero shot"
            fetchpriority="high"
            class="w-full h-auto"
        />
    </picture>
--}}
