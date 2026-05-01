<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$sortie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($sortie_id <= 0) {
    header('Location: ' . app_url('app/pages/mes-sorties.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ? AND user_id = ?");
$stmt->execute([$sortie_id, $user_id]);
$sortie = $stmt->fetch();

if (!$sortie) {
    header('Location: ' . app_url('app/pages/mes-sorties.php'));
    exit;
}

$statut = sortie_statut_effectif($sortie);
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ($statut === 'cancelled') {
        $erreur = 'Cette sortie est deja annulee.';
    } elseif ($statut === 'finished') {
        $erreur = 'Une sortie terminee ne peut pas etre annulee.';
    } else {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("UPDATE sorties SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $stmt->execute([$sortie_id, $user_id]);

            $stmt = $pdo->prepare("
                SELECT user_id
                FROM participations
                WHERE sortie_id = ?
            ");
            $stmt->execute([$sortie_id]);
            $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($participants)) {
                $stmt_notif = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, message, lien)
                    VALUES (?, 'sortie_annulee', ?, ?)
                ");

                foreach ($participants as $participant_id) {
                    $stmt_notif->execute([
                        (int) $participant_id,
                        'La sortie "' . $sortie['titre'] . '" a ete annulee.',
                        'app/pages/sortie.php?id=' . (int) $sortie_id,
                    ]);
                }
            }

            $pdo->commit();
            header('Location: ' . app_url('app/pages/mes-sorties.php'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreur = 'Impossible d annuler cette sortie pour le moment.';
        }
    }
}
?>

<section class="auth-section">
  <div class="auth-card" style="max-width: 520px;">
    <h1 class="auth-title">Annuler la sortie</h1>

    <p style="font-size: 14px; color: #7a5060; margin-bottom: 1.5rem; line-height: 1.7;">
      Tu es sur le point d'annuler <strong style="color: #1a0810;"><?= e($sortie['titre']) ?></strong>.
      La sortie restera visible dans ton espace, mais elle ne sera plus disponible publiquement et les participants seront notifies.
    </p>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" data-disable-on-submit="true" action="" style="display: flex; gap: 1rem;">
      <?= csrf_field() ?>
      <a href="../pages/mes-sorties.php" class="cta-btn-outline" style="flex: 1; text-align: center;">Retour</a>
      <button type="submit" class="submit-btn" style="flex: 1; margin-top: 0;">Annuler la sortie</button>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
