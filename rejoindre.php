<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /rencontre_site_web/auth/connexion.php');
    exit;
}

$sortie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$sortie_id) {
    header('Location: /rencontre_site_web/sorties.php');
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, u.prenom as organisateur FROM sorties s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

if (!$sortie) {
    header('Location: /rencontre_site_web/sorties.php');
    exit;
}

$erreur = '';
$succes = false;

if ($sortie['user_id'] === $_SESSION['user_id']) {
    $erreur = "Tu ne peux pas rejoindre ta propre sortie.";
} elseif ($sortie['places_restantes'] <= 0) {
    $erreur = "Cette sortie est complète.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT id FROM participations WHERE sortie_id = ? AND user_id = ?");
    $stmt->execute([$sortie_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $erreur = "Tu participes déjà à cette sortie.";
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO participations (sortie_id, user_id) VALUES (?, ?)");
            $stmt->execute([$sortie_id, $_SESSION['user_id']]);

            $stmt = $pdo->prepare("UPDATE sorties SET places_restantes = places_restantes - 1 WHERE id = ?");
            $stmt->execute([$sortie_id]);

            $pdo->commit();
            $succes = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = "Une erreur est survenue. Réessaie.";
        }
    }
}
?>

<section class="auth-section">
  <div class="auth-card" style="max-width: 520px;">

    <h1 class="auth-title"><?= htmlspecialchars($sortie['titre']) ?></h1>

    <div class="sortie-detail">
      <span class="sortie-activite"><?= htmlspecialchars($sortie['activite']) ?></span>
      <p class="sortie-meta" style="margin-top: 1rem;">
        <?= htmlspecialchars($sortie['ville']) ?> —
        <?= date('d/m/Y à H:i', strtotime($sortie['date_sortie'])) ?>
      </p>
      <?php if ($sortie['description']): ?>
        <p class="sortie-desc" style="margin-top: 0.75rem;">
          <?= htmlspecialchars($sortie['description']) ?>
        </p>
      <?php endif; ?>
      <p style="margin-top: 0.75rem; font-size: 13px; color: #4a8a4a;">
        <?= $sortie['places_restantes'] ?> place(s) restante(s) — Proposé par <?= htmlspecialchars($sortie['organisateur']) ?>
      </p>
    </div>

    <?php if ($erreur): ?>
      <div class="alert alert-error" style="margin-top: 1.5rem;">
        <?= htmlspecialchars($erreur) ?>
      </div>
      <a href="sorties.php" class="cta-btn" style="display: inline-block; margin-top: 1rem;">
        Retour aux sorties
      </a>

    <?php elseif ($succes): ?>
      <div class="alert alert-success" style="margin-top: 1.5rem;">
        Tu as rejoint la sortie ! Tu peux maintenant contacter <?= htmlspecialchars($sortie['organisateur']) ?>.
      </div>
      <div style="display: flex; gap: 1rem; margin-top: 1rem;">
        <a href="sorties.php" class="cta-btn">Retour aux sorties</a>
        <a href="conversation.php?sortie=<?= $sortie_id ?>&user=<?= $sortie['user_id'] ?>" class="cta-btn-small" style="padding: 12px 20px;">
          Envoyer un message à <?= htmlspecialchars($sortie['organisateur']) ?>
        </a>
      </div>

    <?php else: ?>
      <p style="margin-top: 1.5rem; font-size: 14px; color: #6a7a6a;">
        Tu es sur le point de rejoindre cette sortie. Confirmes-tu ta participation ?
      </p>
      <form method="POST" action="rejoindre.php?id=<?= $sortie_id ?>" style="margin-top: 1.5rem; display: flex; gap: 1rem;">
        <a href="sorties.php" class="cta-btn">Annuler</a>
        <button type="submit" class="submit-btn">Confirmer ma participation</button>
      </form>
    <?php endif; ?>

  </div>
</section>

<?php require_once 'includes/footer.php'; ?>