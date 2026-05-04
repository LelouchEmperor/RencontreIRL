<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';
require_once __DIR__ . '/../services/intime-matching.php';
require_once __DIR__ . '/../services/interests.php';

$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];
$target_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$erreur = '';
$succes = '';

$stmt = $pdo->prepare("SELECT * FROM user_profiles_intime WHERE user_id = ?");
$stmt->execute([$user_id]);
$profil_viewer = $stmt->fetch() ?: [];
$interets_viewer = interets_utilisateur($pdo, $user_id);

$stmt = $pdo->prepare("
    SELECT ip.*, u.id AS user_id, u.email_verifie,
           COALESCE(photos.nb_photos, 0) AS nb_photos_validees
    FROM user_profiles_intime ip
    JOIN users u ON u.id = ip.user_id
    LEFT JOIN privacy_settings ps ON ps.user_id = ip.user_id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS nb_photos
        FROM photos_profil
        WHERE moderation_status = 'approved'
        GROUP BY user_id
    ) photos ON photos.user_id = ip.user_id
    WHERE ip.user_id = ?
    AND ip.user_id <> ?
    AND u.age_verified = 1
    AND u.verification_status = 'verified'
    AND u.safety_onboarding_completed = 1
    AND (u.account_status IS NULL OR u.account_status = '' OR u.account_status = 'active')
    AND (u.account_safety_status IS NULL OR u.account_safety_status = '' OR u.account_safety_status = 'active')
    AND (ps.hide_from_search IS NULL OR ps.hide_from_search = 0)
");
$stmt->execute([$target_id, $user_id]);
$profil = $stmt->fetch();

if (!$profil) {
    http_response_code(404);
    die('Profil intime introuvable.');
}

$interets_target = interets_utilisateur($pdo, $target_id);
$compatibilite = intime_compatibilite($profil_viewer, $profil, $interets_viewer, $interets_target);
$intentions_profil = intime_liste_depuis_chaine($profil['intentions'] ?? '');
$premieres_etapes = intime_premieres_etapes();
$conversation_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'interest') {
    csrf_verify();

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO intime_profile_interests (requester_id, target_user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$user_id, $target_id]);

    $stmt = $pdo->prepare("
        SELECT id
        FROM intime_profile_interests
        WHERE requester_id = ? AND target_user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$target_id, $user_id]);
    $reciproque = $stmt->fetch();

    if ($reciproque) {
        $conversation_id = intime_creer_ou_recuperer_conversation($pdo, $user_id, $target_id);
        journaliser_audit($pdo, $user_id, 'intime_profile_mutual_interest', 'user', $target_id);
        $succes = 'Interet mutuel detecte. Une conversation intime securisee est ouverte.';
    } else {
        journaliser_audit($pdo, $user_id, 'intime_profile_interest_created', 'user', $target_id);
        $succes = 'Interet envoye discretement.';
    }
}

