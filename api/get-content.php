<?php
/**
 * API pública: retorna textos e imagens customizados do site
 * Chamado pelo index.html no carregamento da página
 */
require_once dirname(__DIR__) . '/admin/_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // cache 5 min (CDN / browser)
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['ok' => false]));
}

try {
    $db = db();

    // Textos
    $texts = [];
    foreach ($db->query("SELECT content_key, content_value FROM site_content")->fetchAll() as $r) {
        $texts[$r['content_key']] = $r['content_value'];
    }

    // Imagens (só a URL pública)
    $images = [];
    foreach ($db->query("SELECT image_key, filename FROM site_images")->fetchAll() as $r) {
        $images[$r['image_key']] = UPLOAD_URL . $r['filename'];
    }

    echo json_encode(['ok' => true, 'texts' => $texts, 'images' => $images]);
} catch (Throwable $e) {
    error_log('[Axion Content] ' . $e->getMessage());
    // Retorna vazio — o site usa os padrões embutidos no HTML
    echo json_encode(['ok' => true, 'texts' => [], 'images' => []]);
}
