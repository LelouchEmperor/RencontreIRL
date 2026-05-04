<?php
declare(strict_types=1);

function intime_intentions_disponibles(): array
{
    return [
        'Discussion d abord',
        'Sortie publique avant prive',
        'Rencontre calme',
        'Feeling',
        'Relation suivie',
        'Confidentialite forte',
        'Affinite physique importante',
        'Connexion emotionnelle',
        'Cadre tres discret',
        'Sans pression',
        'Limites explicites',
        'Rencontre occasionnelle assumee',
        'Experience sensuelle mais respectueuse',
        'Exploration progressive',
        'Complicite avant tout',
    ];
}

function intime_premieres_etapes(): array
{
    return [
        'social_first' => 'Faire une sortie publique avant',
        'chat_first' => 'Discuter avant tout',
        'slow_match' => 'Avancer lentement',
        'mutual_interest_first' => 'Attendre un interet mutuel',
        'voice_first' => 'Appel audio avant rencontre',
        'public_place_first' => 'Lieu public obligatoire au debut',
    ];
}

function intime_normaliser_liste(array $valeurs, array $valides): array
{
    $selection = [];

    foreach ($valeurs as $valeur) {
        $valeur = trim((string) $valeur);

        if (in_array($valeur, $valides, true)) {
            $selection[$valeur] = $valeur;
        }
    }

    return array_values($selection);
}

function intime_liste_depuis_chaine(?string $chaine): array
{
    $items = array_map('trim', explode(',', (string) $chaine));
    return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
}

function intime_compatibilite(array $viewer, array $target, array $viewer_interets = [], array $target_interets = []): array
{
    $score = 30;
    $raisons = [];

    $viewer_intentions = intime_liste_depuis_chaine($viewer['intentions'] ?? '');
    $target_intentions = intime_liste_depuis_chaine($target['intentions'] ?? '');
    $intentions_communes = array_values(array_intersect($viewer_intentions, $target_intentions));

    if (!empty($intentions_communes)) {
        $score += min(30, count($intentions_communes) * 12);
        $raisons[] = count($intentions_communes) . ' intention(s) compatible(s)';
    }

    $interets_communs = array_values(array_intersect($viewer_interets, $target_interets));
    if (!empty($interets_communs)) {
        $score += min(25, count($interets_communs) * 8);
        $raisons[] = count($interets_communs) . ' centre(s) d interet commun(s)';
    }

    if (!empty($viewer['zone_approximative']) && !empty($target['zone_approximative'])) {
        $viewer_zone = function_exists('mb_strtolower') ? mb_strtolower((string) $viewer['zone_approximative'], 'UTF-8') : strtolower((string) $viewer['zone_approximative']);
        $target_zone = function_exists('mb_strtolower') ? mb_strtolower((string) $target['zone_approximative'], 'UTF-8') : strtolower((string) $target['zone_approximative']);

        if ($viewer_zone === $target_zone || str_contains($target_zone, $viewer_zone) || str_contains($viewer_zone, $target_zone)) {
            $score += 15;
            $raisons[] = 'zone proche';
        }
    }

    if (($viewer['preferred_first_step'] ?? '') === ($target['preferred_first_step'] ?? '') && !empty($target['preferred_first_step'])) {
        $score += 10;
        $raisons[] = 'meme rythme de prise de contact';
    }

    $score = max(0, min(100, $score));

    if (empty($raisons)) {
        $raisons[] = 'profil verifie et visible';
    }

    return [
        'score' => $score,
        'raisons' => $raisons,
        'intentions_communes' => $intentions_communes,
        'interets_communs' => $interets_communs,
    ];
}

function intime_conversation_pair(int $user_a, int $user_b): array
{
    $ids = [$user_a, $user_b];
    sort($ids);
    return [(int) $ids[0], (int) $ids[1]];
}

function intime_creer_ou_recuperer_conversation(PDO $pdo, int $user_a, int $user_b): int
{
    [$one, $two] = intime_conversation_pair($user_a, $user_b);

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO intime_conversations (user_one_id, user_two_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$one, $two]);

    $stmt = $pdo->prepare("
        SELECT id
        FROM intime_conversations
        WHERE user_one_id = ? AND user_two_id = ?
        LIMIT 1
    ");
    $stmt->execute([$one, $two]);

    return (int) $stmt->fetchColumn();
}
