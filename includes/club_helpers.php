<?php

function club_tag_alias_map(): array
{
    return [
        'AC Milan' => ['AC Milan', 'მილანი'],
        'Arsenal' => ['Arsenal', 'არსენალი'],
        'Atlético Madrid' => ['Atlético Madrid', 'ატლეტიკო მადრიდი'],
        'Bayern Munich' => ['Bayern Munich', 'მიუნხენის ბაიერნი'],
        'Borussia Dortmund' => ['Borussia Dortmund', 'დორტმუნდი'],
        'Chelsea' => ['Chelsea', 'ჩელსი'],
        'FC Barcelona' => ['FC Barcelona', 'ბარსელონა'],
        'Inter Milan' => ['Inter Milan', 'ინტერი'],
        'Juventus' => ['Juventus', 'იუვენტუსი'],
        'Liverpool' => ['Liverpool', 'ლივერპული'],
        'Manchester City' => ['Manchester City', 'მანჩესტერ სიტი'],
        'Manchester United' => ['Manchester United', 'მანჩესტერ იუნაიტედი'],
        'Paris Saint-Germain' => ['Paris Saint-Germain', 'PSG', 'პსჟ'],
        'Real Madrid' => ['Real Madrid', 'რეალ მადრიდი'],
        'Tottenham Hotspur' => ['Tottenham Hotspur', 'ტოტენჰემი'],
    ];
}

function canonical_club_name(string $value): ?string
{
    $needle = mb_strtolower(trim($value), 'UTF-8');
    if ($needle === '') return null;
    foreach (club_tag_alias_map() as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            if (mb_strtolower($alias, 'UTF-8') === $needle) return $canonical;
        }
    }
    return null;
}

function club_tag_aliases(string $club): array
{
    $canonical = canonical_club_name($club);
    return $canonical ? club_tag_alias_map()[$canonical] : [];
}

function database_club_aliases(PDO $pdo, string $club): array
{
    $canonical=canonical_club_name($club)?:$club;
    $stmt=$pdo->prepare('SELECT DISTINCT x.name FROM (SELECT t.name,t.id tag_id FROM tags t UNION ALL SELECT ta.alias name,ta.tag_id FROM tag_aliases ta) x JOIN tags t ON t.id=x.tag_id WHERE t.name=? OR x.name=?');
    $stmt->execute([$canonical,$club]);
    return array_values(array_unique(array_merge([$canonical],club_tag_aliases($canonical),$stmt->fetchAll(PDO::FETCH_COLUMN))));
}
