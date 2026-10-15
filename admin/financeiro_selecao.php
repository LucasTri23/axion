<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_financeiro_selecao.php';
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Use POST.']);
    exit;
}
csrfVerify();
$ids = array_values(array_unique(array_filter(array_map('intval', array_slice((array)($_POST['ids'] ?? []), 0, 1000)), fn($id) => $id > 0)));
$action = $_POST['action'] ?? '';
try {
    $current = financeiroSelecionados(db());
    $selected = array_column($current['items'], 'id');
    if ($action === 'adicionar') $selected = array_values(array_unique(array_merge($selected, $ids)));
    elseif ($action === 'remover') $selected = array_values(array_diff($selected, $ids));
    elseif ($action === 'limpar') $selected = [];
    else { http_response_code(400); echo json_encode(['error' => 'Ação inválida.']); exit; }
    if (count($selected) > 1000) {
        http_response_code(422);
        echo json_encode(['error' => 'Selecione até 1.000 contas por planejamento.']);
        exit;
    }
    $_SESSION['financeiro_selecao'] = $selected;
    echo json_encode(financeiroSelecionados(db()), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('[Axion Financeiro] Falha na seleção: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível atualizar a seleção. Tente novamente.']);
}
