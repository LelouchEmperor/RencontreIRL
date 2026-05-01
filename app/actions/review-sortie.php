<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$sortie_id = isset($_GET['sortie']) ? (int) $_GET['sortie'] : (int) ($_POST['sortie_id'] ?? 0);
$reviewed_id = isset($_GET['user']) ? (int) $_GET['user'] : (int) ($_POST['reviewed_id'] ?? 0);
$erreur = '';
$succes = '';

if ($sortie_id <= 0 || $reviewed_id <= 0 || $reviewed_id === $user_id) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, u.account_status AS organisateur_status
    FROM sorties s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

$stmt = $pdo->prepare("SELECT id, prenom, account_status FROM users WHERE id = ?");
$stmt->execute([$reviewed_id]);
$reviewed = $stmt->fetch();

if (!$sortie || !$reviewed || sortie_statut_effectif($sortie) !== 'finished') {
    http_response_code(404);
    $erreur = 'Avis indisponible pour cette sortie.';
}

if (!$erreur && !in_array(($reviewed['account_status'] ?? 'active') ?: 'active', ['', 'active'], true)) {
    $erreur = 'Ce profil ne peut pas recevoir d avis.';
}

$participants_ids = [];
if (!$erreur) {
    $stmt = $pdo->prepare("SELECT user_id FROM participations WHERE sortie_id = ?");
    $stmt->execute([$sortie_id]);
    $participants_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $organisateur_id = (int) $sortie['user_id'];
    $membres_sortie = array_unique(array_merge([$organisateur_id], $participants_ids));

    if (!in_array($user_id, $membres_sortie, true) || !in_array($reviewed_id, $membres_sortie, true)) {
        $erreur = 'Tu ne peux donner un avis que sur une personne presente dans cette sortie.';
    }
}

if (!$erreur) {
    $stmt = $pdo->prepare("
        SELECT id, note, commentaire
        FROM sortie_reviews
        WHERE sortie_id = ? AND reviewer_id = ? AND reviewed_id = ?
    ");
    $stmt->execute([$sortie_id, $user_id, $reviewed_id]);
    $avis_existant = $stmt->fetch();
} else {
    $avis_existant = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erreur) {
    csrf_verify();

    $note = (int) ($_POST['note'] ?? 0);
    $commentaire = trim((string) ($_POST['commentaire'] ?? ''));

    if ($note < 1 || $note > 5) {
        $erreur = 'Choisis une note entre 1 et 5.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($commentaire, 'UTF-8') : strlen($commentaire)) > 800) {
        $erreur = 'Le commentaire ne doit pas depasser 800 caracteres.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO sortie_reviews (sortie_id, reviewer_id, reviewed_id, note, commentaire)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE note = VALUES(note), commentaire = VALUES(commentaire), created_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$sortie_id, $user_id, $reviewed_id, $note, $commentaire !== '' ? $commentaire : null]);

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, lien)
            VALUES (?, 'avis_recu', ?, ?)
        ");
        $stmt->execute([
            $reviewed_id,
            'Tu as recu un nouvel avis apres une sortie.',
            'app/pages/profil-public.php?id=' . $reviewed_id,
        ]);

        header('Location: /Site_rencontre/RencontreIRL/app/pages/sortie.php?id=' . $sortie_id);
        exit;
    }
}
?>

<section class="auth-section">
  <div class="auth-card">
    <h1 class="auth-title">Laisser un avis</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= e($erreur) ?></div>
      <p class="auth-link"><a href="../pages/sortie.php?id=<?= (int) $sortie_id ?>">Retour a la sortie</a></p>
    <?php else: ?>
      <p class="sortie-meta" style="text-align: center; margin-bottom: 1.5rem;">
        Avis pour <?= e($reviewed['prenom']) ?> apres "<?= e($sortie['titre']) ?>"
      </p>

      <form method="POST" data-disable-on-submit="true" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="sortie_id" value="<?= (int) $sortie_id ?>">
        <input type="hidden" name="reviewed_id" value="<?= (int) $reviewed_id ?>">

        <div class="form-group">
          <label for="note">Note</label>
          <select id="note" name="note" required>
            <?php for ($i = 5; $i >= 1; $i--): ?>
              <option value="<?= $i ?>" <?= ((int) ($avis_existant['note'] ?? 5)) === $i ? 'selected' : '' ?>>
                <?= $i ?>/5
              </option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="commentaire">Commentaire</label>
          <textarea id="commentaire" name="commentaire" rows="5" maxlength="800" placeholder="Ambiance, ponctualite, respect..."><?= e($avis_existant['commentaire'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="submit-btn">Publier l'avis</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
