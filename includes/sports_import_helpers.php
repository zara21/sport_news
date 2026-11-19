<?php

function sports_slug(string $value, string $fallback = 'match'): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    return trim($value, '-') ?: $fallback;
}

function sports_read_matches(string $csvFile): array
{
    if (!is_file($csvFile) || !($handle = fopen($csvFile, 'rb'))) {
        throw new RuntimeException('Matches CSV file was not found or could not be opened.');
    }
    fgetcsv($handle, 0, "\t");
    $records = [];
    $usedSlugs = [];
    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        if (count($row) < 12 || trim((string) $row[0]) === '') continue;
        // FRO ("Final result only") მატჩებს არ ვიღებთ
        if (preg_match('/\bFRO\b/i', (string) $row[1] . ' ' . (string) $row[8])) continue;
        // დრო მხოლოდ HH:MM ფორმატში ("12:00\nFRO" და მსგავსი ნარჩენების გარეშე)
        $row[1] = preg_match('/\b(\d{1,2}:\d{2})\b/', (string) $row[1], $timeMatch) ? $timeMatch[1] : '';
        $baseSlug = sports_slug((string) $row[10], sports_slug($row[2] . '-' . $row[4]));
        $slug = $baseSlug;
        for ($suffix = 2; isset($usedSlugs[$slug]); $suffix++) $slug = $baseSlug . '-' . $suffix;
        $usedSlugs[$slug] = true;
        $records[] = [
            'tournament_name' => trim((string) $row[0]), 'match_time' => trim((string) $row[1]),
            'home_team' => trim((string) $row[2]), 'home_logo' => trim((string) $row[3]),
            'away_team' => trim((string) $row[4]), 'away_logo' => trim((string) $row[5]),
            'home_goals' => is_numeric($row[6]) ? (int) $row[6] : null,
            'away_goals' => is_numeric($row[7]) ? (int) $row[7] : null,
            'match_status' => trim((string) $row[8]), 'live_link' => trim((string) $row[9]),
            'slug' => $slug, 'iframe_live' => trim((string) $row[11]),
        ];
    }
    fclose($handle);
    if (!$records) throw new RuntimeException('Matches CSV contains no valid records.');
    return $records;
}

function sports_read_standings(string $directory): array
{
    $records = [];
    foreach (['premier_league', 'la_liga', 'serie_a', 'ligue_1', 'bundesliga'] as $league) {
        $file = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $league . '_table.csv';
        if (!is_file($file) || !($handle = fopen($file, 'rb'))) continue;
        $header = fgetcsv($handle);
        if (!is_array($header)) { fclose($handle); throw new RuntimeException("{$league}: missing CSV header."); }
        $header = array_map(static function ($column): string {
            $column = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column)));
            return ['p'=>'played','w'=>'wins','d'=>'draws','l'=>'losses','goaldiff'=>'goal_diff','pts'=>'points'][$column] ?? $column;
        }, $header);
        $required = ['position','team','played','wins','draws','losses','score','goal_diff','points'];
        if ($missing = array_diff($required, $header)) {
            fclose($handle); throw new RuntimeException("{$league}: missing columns: " . implode(', ', $missing));
        }
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) { fclose($handle); throw new RuntimeException("{$league}: invalid column count."); }
            $item = array_combine($header, $row);
            $position = (int) preg_replace('/[^0-9]/', '', (string) $item['position']);
            $team = trim((string) $item['team']);
            if ($position < 1 || $position > 50 || $team === '') { fclose($handle); throw new RuntimeException("{$league}: invalid team or position."); }
            $records[] = [
                'league_name'=>$league, 'position'=>$position, 'team'=>$team,
                'played'=>(int)$item['played'], 'wins'=>(int)$item['wins'], 'draws'=>(int)$item['draws'],
                'losses'=>(int)$item['losses'], 'score'=>trim((string)$item['score']),
                'goal_diff'=>(int)$item['goal_diff'], 'points'=>(int)$item['points'],
            ];
        }
        fclose($handle);
    }
    if (!$records) throw new RuntimeException('Standings CSV files contain no valid records.');
    return $records;
}

function sports_diff(array $incoming, array $existing, string $key): array
{
    $new = [];
    foreach ($incoming as $row) $new[(string) $row[$key]] = $row;
    $old = [];
    foreach ($existing as $row) $old[(string) $row[$key]] = $row;
    $added = $updated = 0;
    foreach ($new as $id => $row) {
        if (!isset($old[$id])) $added++;
        elseif (json_encode(array_values($row)) !== json_encode(array_values(array_intersect_key($old[$id], $row)))) $updated++;
    }
    return ['incoming'=>count($new), 'added'=>$added, 'updated'=>$updated, 'removed'=>count(array_diff_key($old, $new)), 'sample'=>array_slice(array_values($new), 0, 5)];
}

function sports_preview(PDO $pdo, string $type, string $directory): array
{
    $result = [];
    if ($type === 'all' || $type === 'matches') {
        $rows = sports_read_matches($directory . '/flashscore_matches_with_logos_and_links.csv');
        $existing = $pdo->query('SELECT tournament_name, match_time, home_team, home_logo, away_team, away_logo, home_goals, away_goals, match_status, live_link, slug, iframe_live FROM matches')->fetchAll();
        $result['matches'] = sports_diff($rows, $existing, 'slug');
    }
    if ($type === 'all' || $type === 'standings') {
        $rows = sports_read_standings($directory);
        foreach ($rows as &$row) $row['_key'] = $row['league_name'] . '|' . mb_strtolower($row['team'], 'UTF-8');
        unset($row);
        $existing = $pdo->query('SELECT league_name, position, team, played, wins, draws, losses, score, goal_diff, points FROM league_standings')->fetchAll();
        foreach ($existing as &$row) $row['_key'] = $row['league_name'] . '|' . mb_strtolower($row['team'], 'UTF-8');
        unset($row);
        $result['standings'] = sports_diff($rows, $existing, '_key');
    }
    if (!$result) throw new RuntimeException('Unknown import type.');
    return $result;
}

function sports_apply(PDO $pdo, string $type, string $directory): array
{
    $counts = ['matches'=>0, 'standings'=>0];
    $pdo->beginTransaction();
    try {
        if ($type === 'all' || $type === 'matches') {
            $rows = sports_read_matches($directory . '/flashscore_matches_with_logos_and_links.csv');
            $pdo->exec('DELETE FROM matches');
            $stmt = $pdo->prepare('INSERT INTO matches (tournament_name, match_time, home_team, home_logo, away_team, away_logo, home_goals, away_goals, match_status, live_link, slug, iframe_live) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($rows as $r) $stmt->execute(array_values($r));
            $counts['matches'] = count($rows);
        }
        if ($type === 'all' || $type === 'standings') {
            $rows = sports_read_standings($directory);
            $leagues = array_values(array_unique(array_column($rows, 'league_name')));
            $delete = $pdo->prepare('DELETE FROM league_standings WHERE league_name = ?');
            foreach ($leagues as $league) $delete->execute([$league]);
            $stmt = $pdo->prepare('INSERT INTO league_standings (league_name, position, team, played, wins, draws, losses, score, goal_diff, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($rows as $r) $stmt->execute(array_values($r));
            $counts['standings'] = count($rows);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $counts;
}
