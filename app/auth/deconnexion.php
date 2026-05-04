<?php
require_once __DIR__ . '/../../config/security.php';

demarrer_session_securisee();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/security-log.php';

if (isset($_SESSION['user_id'])) {
    journaliser_evenement_securite($pdo, 'logout', (int) $_SESSION['user_id']);
}

detruire_session_courante();
header('Location: /Site_rencontre/RencontreIRL/public/');
exit;   
