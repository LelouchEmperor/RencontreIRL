<?php
require_once __DIR__ . '/../../../../config/security.php';
demarrer_session_securisee();
require_once __DIR__ . '/../../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');
http_response_code(501);
echo json_encode([
    'error' => 'provider_not_configured',
    'message' => 'Webhook a connecter au prestataire KYC choisi. Aucun document sensible ne doit etre stocke ici.',
]);
