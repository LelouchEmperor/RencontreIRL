<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';
require_once __DIR__ . '/../services/intime-matching.php';
require_once __DIR__ . '/../services/interests.php';

$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];
$ville = trim($_GET['ville'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM user_profiles_intime WHERE user_id = ?");
$stmt->execute([$user_id]);
$profil_viewer = $stmt->fetch() ?: [];
$interets_viewer = interets_utilisateur($pdo, $user_id);

$sql = "
    SELECT ip.*, u.id AS user_id, u.age_verified, u.email_verifie,
           COALESCE(sent.id, 0) AS interest_sent,
           COALESCE(received.id, 0) AS interest_received,
           COALESCE(photos.nb_photos, 0) AS nb_photos_validees
    FROM user_profiles_intime ip
    JOIN users u ON u.id = ip.user_id
    LEFT JOIN privacy_settings ps ON ps.user_id = ip.user_id
    LEFT JOIN intime_profile_interests sent
        ON sent.requester_id = ?
        AND sent.target_user_id = ip.user_id
    LEFT JOIN intime_profile_interests received
        ON received.requester_id = ip.user_id
        AND received.target_user_id = ?
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS nb_photos
        FROM photos_profil
        WHERE moderation_status = 'approved'
        GROUP BY user_id
    ) photos ON photos.user_id = ip.user_id
    WHERE ip.user_id <> ?
    AND u.age_verified = 1
    AND u.verification_status = 'verified'
    AND u.safety_onboarding_completed = 1
    AND (u.account_status IS NULL OR u.account_status = '' OR u.account_status = 'active')
    AND (u.account_safety_status IS NULL OR u.account_safety_status = '' OR u.account_safety_status = 'active')
    AND (ps.hide_from_search IS NULL OR ps.hide_from_search = 0)
";
$params = [$user_id, $user_id, $user_id];

if ($ville !== '') {
    $sql .= " AND ip.zone_approximative LIKE ?";
    $params[] = '%' . $ville . '%';
}

$sql .= " ORDER BY ip.updated_at DESC LIMIT 80";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personnes = $stmt->fetchAll();

$interets_par_user = [];
if (!empty($personnes)) {
    $ids = array_map(static fn (array $personne): int => (int) $personne['user_id'], $personnes);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT user_id, interest FROM user_interests WHERE user_id IN ($placeholders)");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $row) {
        $interets_par_user[(int) $row['user_id']][] = $row['interest'];
    }
}
?>

<section class="intime-shell">
  <div class="section-header intime-header">
    <div>
      <p class="mode-pill">Mode Intime</p>
      <h1>Personnes verifiees</h1>
      <p class="intime-note">Ici, on decouvre des profils, pas des rendez-vous. L interet reste discret et la conversation demande un accord mutuel.</p>
    </div>
    <div class="intime-actions">
      <a href="profil.php" class="intime-btn-secondary">Mon profil intime</a>
      <a href="<?= e(app_url('app/pages/sorties.php')) ?>" class="quick-exit-btn">Sortie rapide</a>
    </div>
  </div>

  <div class="safety-banner">
    Securite : ne partage pas d'argent, crypto, document ou numero personnel. Garde les premiers echanges sur la plateforme et signale tout comportement insistant.
  </div>

  <form method="GET" action="" class="intime-filter">
    <input name="ville" value="<?= e($ville) ?>" placeholder="Filtrer par ville ou zone">
    <button class="intime-btn-secondary" type="submit">Filtrer</button>
  </form>

  <?php if (empty($personnes)): ?>
    <div class="intime-panel">Aucun profil intime visible pour le moment.</div>
  <?php else: ?>
    <div class="intime-people-grid">
      <?php foreach ($personnes as $personne): ?>
        <?php
          $compatibilite = intime_compatibilite(
              $profil_viewer,
              $personne,
              $interets_viewer,
              $interets_par_user[(int) $personne['user_id']] ?? []
          );
          $intentions_personne = intime_liste_depuis_chaine($personne['intentions'] ?? '');
          $premieres_etapes = intime_premieres_etapes();
        ?>
        <article class="intime-person-card">
          <div>
            <span class="verified-badge">18+ verifie</span>
            <h2><?= e($personne['pseudo']) ?></h2>
            <p class="intime-note">
              <?= e($personne['zone_approximative'] ?: 'Zone non renseignee') ?>
              <?= !empty($personne['age_range']) ? ' - ' . e($personne['age_range']) : '' ?>
            </p>
          </div>

          <div class="compat-score">
            <strong><?= (int) $compatibilite['score'] ?>%</strong>
            <span><?= e(implode(' - ', array_slice($compatibilite['raisons'], 0, 2))) ?></span>
          </div>

          <div class="trust-badges intime-badges">
            <span class="trust-badge is-success">Identite verifiee</span>
            <?php if (!empty($personne['email_verifie'])): ?><span class="trust-badge is-success">Email verifie</span><?php endif; ?>
            <?php if ((int) $personne['nb_photos_validees'] > 0): ?><span class="trust-badge is-success">Photo validee</span><?php endif; ?>
          </div>

          <?php if (!empty($intentions_personne)): ?>
            <div class="intime-chip-row">
              <?php foreach (array_slice($intentions_personne, 0, 3) as $intention): ?>
                <span><?= e($intention) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($personne['bio_courte'])): ?>
            <p><?= e(texte_court($personne['bio_courte'], 140)) ?></p>
          <?php endif; ?>

          <?php if (!empty($personne['rencontre_preferences'])): ?>
            <p><strong>Recherche :</strong> <?= e(texte_court($personne['rencontre_preferences'], 110)) ?></p>
          <?php endif; ?>

          <p class="intime-note">
            Rythme : <?= e($premieres_etapes[$personne['preferred_first_step'] ?? 'social_first'] ?? 'Avancer lentement') ?>
          </p>

          <div class="intime-actions">
            <a href="personne.php?id=<?= (int) $personne['user_id'] ?>" class="intime-btn-secondary">Voir le profil</a>
            <?php if (!empty($personne['interest_received']) && empty($personne['interest_sent'])): ?>
              <span class="intime-state-pill">Elle/il t'a remarque</span>
            <?php endif; ?>
            <?php if (!empty($personne['interest_sent'])): ?>
              <span class="intime-state-pill">Interet envoye</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
