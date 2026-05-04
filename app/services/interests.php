<?php
declare(strict_types=1);

function interets_disponibles(): array
{
    return [
        'Cuisine',
        'Sport',
        'Jeux video',
        'Jeux de societe',
        'Culture',
        'Musique',
        'Balade',
        'Randonnee',
        'Bar',
        'Cafe',
        'Cinema',
        'Voyage',
        'Photo',
        'Bien-etre',
        'Lecture',
        'Tech',
    ];
}

function normaliser_interets(array $interets): array
{
    $interets_valides = interets_disponibles();
    $selection = [];

    foreach ($interets as $interet) {
        $interet = trim((string) $interet);

        if (in_array($interet, $interets_valides, true)) {
            $selection[$interet] = $interet;
        }
    }

    return array_values($selection);
}

function interets_utilisateur(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare("
        SELECT interest
        FROM user_interests
        WHERE user_id = ?
        ORDER BY interest ASC
    ");
    $stmt->execute([$user_id]);

    return array_column($stmt->fetchAll(), 'interest');
}

function enregistrer_interets_utilisateur(PDO $pdo, int $user_id, array $interets): void
{
    $interets = normaliser_interets($interets);

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("DELETE FROM user_interests WHERE user_id = ?");
        $stmt->execute([$user_id]);

        if (!empty($interets)) {
            $stmt = $pdo->prepare("INSERT INTO user_interests (user_id, interest) VALUES (?, ?)");

            foreach ($interets as $interet) {
                $stmt->execute([$user_id, $interet]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function activite_correspond_interets(?string $activite, array $interets): bool
{
    $activite = trim((string) $activite);

    if ($activite === '' || empty($interets)) {
        return false;
    }

    $activite_normalisee = function_exists('mb_strtolower') ? mb_strtolower($activite, 'UTF-8') : strtolower($activite);

    foreach ($interets as $interet) {
        $interet_normalise = function_exists('mb_strtolower') ? mb_strtolower($interet, 'UTF-8') : strtolower($interet);

        if (str_contains($activite_normalisee, $interet_normalise) || str_contains($interet_normalise, $activite_normalisee)) {
            return true;
        }
    }

    return false;
}
