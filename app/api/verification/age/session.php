<?php
require_once __DIR__ . '/../../../../config/security.php';
demarrer_session_securisee();
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/intime.php';
require_once __DIR__ . '/../../../services/age-verification-provider.php';
require_once __DIR__ . '/../../../services/intime-access.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'non_connecte']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'methode_invalide']);
    exit;
}

csrf_verify();
$action = $_POST['action'] ?? 'create_session';

if ($action === 'validate_local_test') {
    $session = valider_verification_age_test(
        $pdo,
        (int) $_SESSION['user_id'],
        $_POST['legal_first_name'] ?? null,
        $_POST['legal_last_name'] ?? null,
        $_POST['legal_birth_date'] ?? null
    );
    journaliser_audit($pdo, (int) $_SESSION['user_id'], 'age_verification_local_test_validated_api', 'user', (int) $_SESSION['user_id'], $session['reference'] ?? null);
    echo json_encode($session);
    exit;
}

$session = creer_session_verification_age($pdo, (int) $_SESSION['user_id']);
journaliser_audit($pdo, (int) $_SESSION['user_id'], 'age_verification_session_created_api', 'user', (int) $_SESSION['user_id'], $session['reference']);
echo json_encode($session);
