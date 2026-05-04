<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/user-cleanup.php';
require_once __DIR__ . '/../services/intime-access.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$erreur = '';

$stmt = $pdo->prepare("SELECT mot_de_passe FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = $_POST['mot_de_passe'] ?? '';
    $confirmation = trim($_POST['confirmation'] ?? '');

    if (!$user || empty($user['mot_de_passe']) || !password_verify($password, $user['mot_de_passe'])) {
        $erreur = 'Mot de passe incorrect.';
    } elseif ($confirmation !== 'SUPPRIMER') {
        $erreur = 'Tape SUPPRIMER pour confirmer.';
    } else {
        enregistrer_consentement($pdo, $user_id, 'account_delete_requested', '2026-05-04', true);
        journaliser_audit($pdo, $user_id, 'account_deleted_by_user', 'user', $user_id);
        $photos = supprimer_utilisateur_complet($pdo, $user_id);
        supprimer_fichiers_upload($photos);
        detruire_session_courante();
        header('Location: ' . app_url('public/'));
        exit;
    }
}
?>

<section class="section">
  <form method="POST" action="" class="auth-card danger-form" data-disable-on-submit="true">
    <?= csrf_field() ?>
    <a href="<?= e(app_url('app/pages/parametres.php')) ?>" class="back-link">Retour aux parametres</a>
    <h1 class="auth-title">Supprimer mon compte</h1>
    <p class="danger-text">Cette action est irreversible et supprime tes donnees principales du site.</p>

    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>

    <div class="form-group">
      <label for="mot_de_passe">Mot de passe</label>
      <input type="password" id="mot_de_passe" name="mot_de_passe" required>
    </div>
    <div class="form-group">
      <label for="confirmation">Tape SUPPRIMER</label>
      <input id="confirmation" name="confirmation" required>
    </div>
    <button class="danger-btn" type="submit">Supprimer definitivement</button>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
