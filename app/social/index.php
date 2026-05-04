<?php
require_once __DIR__ . '/../../config/security.php';
demarrer_session_securisee();
header('Location: ' . app_url('app/pages/sorties.php'));
exit;
