<?php
require_once __DIR__ . '/../../../config/security.php';
demarrer_session_securisee();
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../services/intime-access.php';

header('Content-Type: application/json; charset=utf-8');
$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM user_profiles_intime WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['profile' => $stmt->fetch()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'methode_invalide']);
