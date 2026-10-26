<?php

return [
    'node_binary' => env('SCRAPER_NODE_BINARY', 'node'),
    'presets' => [
        'quotes' => [
            'name' => 'Citas y autores', 'url' => 'https://quotes.toscrape.com/js/',
            'item_selector' => '.quote', 'next_selector' => '.next a', 'max_pages' => 2,
            'fields' => [
                ['name' => 'cita', 'selector' => '.text'],
                ['name' => 'autor', 'selector' => '.author'],
                ['name' => 'etiquetas', 'selector' => '.tags'],
            ],
        ],
        'books' => [
            'name' => 'Catálogo de libros', 'url' => 'https://books.toscrape.com/',
            'item_selector' => 'article.product_pod', 'next_selector' => '.next a', 'max_pages' => 2,
            'fields' => [
                ['name' => 'titulo', 'selector' => 'h3 a', 'attribute' => 'title'],
                ['name' => 'precio', 'selector' => '.price_color'],
                ['name' => 'enlace', 'selector' => 'h3 a', 'attribute' => 'href'],
            ],
        ],
    ],
];
