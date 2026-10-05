<?php

// Plan limits follow section 31 of the gap analysis. null = unlimited.
// Prices are placeholders until validated with customer interviews.
return [
    'free' => [
        'name' => 'Free', 'price' => 0,
        'people' => 100, 'photos' => 250, 'storage_mb' => 500, 'contributors' => 3,
        'features' => [],
    ],
    'family' => [
        'name' => 'Family', 'price' => 59,
        'people' => 1000, 'photos' => null, 'storage_mb' => 10 * 1024, 'contributors' => null,
        'features' => ['documents', 'privacy'],
    ],
    'heritage' => [
        'name' => 'Heritage', 'price' => 99,
        'people' => null, 'photos' => null, 'storage_mb' => 100 * 1024, 'contributors' => null,
        'features' => ['documents', 'privacy', 'video', 'ai', 'gedcom', 'archive_export'],
    ],
    'legacy' => [
        'name' => 'Legacy', 'price' => 199,
        'people' => null, 'photos' => null, 'storage_mb' => 500 * 1024, 'contributors' => null,
        'features' => ['documents', 'privacy', 'video', 'ai', 'gedcom', 'archive_export', 'book'],
    ],
];
