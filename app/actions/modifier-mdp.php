<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/security-log.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$erreur  = '';
$succes  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $actuel     = $_POST['mot_de_passe_actuel'];
    $nouveau    = $_POST['mot_de_passe_nouveau'];
    $confirmation = $_POST['mot_de_passe_confirmation'];

    $stmt = $pdo->prepare("SELECT mot_de_passe, email, prenom, ville FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!password_verify($actuel, $user['mot_de_passe'])) {
        journaliser_evenement_securite($pdo, 'password_change_failed', (int) $user_id, null, 'mot_de_passe_actuel_incorrect');
        $erreur = 'Mot de passe actuel incorrect.';
    } elseif ($erreur_mdp = erreur_mot_de_passe($nouveau, [$user['email'] ?? '', $user['prenom'] ?? '', $user['ville'] ?? ''])) {
        $erreur = $erreur_mdp;
    } elseif ($nouveau !== $confirmation) {
        $erreur = 'Les deux nouveaux mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($nouveau, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id]);
        journaliser_evenement_securite($pdo, 'password_changed', (int) $user_id);
        $succes = 'Mot de passe mis à jour avec succès.';
        header('Location: /Site_rencontre/RencontreIRL/app/pages/profil.php');
        exit;
    }
}
?>

<section class="auth-section">
  <div class="auth-card">
    <h1 class="auth-title">Modifier mon mot de passe</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
      <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
    <?php endif; ?>

    <form method="POST" data-disable-on-submit="true" action="">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="mot_de_passe_actuel">Mot de passe actuel</label>
        <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" autocomplete="current-password" required />
      </div>
      <div class="form-group">
        <label for="mot_de_passe_nouveau">Nouveau mot de passe</label>
        <input type="password" id="mot_de_passe_nouveau" name="mot_de_passe_nouveau" placeholder="10 caracteres, majuscule et chiffre" minlength="10" autocomplete="new-password" required />
      </div>
      <div class="form-group">
        <label for="mot_de_passe_confirmation">Confirmer le nouveau mot de passe</label>
        <input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" autocomplete="new-password" required />
      </div>
      <button type="submit" class="submit-btn">Mettre à jour</button>
    </form>

    <p class="auth-link" style="margin-top: 1rem;">
      <a href="../pages/profil.php">← Retour au profil</a>
    </p>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
