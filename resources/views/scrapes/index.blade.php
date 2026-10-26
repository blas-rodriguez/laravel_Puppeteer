@extends('layouts.app')
@section('content')
<div class="page-heading"><div><div class="eyebrow">EL NAVEGADOR HACE EL TRABAJO</div><h1>Datos web, sin copiar y pegar<span>.</span></h1><p>Configurá una extracción y convertí páginas públicas en datos estructurados.</p></div><span class="outline-tag">✦ Scraping workspace</span></div>
<div class="stats-grid">
    <div class="stat"><span class="stat-icon blue">↗</span><div><small>Extracciones totales</small><strong>{{ $total }}</strong></div><span class="stat-detail">Historial local</span></div>
    <div class="stat"><span class="stat-icon green">✓</span><div><small>Completadas</small><strong>{{ $completed }}</strong></div><span class="stat-detail">Datos disponibles</span></div>
    <div class="stat"><span class="stat-icon amber">◷</span><div><small>En proceso</small><strong>{{ $active }}</strong></div><span class="stat-detail">En cola o ejecutando</span></div>
</div>
<div class="workbench">
<section class="panel extractor-panel" aria-labelledby="extractor-title">
    <div class="panel-heading"><div><span class="section-number">01</span><h2 id="extractor-title">Nueva extracción</h2></div><span class="muted small">Configurá tu receta</span></div>
    @if($errors->any())<div class="notice error" role="alert"><strong>Revisá la configuración</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form action="{{ route('scrapes.store') }}" method="post" id="scrape-form">
        @csrf
        <div class="field-label">Empezá con un ejemplo <span>o adaptalo a tu sitio</span></div>
        <div class="preset-grid">
            <button type="button" class="preset {{ old('name', $presets['quotes']['name']) === $presets['quotes']['name'] ? 'selected' : '' }}" data-preset="quotes"><span class="preset-icon">❝</span><span><strong>Citas y autores</strong><small>Contenido con JavaScript</small></span><span class="preset-arrow">↗</span></button>
            <button type="button" class="preset" data-preset="books"><span class="preset-icon">▤</span><span><strong>Catálogo de libros</strong><small>Texto, precios y enlaces</small></span><span class="preset-arrow">↗</span></button>
        </div>
        <div class="form-grid">
            <label class="full">Nombre de la extracción<input name="name" id="name" value="{{ old('name', $presets['quotes']['name']) }}" required maxlength="100" placeholder="Ej. Catálogo de productos"></label>
            <label class="full">URL de origen<div class="input-with-icon"><span>↗</span><input type="url" name="url" id="url" value="{{ old('url', $presets['quotes']['url']) }}" required maxlength="2000" placeholder="https://ejemplo.com/productos"></div><small>Una página pública HTTP o HTTPS. Las redirecciones se validan antes de continuar.</small></label>
            <label>Selector de cada fila<input class="mono" name="item_selector" id="item_selector" value="{{ old('item_selector', $presets['quotes']['item_selector']) }}" required maxlength="500"><small>El elemento que se repite.</small></label>
            <label>Selector de siguiente página<input class="mono" name="next_selector" id="next_selector" value="{{ old('next_selector', $presets['quotes']['next_selector']) }}" maxlength="500" placeholder="Opcional"><small>Un enlace con atributo href.</small></label>
            <label class="full">Campos a extraer <span class="code-tag">JSON</span>
                <textarea name="fields_json" id="fields" class="code-editor" rows="10" required spellcheck="false">{{ old('fields_json', json_encode(old('fields', $presets['quotes']['fields']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</textarea>
                <small>Cada campo lleva <code>name</code>, <code>selector</code> y, opcionalmente, <code>attribute</code>. Los selectores son relativos a cada fila.</small>
            </label>
        </div>
        <div class="form-bottom"><label class="pages-label">Páginas <select name="max_pages" id="max_pages">@for($i=1; $i<=5; $i++)<option value="{{ $i }}" @selected((int) old('max_pages', 2) === $i)>{{ $i }}</option>@endfor</select><span class="muted small">Máx. 100 filas / página</span></label><button class="button primary" type="submit" id="submit-button">Iniciar extracción <span>↗</span></button></div>
        <p class="form-footnote"><span class="online-dot"></span> Chrome headless · Puppeteer · La ejecución se procesa en segundo plano.</p>
    </form>
</section>
<aside class="guide-column">
    <section class="dark-card"><div class="tiny-tag">DE LA WEB A TU BASE DE DATOS</div><h2>Un navegador real.<br>Datos reales.</h2><p>Puppeteer renderiza JavaScript y espera el contenido antes de extraerlo.</p><div class="browser-illustration" aria-hidden="true"><div class="browser-bar"><i></i><i></i><i></i><span>https://tu-proximo-proyecto.dev</span></div><div class="browser-content"><span class="code-line">&lt;article class="producto"&gt;</span><span class="code-line indent">&lt;h2&gt;Tu próximo dato&lt;/h2&gt;</span><span class="code-line">&lt;/article&gt;</span><div class="data-output"><span>↳</span> { "titulo": "Tu próximo dato" } <b>✓</b></div></div></div><div class="dark-card-footer"><span class="online-dot"></span> Del DOM a JSON, con trazabilidad.</div></section>
    <section class="panel guide" id="como-funciona"><div class="eyebrow">ASÍ FUNCIONA</div><h2>Tres pasos. Un flujo claro.</h2><ol class="steps"><li><span>1</span><div><strong>Definí qué necesitás</strong><p>Elegí una URL, el elemento repetido y los campos de cada fila.</p></div></li><li><span>2</span><div><strong>Dejá correr el navegador</strong><p>Laravel encola el trabajo. Puppeteer abre Chromium y recorre las páginas.</p></div></li><li><span>3</span><div><strong>Llevate los resultados</strong><p>Consultá los datos, exportá CSV o JSON y revisá la captura y la traza.</p></div></li></ol></section>
    <div class="tip"><span>↳</span><p><strong>Un buen lugar para practicar</strong>Los ejemplos usan <a href="https://toscrape.com/" target="_blank" rel="noopener noreferrer">ToScrape</a>, sitios preparados para aprender scraping. Para otros sitios, revisá sus condiciones y permisos.</p></div>
</aside>
</div>
<section class="panel history" id="historial"><div class="panel-heading"><div><span class="section-number">02</span><h2>Historial de extracciones</h2></div><span class="count-tag">{{ $total }} ejecuciones</span></div>
@if($runs->isEmpty())
    <div class="empty-state"><span class="empty-icon">⌘</span><h3>Tu primera extracción empieza acá</h3><p>Elegí uno de los ejemplos y ejecutalo.<br>Cada resultado va a quedar guardado en este historial.</p><a href="#extractor-title">Configurar mi primera extracción ↗</a></div>
@else
    <div class="table-scroll"><table><thead><tr><th>Extracción</th><th>Estado</th><th>Filas</th><th>Creada</th><th><span class="sr-only">Ver detalle</span></th></tr></thead><tbody>@foreach($runs as $run)<tr><td><a class="row-title" href="{{ route('scrapes.show', $run) }}">{{ $run->name }}</a><span class="table-url">{{ parse_url($run->url, PHP_URL_HOST) }}</span></td><td><span class="badge {{ $run->status }}"><i></i>{{ $run->statusLabel() }}</span></td><td class="mono">{{ count($run->records ?? []) }}</td><td class="muted">{{ $run->created_at->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }}</td><td><a class="text-link" href="{{ route('scrapes.show', $run) }}" aria-label="Ver {{ $run->name }}">Ver detalle ↗</a></td></tr>@endforeach</tbody></table></div>
    @if($runs->hasPages())<div class="pagination">@if($runs->previousPageUrl())<a href="{{ $runs->previousPageUrl() }}#historial">← Anterior</a>@endif<span>Página {{ $runs->currentPage() }} de {{ $runs->lastPage() }}</span>@if($runs->nextPageUrl())<a href="{{ $runs->nextPageUrl() }}#historial">Siguiente →</a>@endif</div>@endif
@endif
</section>
@endsection
@push('scripts')<script type="application/json" id="presets-data">{!! json_encode($presets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>@endpush
