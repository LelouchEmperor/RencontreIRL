<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        s.*,
        COUNT(DISTINCT p.id) AS nb_participants,
        COUNT(DISTINCT l.id) AS nb_likes
    FROM sorties s
    LEFT JOIN participations p ON p.sortie_id = s.id
    LEFT JOIN likes_sorties l ON l.sortie_id = s.id
    WHERE s.user_id = ?
    GROUP BY s.id
    ORDER BY s.date_sortie ASC
");
$stmt->execute([$user_id]);
$mes_sorties = $stmt->fetchAll();

$participants_par_sortie = [];

if (!empty($mes_sorties)) {
    $ids_sorties = array_column($mes_sorties, 'id');
    $placeholders = implode(',', array_fill(0, count($ids_sorties), '?'));

    $stmt = $pdo->prepare("
        SELECT p.sortie_id, p.created_at, u.id AS user_id, u.prenom, u.ville
        FROM participations p
        JOIN users u ON u.id = p.user_id
        WHERE p.sortie_id IN ($placeholders)
        ORDER BY p.created_at ASC
    ");
    $stmt->execute($ids_sorties);

    foreach ($stmt->fetchAll() as $participant) {
        $participants_par_sortie[(int) $participant['sortie_id']][] = $participant;
    }
}
?>

<section class="section">
  <div class="section-header">
    <div>
      <a href="sorties.php" class="back-link">Retour aux sorties</a>
      <h1 class="section-title" style="margin-top: 0.75rem;">Mes sorties</h1>
    </div>
    <a href="../actions/creer-sortie.php" class="cta-btn">+ Proposer une sortie</a>
  </div>

  <?php if (empty($mes_sorties)): ?>
    <div class="empty-state">
      <p>Tu n'as pas encore propose de sortie.</p>
      <a href="../actions/creer-sortie.php" class="cta-btn" style="margin-top: 1rem;">Creer ma premiere sortie</a>
    </div>
  <?php else: ?>
    <div class="owner-events">
      <?php foreach ($mes_sorties as $sortie): ?>
        <?php
          $participants = $participants_par_sortie[(int) $sortie['id']] ?? [];
          $statut_sortie = sortie_statut_effectif($sortie);
        ?>
        <article class="owner-event">
          <div class="owner-event-main">
            <div class="sortie-header">
              <span class="sortie-activite"><?= e($sortie['activite']) ?></span>
              <span class="sortie-status sortie-status-<?= e($statut_sortie) ?>"><?= e(sortie_statut_label($statut_sortie)) ?></span>
            </div>

            <h2 class="sortie-titre">
              <a href="sortie.php?id=<?= (int) $sortie['id'] ?>" style="color: inherit; text-decoration: none;"><?= e($sortie['titre']) ?></a>
            </h2>

            <p class="sortie-meta">
              <?= e($sortie['ville']) ?>
              <?php if (!empty($sortie['adresse'])): ?>
                - <?= e($sortie['adresse']) ?>
              <?php endif; ?>
            </p>
            <p class="sortie-meta"><?= date('d/m/Y a H:i', strtotime($sortie['date_sortie'])) ?></p>

            <?php if (!empty($sortie['description'])): ?>
              <p class="sortie-desc"><?= e($sortie['description']) ?></p>
            <?php endif; ?>

            <div class="owner-stats">
              <div>
                <strong><?= (int) $sortie['nb_participants'] ?></strong>
                <span>participant(s)</span>
              </div>
              <div>
                <strong><?= (int) $sortie['places_restantes'] ?></strong>
                <span>place(s) restante(s)</span>
              </div>
              <div>
                <strong><?= (int) $sortie['nb_likes'] ?></strong>
                <span>like(s)</span>
              </div>
            </div>

            <div class="sortie-actions" style="justify-content: flex-start; margin-top: 1rem;">
              <a href="sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Details</a>
              <a href="../actions/modifier-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Modifier</a>
              <?php if ($statut_sortie === 'open'): ?>
                <a href="../actions/annuler-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Annuler</a>
              <?php endif; ?>
              <a href="../actions/supprimer-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Supprimer</a>
            </div>
          </div>

          <aside class="owner-event-side">
            <h3 class="profil-section-title">Participants</h3>

            <?php if (empty($participants)): ?>
              <p class="sortie-meta">Personne n'a encore rejoint cette sortie.</p>
            <?php else: ?>
              <div class="participant-list">
                <?php foreach ($participants as $participant): ?>
                  <div class="participant-pill">
                    <span>
                      <?= e($participant['prenom']) ?>
                      <?php if (!empty($participant['ville'])): ?>
                        <small><?= e($participant['ville']) ?></small>
                      <?php endif; ?>
                    </span>
                    <a href="conversation.php?sortie=<?= (int) $sortie['id'] ?>&user=<?= (int) $participant['user_id'] ?>" class="back-link">Message</a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </aside>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
