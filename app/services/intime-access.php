<?php
declare(strict_types=1);

function charger_acces_intime(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare("
        SELECT id, account_status, account_safety_status, age_verified, age_verified_at,
               verification_status, verification_expires_at, adult_access_revoked_at,
               safety_onboarding_completed
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        return [
            'authenticated' => false,
            'can_access' => false,
            'reason' => 'Utilisateur introuvable.',
        ];
    }

    $account_status = $user['account_status'] ?? 'active';
    $safety_status = $user['account_safety_status'] ?? 'active';
    $expires_at = !empty($user['verification_expires_at']) ? strtotime((string) $user['verification_expires_at']) : null;

    $age_ok = !empty($user['age_verified'])
        && ($user['verification_status'] ?? '') === 'verified'
        && empty($user['adult_access_revoked_at'])
        && (!$expires_at || $expires_at > time());

    $account_ok = ($account_status === null || $account_status === '' || $account_status === 'active')
        && $safety_status === 'active';

    $onboarding_ok = !empty($user['safety_onboarding_completed']);

    $reason = null;
    if (!$account_ok) {
        $reason = 'Ton compte est limite ou en cours de verification.';
    } elseif (!$age_ok) {
        $reason = 'La verification 18+ est requise pour acceder a cet espace.';
    } elseif (!$onboarding_ok) {
        $reason = 'L onboarding securite doit etre termine avant d acceder au mode intime.';
    }

    return [
        'authenticated' => true,
        'can_access' => $account_ok && $age_ok && $onboarding_ok,
        'age_verified' => $age_ok,
        'onboarding_completed' => $onboarding_ok,
        'account_ok' => $account_ok,
        'verification_status' => $user['verification_status'] ?? 'not_started',
        'reason' => $reason,
    ];
}

function exiger_connexion(): int
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . app_url('app/auth/connexion.php'));
        exit;
    }

    return (int) $_SESSION['user_id'];
}

function exiger_acces_intime(PDO $pdo): array
{
    $user_id = exiger_connexion();
    $acces = charger_acces_intime($pdo, $user_id);

    if (!$acces['can_access']) {
        header('Location: ' . app_url('app/intime/index.php'));
        exit;
    }

    $acces['user_id'] = $user_id;
    return $acces;
}

function consent_ip_hash(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return $ip !== '' ? hash('sha256', $ip) : null;
}

function consent_user_agent_hash(): ?string
{
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return $agent !== '' ? hash('sha256', $agent) : null;
}

function enregistrer_consentement(PDO $pdo, int $user_id, string $type, string $version, bool $accepted): void
{
    $stmt = $pdo->prepare("
        INSERT INTO consent_events (user_id, type, version, accepted, ip_hash, user_agent_hash)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        substr($type, 0, 80),
        substr($version, 0, 30),
        $accepted ? 1 : 0,
        consent_ip_hash(),
        consent_user_agent_hash(),
    ]);
}

function journaliser_audit(PDO $pdo, ?int $actor_user_id, string $action, ?string $target_type = null, ?int $target_id = null, ?string $details = null): void
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (actor_user_id, action, target_type, target_id, details, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $actor_user_id,
        substr($action, 0, 100),
        $target_type ? substr($target_type, 0, 60) : null,
        $target_id,
        $details ? substr($details, 0, 255) : null,
        consent_ip_hash(),
    ]);
}
