<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';

$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];

$stmt = $pdo->prepare("
    SELECT p.*, ip.pseudo
    FROM proposals_intime p
    JOIN user_profiles_intime ip ON ip.user_id = p.creator_id
    WHERE p.status = 'active'
    AND p.expires_at > NOW()
    AND p.creator_id <> ?
    ORDER BY p.created_at DESC
    LIMIT 80
");
$stmt->execute([$user_id]);
$propositions = $stmt->fetchAll();
?>

<section class="intime-shell">
  <div class="section-header intime-header">
    <div>
      <p class="mode-pill">Mode Intime</p>
      <h1>Propositions confidentielles</h1>
      <p class="intime-note">Ville ou zone approximative uniquement. La conversation s ouvrira seulement apres accord mutuel.</p>
    </div>
    <div class="intime-actions">
      <a href="profil.php" class="intime-btn-secondary">Profil intime</a>
      <a href="creer-proposition.php" class="intime-btn">Creer une proposition</a>
    </div>
  </div>

  <?php if (empty($propositions)): ?>
    <div class="intime-panel">Aucune proposition active pour le moment.</div>
  <?php else: ?>
    <div class="intime-proposal-grid">
      <?php foreach ($propositions as $proposition): ?>
        <article class="intime-proposal-card">
          <span class="verified-badge">Verifie 18+</span>
          <h2><?= e($proposition['title']) ?></h2>
          <p><?= e($proposition['intention']) ?></p>
          <dl>
            <div><dt>Zone</dt><dd><?= e($proposition['approximate_location']) ?></dd></div>
            <div><dt>Creneau</dt><dd><?= e($proposition['time_window']) ?></dd></div>
            <div><dt>Propose par</dt><dd><?= e($proposition['pseudo']) ?></dd></div>
          </dl>
          <a href="proposition.php?id=<?= (int) $proposition['id'] ?>" class="intime-btn-secondary">Voir avec discretion</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
