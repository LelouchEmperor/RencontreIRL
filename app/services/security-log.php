<?php
declare(strict_types=1);

function security_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr((string) $ip, 0, 45);
}

function security_user_agent(): ?string
{
    $agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return $agent !== '' ? substr($agent, 0, 255) : null;
}

function journaliser_evenement_securite(
    PDO $pdo,
    string $event_type,
    ?int $user_id = null,
    ?string $email = null,
    ?string $details = null
): void {
    $stmt = $pdo->prepare("
        INSERT INTO security_events (user_id, email, event_type, ip_address, user_agent, details)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $email ? substr($email, 0, 255) : null,
        substr($event_type, 0, 80),
        security_client_ip(),
        security_user_agent(),
        $details ? substr($details, 0, 255) : null,
    ]);
}

function enregistrer_tentative_connexion(PDO $pdo, ?string $email, bool $success): void
{
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, ip_address, success)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $email ? strtolower(substr(trim($email), 0, 255)) : null,
        security_client_ip(),
        $success ? 1 : 0,
    ]);
}

function connexion_temporairement_bloquee(PDO $pdo, ?string $email, int $max_attempts = 5, int $minutes = 15): bool
{
    $email = $email ? strtolower(substr(trim($email), 0, 255)) : null;
    $ip = security_client_ip();
    $minutes = max(1, min(1440, $minutes));

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM login_attempts
        WHERE success = 0
        AND created_at >= (NOW() - INTERVAL $minutes MINUTE)
        AND (ip_address = ? OR (email IS NOT NULL AND email = ?))
    ");
    $stmt->execute([$ip, $email]);

    return (int) $stmt->fetchColumn() >= $max_attempts;
}

function nettoyer_anciennes_tentatives_connexion(PDO $pdo): void
{
    $pdo->exec("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 7 DAY)");
}
