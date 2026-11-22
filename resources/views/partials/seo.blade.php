<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
<link rel="canonical" href="{{ $seo->canonicalUrl }}">
<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:type" content="{{ $seo->ogType }}">
<meta property="og:image" content="{{ $seo->imageUrl }}">
<meta property="og:image:alt" content="{{ $seo->imageAlt }}">
@if ($seo->imageWidth !== null && $seo->imageHeight !== null)
    <meta property="og:image:width" content="{{ $seo->imageWidth }}">
    <meta property="og:image:height" content="{{ $seo->imageHeight }}">
@endif
<meta property="og:url" content="{{ $seo->canonicalUrl }}">
<meta property="og:site_name" content="WayFinder">
<meta property="og:locale" content="id_ID">
@if ($seo->latitude !== null && $seo->longitude !== null)
    <meta property="place:location:latitude" content="{{ json_encode($seo->latitude, JSON_PRESERVE_ZERO_FRACTION) }}">
    <meta property="place:location:longitude" content="{{ json_encode($seo->longitude, JSON_PRESERVE_ZERO_FRACTION) }}">
@endif
<meta name="twitter:card" content="summary_large_image">
@if ($seo->noindex)
    <meta name="robots" content="noindex">
@endif
@foreach ($seo->alternates as $alternate)
    <link rel="alternate" type="{{ $alternate['type'] }}" href="{{ $alternate['url'] }}" title="{{ $alternate['title'] }}">
@endforeach
