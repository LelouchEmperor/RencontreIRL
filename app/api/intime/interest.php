<?php
require_once __DIR__ . '/../../../config/security.php';
demarrer_session_securisee();
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../services/intime-access.php';
require_once __DIR__ . '/../../services/intime-matching.php';

header('Content-Type: application/json; charset=utf-8');
$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'methode_invalide']);
    exit;
}

csrf_verify();
$target_user_id = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;

$stmt = $pdo->prepare("
    SELECT ip.user_id
    FROM user_profiles_intime ip
    JOIN users u ON u.id = ip.user_id
    LEFT JOIN privacy_settings ps ON ps.user_id = ip.user_id
    WHERE ip.user_id = ?
    AND ip.user_id <> ?
    AND u.age_verified = 1
    AND u.verification_status = 'verified'
    AND u.safety_onboarding_completed = 1
    AND (u.account_status IS NULL OR u.account_status = '' OR u.account_status = 'active')
    AND (u.account_safety_status IS NULL OR u.account_safety_status = '' OR u.account_safety_status = 'active')
    AND (ps.hide_from_search IS NULL OR ps.hide_from_search = 0)
");
$stmt->execute([$target_user_id, $user_id]);
$target = $stmt->fetch();

if (!$target) {
    http_response_code(400);
    echo json_encode(['error' => 'profil_invalide']);
    exit;
}

$stmt = $pdo->prepare("INSERT IGNORE INTO intime_profile_interests (requester_id, target_user_id) VALUES (?, ?)");
$stmt->execute([$user_id, $target_user_id]);

journaliser_audit($pdo, $user_id, 'intime_profile_interest_created_api', 'user', $target_user_id);

$stmt = $pdo->prepare("
    SELECT id
    FROM intime_profile_interests
    WHERE requester_id = ? AND target_user_id = ?
    LIMIT 1
");
$stmt->execute([$target_user_id, $user_id]);
$mutual = (bool) $stmt->fetch();
$conversation_id = null;

if ($mutual) {
    $conversation_id = intime_creer_ou_recuperer_conversation($pdo, $user_id, $target_user_id);
}

echo json_encode([
    'ok' => true,
    'mutual' => $mutual,
    'conversation_id' => $conversation_id,
]);
