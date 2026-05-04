<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';

$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, ip.pseudo, ip.age_range, ip.bio_courte
    FROM proposals_intime p
    JOIN user_profiles_intime ip ON ip.user_id = p.creator_id
    WHERE p.id = ?
    AND p.status = 'active'
    AND p.expires_at > NOW()
");
$stmt->execute([$id]);
$proposition = $stmt->fetch();

if (!$proposition) {
    http_response_code(404);
    die('Proposition introuvable.');
}

$succes = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'interest') {
    csrf_verify();

    if ((int) $proposition['creator_id'] === $user_id) {
        $erreur = 'Tu ne peux pas manifester ton interet pour ta propre proposition.';
    } else {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO intime_interests (proposal_id, user_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$id, $user_id]);

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO intime_matches (proposal_id, creator_id, interested_user_id, interested_confirmed_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$id, (int) $proposition['creator_id'], $user_id]);
        journaliser_audit($pdo, $user_id, 'intime_interest_created', 'proposal_intime', $id);
        $succes = 'Interet envoye. La conversation ne s ouvrira qu apres confirmation mutuelle.';
    }
}
?>

<section class="intime-shell">
  <article class="intime-card">
    <a href="propositions.php" class="back-link">Retour</a>
    <span class="verified-badge">18+ verifie</span>
    <h1><?= e($proposition['title']) ?></h1>
    <p><?= e($proposition['intention']) ?></p>

    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
    <?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

    <div class="intime-details">
      <p><strong>Zone :</strong> <?= e($proposition['approximate_location']) ?></p>
      <p><strong>Creneau :</strong> <?= e($proposition['time_window']) ?></p>
      <p><strong>Propose par :</strong> <?= e($proposition['pseudo']) ?><?= !empty($proposition['age_range']) ? ' - ' . e($proposition['age_range']) : '' ?></p>
      <?php if (!empty($proposition['limits_expectations'])): ?>
        <p><strong>Limites et attentes :</strong><br><?= nl2br(e($proposition['limits_expectations'])) ?></p>
      <?php endif; ?>
    </div>

    <?php if ((int) $proposition['creator_id'] !== $user_id): ?>
      <form method="POST" action="" data-disable-on-submit="true">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="interest">
        <button type="submit" class="intime-btn">Manifester mon interet</button>
      </form>
    <?php endif; ?>
  </article>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
