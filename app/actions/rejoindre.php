<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$sortie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$sortie_id) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, u.prenom as organisateur, u.account_status AS organisateur_status
    FROM sorties s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

if (
    !$sortie
    || !in_array(($sortie['organisateur_status'] ?? 'active') ?: 'active', ['', 'active'], true)
    || sortie_statut_effectif($sortie) !== 'open'
) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$erreur = '';
$succes = false;

if ((int) $sortie['user_id'] === $user_id) {
    $erreur = "Tu ne peux pas rejoindre ta propre sortie.";
} elseif ((int) $sortie['places_restantes'] <= 0) {
    $erreur = "Cette sortie est complete.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.account_status AS organisateur_status
            FROM sorties s
            JOIN users u ON u.id = s.user_id
            WHERE s.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$sortie_id]);
        $sortie_verrouillee = $stmt->fetch();

        if (
            !$sortie_verrouillee
            || !in_array(($sortie_verrouillee['organisateur_status'] ?? 'active') ?: 'active', ['', 'active'], true)
            || sortie_statut_effectif($sortie_verrouillee) !== 'open'
        ) {
            $erreur = "Cette sortie n'existe plus.";
        } elseif ((int) $sortie_verrouillee['user_id'] === $user_id) {
            $erreur = "Tu ne peux pas rejoindre ta propre sortie.";
        } elseif ((int) $sortie_verrouillee['places_restantes'] <= 0) {
            $erreur = "Cette sortie est complete.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM participations WHERE sortie_id = ? AND user_id = ?");
            $stmt->execute([$sortie_id, $user_id]);

            if ($stmt->fetch()) {
                $erreur = "Tu participes deja a cette sortie.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO participations (sortie_id, user_id) VALUES (?, ?)");
                $stmt->execute([$sortie_id, $user_id]);

                $stmt = $pdo->prepare("UPDATE sorties SET places_restantes = places_restantes - 1 WHERE id = ? AND places_restantes > 0");
                $stmt->execute([$sortie_id]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Aucune place disponible.');
                }
            }
        }

        if ($erreur) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
            $sortie['places_restantes'] = max(0, (int) $sortie['places_restantes'] - 1);

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, lien)
                VALUES (?, 'participation', ?, ?)
            ");
            $stmt->execute([
                $sortie['user_id'],
                $_SESSION['prenom'] . ' a rejoint ta sortie : ' . $sortie['titre'],
                'app/pages/sortie.php?id=' . (int) $sortie_id
            ]);
            $succes = true;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erreur = "Une erreur est survenue. Reessaie.";
    }
}
?>

<section class="auth-section">
  <div class="auth-card" style="max-width: 520px;">

    <h1 class="auth-title"><?= e($sortie['titre']) ?></h1>

    <div class="sortie-detail">
      <span class="sortie-activite"><?= e($sortie['activite']) ?></span>
      <p class="sortie-meta" style="margin-top: 1rem;">
        <?= e($sortie['ville']) ?> -
        <?= date('d/m/Y a H:i', strtotime($sortie['date_sortie'])) ?>
      </p>
      <?php if ($sortie['description']): ?>
        <p class="sortie-desc" style="margin-top: 0.75rem;">
          <?= e($sortie['description']) ?>
        </p>
      <?php endif; ?>
      <p style="margin-top: 0.75rem; font-size: 13px; color: #4a8a4a;">
        <?= (int) $sortie['places_restantes'] ?> place(s) restante(s) - Propose par <?= e($sortie['organisateur']) ?>
      </p>
    </div>

    <?php if ($erreur): ?>
      <div class="alert alert-error" style="margin-top: 1.5rem;">
        <?= e($erreur) ?>
      </div>
      <a href="../pages/sorties.php" class="cta-btn" style="display: inline-block; margin-top: 1rem;">
        Retour aux sorties
      </a>

    <?php elseif ($succes): ?>
      <div class="alert alert-success" style="margin-top: 1.5rem;">
        Tu as rejoint la sortie ! Tu peux maintenant contacter <?= e($sortie['organisateur']) ?>.
      </div>
      <div style="display: flex; gap: 1rem; margin-top: 1rem;">
        <a href="../pages/sorties.php" class="cta-btn">Retour aux sorties</a>
        <a href="../pages/conversation.php?sortie=<?= (int) $sortie_id ?>&user=<?= (int) $sortie['user_id'] ?>" class="cta-btn-small" style="padding: 12px 20px;">
          Envoyer un message a <?= e($sortie['organisateur']) ?>
        </a>
      </div>

    <?php else: ?>
      <p style="margin-top: 1.5rem; font-size: 14px; color: #6a7a6a;">
        Tu es sur le point de rejoindre cette sortie. Confirmes-tu ta participation ?
      </p>
      <form method="POST" data-disable-on-submit="true" action="rejoindre.php?id=<?= (int) $sortie_id ?>" style="margin-top: 1.5rem; display: flex; gap: 1rem;">
        <?= csrf_field() ?>
        <a href="../pages/sorties.php" class="cta-btn">Annuler</a>
        <button type="submit" class="submit-btn">Confirmer ma participation</button>
      </form>
    <?php endif; ?>

  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
