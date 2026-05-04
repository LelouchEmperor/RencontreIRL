<?php
require_once __DIR__ . '/../../config/security.php';
demarrer_session_securisee();
require_once __DIR__ . '/../../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];

function export_fetch_all(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$stmt = $pdo->prepare("
    SELECT id, prenom, nom, email, email_verifie, ville, date_naissance, code_postal, bio,
           created_at, age_verified, age_verified_at, verification_status,
           safety_onboarding_completed, account_status, account_safety_status
    FROM users
    WHERE id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$export = [
    'generated_at' => date('c'),
    'user' => $user,
    'photos_profil' => export_fetch_all($pdo, "SELECT id, nom_fichier, ordre, moderation_status, created_at FROM photos_profil WHERE user_id = ?", [$user_id]),
    'sorties_creees' => export_fetch_all($pdo, "SELECT * FROM sorties WHERE user_id = ?", [$user_id]),
    'participations' => export_fetch_all($pdo, "SELECT * FROM participations WHERE user_id = ?", [$user_id]),
    'messages_envoyes' => export_fetch_all($pdo, "SELECT id, sortie_id, destinataire_id, contenu, lu, created_at FROM messages WHERE expediteur_id = ?", [$user_id]),
    'notifications' => export_fetch_all($pdo, "SELECT type, message, lu, lien, created_at FROM notifications WHERE user_id = ?", [$user_id]),
    'profil_intime' => export_fetch_all($pdo, "SELECT * FROM user_profiles_intime WHERE user_id = ?", [$user_id]),
    'propositions_intimes' => export_fetch_all($pdo, "SELECT * FROM proposals_intime WHERE creator_id = ?", [$user_id]),
    'interets_intimes_envoyes' => export_fetch_all($pdo, "SELECT target_user_id, status, created_at FROM intime_profile_interests WHERE requester_id = ?", [$user_id]),
    'interets_intimes_recus' => export_fetch_all($pdo, "SELECT requester_id, status, created_at FROM intime_profile_interests WHERE target_user_id = ?", [$user_id]),
    'consentements' => export_fetch_all($pdo, "SELECT type, version, accepted, created_at FROM consent_events WHERE user_id = ?", [$user_id]),
    'confidentialite' => export_fetch_all($pdo, "SELECT * FROM privacy_settings WHERE user_id = ?", [$user_id]),
];

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="rencontreirl-export-' . $user_id . '.json"');
echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
