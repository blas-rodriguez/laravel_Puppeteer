<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Workspace') · ScrapeLab Puppeteer</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
<a href="#main" class="skip-link">Saltar al contenido</a>
<aside class="sidebar">
    <a class="brand" href="{{ route('scrapes.index') }}"><span class="brand-icon">s<span>↗</span></span>scrape<span class="brand-light">lab</span><span class="brand-dot">.</span></a>
    <div class="workspace-label">TU ESPACIO DE TRABAJO</div>
    <nav aria-label="Navegación principal">
        <a class="nav-link active" href="{{ route('scrapes.index') }}"><span>▦</span> Extractor <span class="nav-arrow">↗</span></a>
        <a class="nav-link" href="{{ route('scrapes.index') }}#historial"><span>◷</span> Historial</a>
        <a class="nav-link" href="{{ route('scrapes.index') }}#como-funciona"><span>⌘</span> Cómo funciona</a>
    </nav>
    <div class="sidebar-note"><span class="tiny-tag">PUPPETEER EDITION</span><p>Chrome, bajo<br>tu control.</p><div class="stack-tags"><span>Laravel</span><span>Puppeteer</span></div></div>
    <div class="sidebar-footer"><span class="avatar">BR</span><div><strong>Portfolio workspace</strong><small>Blas Rodríguez · Local demo</small></div><span class="online-dot"></span></div>
</aside>
<div class="app-shell">
    <header class="topbar"><div><span class="breadcrumb">Workspace</span><span class="slash">/</span>@yield('breadcrumb', 'Extractor')</div><div class="environment"><span class="edition">Puppeteer</span><span class="online-dot"></span> Entorno local <span class="version">v1.0</span></div></header>
    <main id="main">
        @if(session('message'))<div class="notice success" role="status">{{ session('message') }}</div>@endif
        @yield('content')
        <footer class="page-footer"><span>Construido con Laravel + Puppeteer.</span><span>Explorá. Extraé. Entendé.</span></footer>
    </main>
</div>
@stack('scripts')
</body>
</html>
