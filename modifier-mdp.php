<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$erreur  = '';
$succes  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actuel     = $_POST['mot_de_passe_actuel'];
    $nouveau    = $_POST['mot_de_passe_nouveau'];
    $confirmation = $_POST['mot_de_passe_confirmation'];

    $stmt = $pdo->prepare("SELECT mot_de_passe FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!password_verify($actuel, $user['mot_de_passe'])) {
        $erreur = 'Mot de passe actuel incorrect.';
    } elseif (strlen($nouveau) < 8) {
        $erreur = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
    } elseif ($nouveau !== $confirmation) {
        $erreur = 'Les deux nouveaux mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($nouveau, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id]);
        $succes = 'Mot de passe mis à jour avec succès.';
        header('Location: /Site_rencontre/RencontreIRL/profil.php');
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

    <form method="POST" action="">
      <div class="form-group">
        <label for="mot_de_passe_actuel">Mot de passe actuel</label>
        <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required />
      </div>
      <div class="form-group">
        <label for="mot_de_passe_nouveau">Nouveau mot de passe</label>
        <input type="password" id="mot_de_passe_nouveau" name="mot_de_passe_nouveau" placeholder="8 caractères minimum" required />
      </div>
      <div class="form-group">
        <label for="mot_de_passe_confirmation">Confirmer le nouveau mot de passe</label>
        <input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" required />
      </div>
      <button type="submit" class="submit-btn">Mettre à jour</button>
    </form>

    <p class="auth-link" style="margin-top: 1rem;">
      <a href="profil.php">← Retour au profil</a>
    </p>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>