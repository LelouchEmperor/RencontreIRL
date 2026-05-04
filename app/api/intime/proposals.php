<?php
require_once __DIR__ . '/../../../config/security.php';
demarrer_session_securisee();
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../services/intime-access.php';

header('Content-Type: application/json; charset=utf-8');
$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT ip.user_id, ip.pseudo, ip.age_range, ip.zone_approximative, ip.rencontre_preferences, ip.bio_courte
        FROM user_profiles_intime ip
        JOIN users u ON u.id = ip.user_id
        LEFT JOIN privacy_settings ps ON ps.user_id = ip.user_id
        WHERE ip.user_id <> ?
        AND u.age_verified = 1
        AND u.verification_status = 'verified'
        AND u.safety_onboarding_completed = 1
        AND (u.account_status IS NULL OR u.account_status = '' OR u.account_status = 'active')
        AND (u.account_safety_status IS NULL OR u.account_safety_status = '' OR u.account_safety_status = 'active')
        AND (ps.hide_from_search IS NULL OR ps.hide_from_search = 0)
        ORDER BY ip.updated_at DESC
        LIMIT 80
    ");
    $stmt->execute([$user_id]);
    echo json_encode(['people' => $stmt->fetchAll()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'methode_invalide']);
