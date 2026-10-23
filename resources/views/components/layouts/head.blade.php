@props(['title' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#10382a">
<meta name="color-scheme" content="light">
<meta name="description" content="PowerNest Estate — visitors, electricity, bills and estate services in one place.">
<title>{{ $title ? $title.' · ' : '' }}PowerNest Estate</title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="PowerNest">
@vite(['resources/css/app.css', 'resources/js/app.js'])
