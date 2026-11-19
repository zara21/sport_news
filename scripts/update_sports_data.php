<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/sports_import_helpers.php';
require_once __DIR__ . '/../includes/cache_helpers.php';

$type = $argv[1] ?? 'all';
$run = $pdo->prepare('INSERT INTO data_sync_runs (sync_type, status) VALUES (?, "running")');
$run->execute([$type]);
$runId = (int) $pdo->lastInsertId();
try {
    $counts = sports_apply($pdo, $type, __DIR__ . '/../admin/import');
    $changed = array_sum($counts);
    $pdo->prepare('UPDATE data_sync_runs SET status="success", rows_changed=?, message=?, finished_at=NOW() WHERE id=?')
        ->execute([$changed, json_encode($counts), $runId]);
    cache_clear_all();
    fwrite(STDOUT, "Sports data updated: matches={$counts['matches']}, standings={$counts['standings']}\n");
} catch (Throwable $e) {
    $pdo->prepare('UPDATE data_sync_runs SET status="failed", message=?, finished_at=NOW() WHERE id=?')->execute([$e->getMessage(), $runId]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
