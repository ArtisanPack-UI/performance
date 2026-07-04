{{--
    Lazy loading with a dominant-color placeholder.

    <x-perf-lazy-image> ships a data-src attribute, a solid-color
    placeholder computed from the source image's dominant color, and an
    IntersectionObserver bootstrap that swaps in the real src when the
    element enters the viewport.

    Requires:
      'features' => [
          'image_optimization' => true,
          'lazy_loading'       => true,
      ],
--}}

{{-- Basic lazy image with a computed dominant-color placeholder. --}}
<x-perf-lazy-image
    :src="asset( 'uploads/product-1.jpg' )"
    alt="Handmade ceramic mug"
    width="800"
    height="600"
/>

{{-- Custom threshold — start loading 200px before the image enters the viewport. --}}
<x-perf-lazy-image
    :src="asset( 'uploads/product-2.jpg' )"
    alt="Handmade ceramic bowl"
    width="800"
    height="600"
    :threshold="200"
/>

{{-- Force above-the-fold images to load eagerly and hint fetchpriority. --}}
<x-perf-lazy-image
    :src="asset( 'uploads/hero.jpg' )"
    alt="Store hero"
    width="1920"
    height="800"
    loading="eager"
    fetchpriority="high"
/>
