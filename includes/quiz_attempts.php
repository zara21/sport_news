<?php

function start_quiz_attempt(string $type, array $attempt): string
{
    $attempts = $_SESSION[$type] ?? [];
    $attempts = array_slice($attempts, -11, null, true);
    $token = bin2hex(random_bytes(16));
    $attempts[$token] = $attempt;
    $_SESSION[$type] = $attempts;
    return $token;
}
