<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/geocode.php';

$sortie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$sortie_id) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        s.*,
        u.prenom AS organisateur,
        u.nom AS organisateur_nom,
        u.ville AS organisateur_ville,
        u.bio AS organisateur_bio,
        u.photo AS organisateur_photo,
        u.email_verifie AS organisateur_email_verifie,
        u.id AS organisateur_id,
        u.account_status AS organisateur_status,
        (
            SELECT COUNT(*)
            FROM sorties so
            WHERE so.user_id = u.id
        ) AS organisateur_nb_sorties
    FROM sorties s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

if ($sortie && !in_array(($sortie['organisateur_status'] ?? 'active') ?: 'active', ['', 'active'], true)) {
    $sortie = false;
}

if (!$sortie) {
    http_response_code(404);
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$est_organisateur = $sortie && $user_id > 0 && (int) $sortie['user_id'] === $user_id;
$deja_inscrit = false;
$participants = [];
$avis_sortie = [];
$avis_deja_donnes = [];
$distance = null;
$statut_sortie = $sortie ? sortie_statut_effectif($sortie) : 'unknown';
$nb_participants = 0;
$places_total = $sortie ? max(1, (int) $sortie['places_total']) : 1;
$places_restantes = $sortie ? max(0, (int) $sortie['places_restantes']) : 0;
$places_prises = $sortie ? max(0, $places_total - $places_restantes) : 0;
$progression_places = $sortie ? min(100, (int) round(($places_prises / $places_total) * 100)) : 0;

if ($sortie && $user_id > 0) {
    $stmt = $pdo->prepare("SELECT latitude, longitude FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && $user['latitude'] && $user['longitude'] && $sortie['latitude'] && $sortie['longitude']) {
        $distance = distance_km($user['latitude'], $user['longitude'], $sortie['latitude'], $sortie['longitude']);
    }

    $stmt = $pdo->prepare("SELECT id FROM participations WHERE sortie_id = ? AND user_id = ?");
    $stmt->execute([$sortie_id, $user_id]);
    $deja_inscrit = (bool) $stmt->fetch();
}

if ($sortie) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE sortie_id = ?");
    $stmt->execute([$sortie_id]);
    $nb_participants = (int) $stmt->fetchColumn();
}

if ($sortie && ($est_organisateur || $deja_inscrit || $statut_sortie === 'finished')) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.prenom, p.created_at
        FROM participations p
        JOIN users u ON u.id = p.user_id
        WHERE p.sortie_id = ?
        ORDER BY p.created_at ASC
    ");
    $stmt->execute([$sortie_id]);
    $participants = $stmt->fetchAll();
}

if ($sortie && $statut_sortie === 'finished') {
    $stmt = $pdo->prepare("
        SELECT r.*, reviewer.prenom AS reviewer_prenom, reviewed.prenom AS reviewed_prenom
        FROM sortie_reviews r
        JOIN users reviewer ON reviewer.id = r.reviewer_id
        JOIN users reviewed ON reviewed.id = r.reviewed_id
        WHERE r.sortie_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$sortie_id]);
    $avis_sortie = $stmt->fetchAll();

    if ($user_id > 0) {
        $stmt = $pdo->prepare("SELECT reviewed_id FROM sortie_reviews WHERE sortie_id = ? AND reviewer_id = ?");
        $stmt->execute([$sortie_id, $user_id]);
        $avis_deja_donnes = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }
}
?>

