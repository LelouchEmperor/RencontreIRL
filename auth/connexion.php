<?php
require_once '../includes/header.php';
require_once '../config/db.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $mdp   = $_POST['mot_de_passe'];

    if (empty($email) || empty($mdp)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
if ($user && password_verify($mdp, $user['mot_de_passe'])) {
    if (!$user['email_verifie']) {
        $erreur = 'Ton email n\'est pas encore vérifié. Consulte ta boîte mail.';
    } else {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['prenom']  = $user['prenom'];
        header('Location: /Site_rencontre/RencontreIRL/sorties.php');
        exit;
    }
} else {
    $erreur = 'Email ou mot de passe incorrect.';
}
    }
}
?>

<section class="auth-section">
  <div class="auth-card">
    <h1 class="auth-title">Se connecter</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="toi@example.com" required />
      </div>
      <div class="form-group">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="Ton mot de passe" required />
      </div>
      <button type="submit" class="submit-btn">Se connecter</button>
    </form>

    <p class="auth-link">Pas encore de compte ? <a href="inscription.php">S'inscrire</a></p>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>