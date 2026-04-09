<?php
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../config/geocode.php';

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom']);
    $email  = trim($_POST['email']);
    $mdp    = $_POST['mot_de_passe'];
    $ville  = trim($_POST['ville']);

    if (empty($prenom) || empty($email) || empty($mdp) || empty($ville)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Email invalide.';
    } elseif (strlen($mdp) < 8) {
        $erreur = 'Le mot de passe doit faire au moins 8 caractères.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erreur = 'Cet email est déjà utilisé.';
        } else {
            $hash = password_hash($mdp, PASSWORD_BCRYPT);

            $coords = geocoder_ville($ville);
            $lat = $coords ? $coords['latitude'] : null;
            $lon = $coords ? $coords['longitude'] : null;

            if (!$coords) {
                $erreur_ville = true;
            }

            $stmt = $pdo->prepare("INSERT INTO users (prenom, email, mot_de_passe, ville, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$prenom, $email, $hash, $ville, $lat, $lon]);

            if (isset($erreur_ville)) {
                $succes = 'Compte créé ! <a href="connexion.php">Connecte-toi</a> — Note : ta ville n\'a pas été reconnue, tu pourras la corriger dans ton profil.';
                header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
              exit;
            } else {
                header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
                exit;
            }
        }
    }
}
?>

<section class="auth-section">
  <div class="auth-card">
    <h1 class="auth-title">Créer un compte</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
      <div class="alert alert-success"><?= $succes ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="prenom">Prénom</label>
        <input type="text" id="prenom" name="prenom" placeholder="Alex" required />
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="toi@example.com" required />
      </div>
      <div class="form-group">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="8 caractères minimum" required />
      </div>
      <div class="form-group">
        <label for="ville">Ta ville</label>
        <input type="text" id="ville" name="ville" placeholder="Caen" required />
      </div>
      <button type="submit" class="submit-btn">Créer mon compte</button>
    </form>

    <p class="auth-link">Déjà un compte ? <a href="connexion.php">Se connecter</a></p>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>