<?php
require_once __DIR__ . '/../../../../config/security.php';
demarrer_session_securisee();
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../services/age-verification-provider.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'non_connecte']);
    exit;
}

echo json_encode(statut_verification_age($pdo, (int) $_SESSION['user_id']));
