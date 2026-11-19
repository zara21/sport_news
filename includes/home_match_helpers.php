<?php
require_once __DIR__ . '/cache_helpers.php';

function home_match_feed(PDO $pdo): array
{
    return cache_remember('home.matches.v4', 90, function () use ($pdo): array {
        $stmt = $pdo->query("SELECT id, tournament_name, match_time, home_team, home_logo,
                away_team, away_logo, home_goals, away_goals, match_status, slug
            FROM matches
            WHERE LOWER(TRIM(COALESCE(match_status, ''))) NOT IN (
                'finished', 'after pen.', 'postponed', 'cancelled', 'canceled'
            )
            ORDER BY
                CASE
                    WHEN LOWER(TRIM(match_status)) NOT IN ('', 'finished', 'after pen.') THEN 0
                    WHEN match_status IS NULL OR TRIM(match_status) = '' THEN 1
                    ELSE 2
                END,
                CASE WHEN match_time IS NULL OR TRIM(match_time) = '' THEN 1 ELSE 0 END,
                match_time ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    });
}