<section class="section">
  <?php if (!$sortie): ?>
    <div class="empty-state">
      <p>Cette sortie est introuvable.</p>
      <a href="sorties.php" class="cta-btn" style="margin-top: 1rem;">Retour aux sorties</a>
    </div>
  <?php else: ?>
    <div class="section-header">
      <div>
        <a href="sorties.php" class="back-link">Retour aux sorties</a>
        <h1 class="section-title" style="margin-top: 0.75rem;"><?= e($sortie['titre']) ?></h1>
      </div>
      <div class="sortie-actions">
        <span class="sortie-activite"><?= e($sortie['activite']) ?></span>
        <span class="sortie-status sortie-status-<?= e($statut_sortie) ?>"><?= e(sortie_statut_label($statut_sortie)) ?></span>
      </div>
    </div>

    <div class="sortie-detail-layout">
      <article class="sortie-detail-main">
        <div class="event-hero-summary">
          <div>
            <p class="sortie-meta">Le <?= date('d/m/Y a H:i', strtotime($sortie['date_sortie'])) ?></p>
            <p class="event-location"><?= e($sortie['ville']) ?><?= !empty($sortie['adresse']) ? ' - ' . e($sortie['adresse']) : '' ?></p>
          </div>
          <div class="event-quick-stats">
            <span><strong><?= (int) $nb_participants ?></strong> participant<?= $nb_participants > 1 ? 's' : '' ?></span>
            <span><strong><?= (int) $places_restantes ?></strong> place<?= $places_restantes > 1 ? 's' : '' ?> libre<?= $places_restantes > 1 ? 's' : '' ?></span>
            <?php if ($distance !== null): ?>
              <span><strong><?= (float) $distance ?></strong> km</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($sortie['description'])): ?>
          <p class="sortie-desc" style="font-size: 15px; margin-top: 1.5rem;">
            <?= nl2br(e($sortie['description'])) ?>
          </p>
        <?php else: ?>
          <p class="sortie-desc" style="font-size: 15px; margin-top: 1.5rem;">
            Aucune description ajoutee pour le moment.
          </p>
        <?php endif; ?>

        <div class="event-progress">
          <div class="event-progress-head">
            <span>Remplissage</span>
            <strong><?= (int) $places_prises ?> / <?= (int) $places_total ?></strong>
          </div>
          <div class="event-progress-bar">
            <span style="width: <?= (int) $progression_places ?>%;"></span>
          </div>
        </div>

        <div class="detail-list">
          <div class="detail-row">
            <span>Ville</span>
            <span><?= e($sortie['ville']) ?></span>
          </div>
          <?php if (!empty($sortie['adresse'])): ?>
            <div class="detail-row">
              <span>Adresse</span>
              <span><?= e($sortie['adresse']) ?></span>
            </div>
          <?php endif; ?>
          <div class="detail-row">
            <span>Places</span>
            <span><?= (int) $sortie['places_restantes'] ?> restante(s) sur <?= (int) $sortie['places_total'] ?></span>
          </div>
          <div class="detail-row">
            <span>Statut</span>
            <span><?= e(sortie_statut_label($statut_sortie)) ?></span>
          </div>
          <?php if ($distance !== null): ?>
            <div class="detail-row">
              <span>Distance</span>
              <span><?= (float) $distance ?> km</span>
            </div>
          <?php endif; ?>
        </div>
      </article>

      <aside class="sortie-detail-side">
        <h2 class="profil-section-title">Actions</h2>

        <?php if (!$user_id): ?>
          <a href="../auth/connexion.php" class="cta-btn" style="width: 100%; text-align: center;">Se connecter</a>
        <?php elseif ($est_organisateur): ?>
          <div class="sortie-actions" style="justify-content: flex-start;">
            <a href="../actions/modifier-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Modifier</a>
            <?php if ($statut_sortie === 'open'): ?>
              <a href="../actions/annuler-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Annuler</a>
            <?php endif; ?>
            <a href="../actions/supprimer-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Supprimer</a>
            <?php if (!empty($participants)): ?>
              <a href="messages.php" class="cta-btn-small">Messages</a>
            <?php endif; ?>
          </div>

          <h2 class="profil-section-title" style="margin-top: 2rem;">Participants</h2>
          <?php if (empty($participants)): ?>
            <p class="sortie-meta">Aucun participant pour le moment.</p>
          <?php else: ?>
            <div class="participant-list">
              <?php foreach ($participants as $participant): ?>
                <div class="participant-pill">
                  <span><?= e($participant['prenom']) ?></span>
                  <?php if ($statut_sortie === 'finished'): ?>
                    <?php $avis_done = isset($avis_deja_donnes[(int) $participant['id']]); ?>
                    <a href="../actions/review-sortie.php?sortie=<?= (int) $sortie_id ?>&user=<?= (int) $participant['id'] ?>" class="back-link">
                      <?= $avis_done ? 'Modifier avis' : 'Laisser un avis' ?>
                    </a>
                  <?php else: ?>
                    <a href="conversation.php?sortie=<?= (int) $sortie_id ?>&user=<?= (int) $participant['id'] ?>" class="back-link">Message</a>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php elseif ($statut_sortie === 'cancelled'): ?>
          <p class="alert alert-error">Cette sortie a ete annulee.</p>
        <?php elseif ($statut_sortie === 'finished'): ?>
          <p class="alert alert-error">Cette sortie est terminee.</p>
          <?php if ($est_organisateur || $deja_inscrit): ?>
            <h2 class="profil-section-title" style="margin-top: 2rem;">Avis</h2>
            <div class="participant-list">
              <?php if (!$est_organisateur): ?>
                <?php $avis_done = isset($avis_deja_donnes[(int) $sortie['organisateur_id']]); ?>
                <div class="participant-pill">
                  <span><?= e($sortie['organisateur']) ?></span>
                  <a href="../actions/review-sortie.php?sortie=<?= (int) $sortie_id ?>&user=<?= (int) $sortie['organisateur_id'] ?>" class="back-link">
                    <?= $avis_done ? 'Modifier avis' : 'Laisser un avis' ?>
                  </a>
                </div>
              <?php endif; ?>

              <?php foreach ($participants as $participant): ?>
                <?php if ((int) $participant['id'] === $user_id) continue; ?>
                <?php $avis_done = isset($avis_deja_donnes[(int) $participant['id']]); ?>
                <div class="participant-pill">
                  <span><?= e($participant['prenom']) ?></span>
                  <a href="../actions/review-sortie.php?sortie=<?= (int) $sortie_id ?>&user=<?= (int) $participant['id'] ?>" class="back-link">
                    <?= $avis_done ? 'Modifier avis' : 'Laisser un avis' ?>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php elseif ($deja_inscrit): ?>
          <a href="conversation.php?sortie=<?= (int) $sortie_id ?>&user=<?= (int) $sortie['user_id'] ?>" class="cta-btn" style="width: 100%; text-align: center;">Messagerie</a>
          <a href="../actions/quitter-sortie.php?id=<?= (int) $sortie_id ?>" class="cta-btn-outline" style="width: 100%; text-align: center; margin-top: 0.75rem;">Quitter</a>
        <?php elseif ($statut_sortie === 'open'): ?>
          <a href="conversation.php?sortie=<?= (int) $sortie_id ?>&user=<?= (int) $sortie['user_id'] ?>" class="cta-btn-outline" style="width: 100%; text-align: center;">Contacter l'organisateur</a>
          <a href="../actions/rejoindre.php?id=<?= (int) $sortie_id ?>" class="cta-btn" style="width: 100%; text-align: center; margin-top: 0.75rem;">Rejoindre</a>
          <a href="../actions/signaler.php?type=sortie&target=<?= (int) $sortie_id ?>" class="back-link" style="display: inline-block; margin-top: 1rem;">Signaler la sortie</a>
        <?php else: ?>
          <p class="alert alert-error">Cette sortie est complete.</p>
        <?php endif; ?>

        <div class="organizer-card">
          <h2 class="profil-section-title">Organisateur</h2>
          <a href="profil-public.php?id=<?= (int) $sortie['organisateur_id'] ?>" class="organizer-link">
            <?php if (!empty($sortie['organisateur_photo'])): ?>
              <img src="/Site_rencontre/RencontreIRL/public/uploads/<?= e($sortie['organisateur_photo']) ?>" alt="Photo de <?= e($sortie['organisateur']) ?>" />
            <?php else: ?>
              <span class="organizer-avatar"><?= e(strtoupper(substr((string) $sortie['organisateur'], 0, 1))) ?></span>
            <?php endif; ?>
            <span>
              <strong><?= e(trim($sortie['organisateur'] . ' ' . ($sortie['organisateur_nom'] ?? ''))) ?></strong>
              <small><?= e($sortie['organisateur_ville'] ?: 'Ville non renseignee') ?></small>
            </span>
          </a>
          <div class="organizer-badges">
            <span class="<?= !empty($sortie['organisateur_email_verifie']) ? 'is-success' : '' ?>">Email <?= !empty($sortie['organisateur_email_verifie']) ? 'verifie' : 'non verifie' ?></span>
            <span><?= (int) $sortie['organisateur_nb_sorties'] ?> sortie<?= (int) $sortie['organisateur_nb_sorties'] > 1 ? 's' : '' ?></span>
          </div>
          <?php if (!empty($sortie['organisateur_bio'])): ?>
            <p><?= e(texte_court($sortie['organisateur_bio'], 130)) ?></p>
          <?php endif; ?>
        </div>
      </aside>
    </div>

    <?php if ($statut_sortie === 'finished'): ?>
      <section class="profil-section" style="margin-top: 2rem;">
        <h2 class="profil-section-title">Avis de la sortie</h2>

        <?php if (empty($avis_sortie)): ?>
          <p class="sortie-meta">Aucun avis publie pour cette sortie.</p>
        <?php else: ?>
          <div class="reviews-list">
            <?php foreach ($avis_sortie as $avis): ?>
              <article class="review-card">
                <div class="review-head">
                  <strong><?= e($avis['reviewer_prenom']) ?> pour <?= e($avis['reviewed_prenom']) ?></strong>
                  <span><?= (int) $avis['note'] ?>/5</span>
                </div>
                <?php if (!empty($avis['commentaire'])): ?>
                  <p><?= nl2br(e($avis['commentaire'])) ?></p>
                <?php endif; ?>
                <small><?= date('d/m/Y', strtotime($avis['created_at'])) ?></small>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