$stmt = $pdo->prepare("
    SELECT id FROM intime_profile_interests
    WHERE requester_id = ? AND target_user_id = ?
    LIMIT 1
");
$stmt->execute([$user_id, $target_id]);
$interest_sent = (bool) $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT id FROM intime_profile_interests
    WHERE requester_id = ? AND target_user_id = ?
    LIMIT 1
");
$stmt->execute([$target_id, $user_id]);
$interest_received = (bool) $stmt->fetch();

if ($interest_sent && $interest_received) {
    [$one, $two] = intime_conversation_pair($user_id, $target_id);
    $stmt = $pdo->prepare("SELECT id FROM intime_conversations WHERE user_one_id = ? AND user_two_id = ? LIMIT 1");
    $stmt->execute([$one, $two]);
    $conversation_id = (int) $stmt->fetchColumn() ?: null;
}
?>

<section class="intime-shell">
  <article class="intime-card">
    <div class="intime-profile-topbar">
      <a href="personnes.php" class="back-link">Retour aux personnes</a>
      <a href="<?= e(app_url('app/pages/sorties.php')) ?>" class="quick-exit-btn">Sortie rapide</a>
    </div>
    <span class="verified-badge">18+ verifie</span>
    <h1><?= e($profil['pseudo']) ?></h1>
    <p class="intime-note">
      <?= e($profil['zone_approximative'] ?: 'Zone non renseignee') ?>
      <?= !empty($profil['age_range']) ? ' - ' . e($profil['age_range']) : '' ?>
    </p>

    <div class="safety-banner">
      Garde les premiers echanges sur la plateforme. Refuse toute demande d'argent, crypto, lien suspect ou pression pour partir sur une autre messagerie.
    </div>

    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
    <?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

    <div class="compat-score compat-score-large">
      <strong><?= (int) $compatibilite['score'] ?>%</strong>
      <span><?= e(implode(' - ', $compatibilite['raisons'])) ?></span>
    </div>

    <div class="trust-badges intime-badges">
      <span class="trust-badge is-success">Identite verifiee</span>
      <?php if (!empty($profil['email_verifie'])): ?><span class="trust-badge is-success">Email verifie</span><?php endif; ?>
      <?php if ((int) $profil['nb_photos_validees'] > 0): ?><span class="trust-badge is-success">Photo validee</span><?php endif; ?>
      <?php if (!empty($profil['bio_courte']) && !empty($profil['rencontre_preferences'])): ?><span class="trust-badge is-success">Profil complet</span><?php endif; ?>
    </div>

    <?php if (!empty($intentions_profil)): ?>
      <div class="intime-chip-row">
        <?php foreach ($intentions_profil as $intention): ?>
          <span><?= e($intention) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="intime-details">
      <?php if (!empty($profil['bio_courte'])): ?>
        <p><strong>Bio :</strong><br><?= nl2br(e($profil['bio_courte'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($profil['rencontre_preferences'])): ?>
        <p><strong>Ce que la personne recherche :</strong><br><?= nl2br(e($profil['rencontre_preferences'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($profil['disponibilites'])): ?>
        <p><strong>Disponibilites :</strong><br><?= nl2br(e($profil['disponibilites'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($profil['limites_attentes'])): ?>
        <p><strong>Limites et attentes :</strong><br><?= nl2br(e($profil['limites_attentes'])) ?></p>
      <?php endif; ?>
      <p><strong>Premiere etape preferee :</strong><br><?= e($premieres_etapes[$profil['preferred_first_step'] ?? 'social_first'] ?? 'Avancer lentement') ?></p>
    </div>

    <div class="conversation-starters">
      <strong>Idees pour commencer naturellement</strong>
      <a href="<?= e(app_url('app/pages/sorties.php')) ?>">Proposer une sortie publique d'abord</a>
      <?php if (!empty($compatibilite['interets_communs'])): ?>
        <span>Parler de <?= e($compatibilite['interets_communs'][0]) ?></span>
      <?php endif; ?>
      <?php if (!empty($profil['bio_courte'])): ?>
        <span>Reagir a sa bio</span>
      <?php endif; ?>
    </div>

    <?php if ($conversation_id): ?>
      <a href="conversation.php?id=<?= (int) $conversation_id ?>" class="intime-btn">Ouvrir la conversation securisee</a>
    <?php elseif ($interest_sent): ?>
      <span class="intime-state-pill">Interet deja envoye<?= $interest_received ? ' - accord mutuel en cours' : '' ?></span>
    <?php else: ?>
      <form method="POST" action="" data-disable-on-submit="true">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="interest">
        <button class="intime-btn" type="submit">Envoyer un interet discret</button>
      </form>
    <?php endif; ?>

    <div class="intime-safety-actions">
      <a href="<?= e(app_url('app/actions/signaler.php?type=user&target=' . (int) $target_id)) ?>" class="intime-btn-secondary">Signaler</a>
      <form method="POST" action="<?= e(app_url('app/actions/block-user.php')) ?>" data-disable-on-submit="true">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="block">
        <input type="hidden" name="target_user_id" value="<?= (int) $target_id ?>">
        <input type="hidden" name="redirect" value="app/intime/personnes.php">
        <button type="submit" class="intime-btn-secondary">Bloquer</button>
      </form>
    </div>
  </article>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
