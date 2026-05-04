<?php
declare(strict_types=1);

function creer_session_verification_age(PDO $pdo, int $user_id): array
{
    $reference = 'local_pending_' . $user_id . '_' . bin2hex(random_bytes(8));

    $stmt = $pdo->prepare("
        INSERT INTO age_verifications (user_id, provider, provider_reference_id, status)
        VALUES (?, 'provider_a_configurer', ?, 'pending')
    ");
    $stmt->execute([$user_id, $reference]);

    $stmt = $pdo->prepare("
        UPDATE users
        SET verification_provider = 'provider_a_configurer',
            verification_reference_id = ?,
            verification_status = 'pending',
            age_verified = 0
        WHERE id = ?
    ");
    $stmt->execute([$reference, $user_id]);

    return [
        'reference' => $reference,
        'status' => 'pending',
        'message' => 'Session creee. Branche ici ton prestataire KYC pour finaliser la verification.',
    ];
}

function valider_verification_age_test(
    PDO $pdo,
    int $user_id,
    ?string $legal_first_name = null,
    ?string $legal_last_name = null,
    ?string $legal_birth_date = null
): array
{
    if (!function_exists('intime_test_verification_active') || !intime_test_verification_active()) {
        return [
            'ok' => false,
            'message' => 'La validation locale est desactivee sur cet environnement.',
        ];
    }

    $reference = 'local_verified_' . $user_id . '_' . bin2hex(random_bytes(8));
    $legal_first_name = trim((string) $legal_first_name);
    $legal_last_name = trim((string) $legal_last_name);
    $legal_birth_date = trim((string) $legal_birth_date);
    $birth_date_valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $legal_birth_date) ? $legal_birth_date : null;

    $stmt = $pdo->prepare("
        INSERT INTO age_verifications
            (user_id, provider, provider_reference_id, status, age_verified, verified_at, expires_at)
        VALUES (?, 'local_test', ?, 'verified', 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))
    ");
    $stmt->execute([$user_id, $reference]);

    $stmt = $pdo->prepare("
        UPDATE users
        SET age_verified = 1,
            age_verified_at = NOW(),
            prenom = CASE WHEN ? <> '' THEN ? ELSE prenom END,
            nom = CASE WHEN ? <> '' THEN ? ELSE nom END,
            date_naissance = COALESCE(?, date_naissance),
            verification_provider = 'local_test',
            verification_reference_id = ?,
            verification_status = 'verified',
            verification_expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR),
            adult_access_revoked_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([$legal_first_name, $legal_first_name, $legal_last_name, $legal_last_name, $birth_date_valid, $reference, $user_id]);

    return [
        'ok' => true,
        'reference' => $reference,
        'message' => 'Verification 18+ simulee pour le test local.',
    ];
}

function statut_verification_age(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare("
        SELECT verification_status, age_verified, age_verified_at, verification_expires_at
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $status = $stmt->fetch();

    return $status ?: [
        'verification_status' => 'not_started',
        'age_verified' => 0,
        'age_verified_at' => null,
        'verification_expires_at' => null,
    ];
}
