<?php
require_once '../includes/header.php';
require_once '../config/db.php';
require_once '../config/geocode.php';
require_once '../config/mailer.php';

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom         = trim($_POST['prenom']);
    $email          = trim($_POST['email']);
    $mdp            = $_POST['mot_de_passe'];
    $ville          = trim($_POST['ville']);
    $date_naissance = $_POST['date_naissance'];

    if (empty($prenom) || empty($email) || empty($mdp) || empty($ville) || empty($date_naissance)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Email invalide.';
    } elseif (strlen($mdp) < 8) {
        $erreur = 'Le mot de passe doit faire au moins 8 caractères.';
    } else {
        $age = (int) date('Y') - (int) date('Y', strtotime($date_naissance));
        if ($age < 18) {
            $erreur = 'Tu dois avoir au moins 18 ans pour t\'inscrire.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erreur = 'Cet email est déjà utilisé.';
            } else {
                $hash  = password_hash($mdp, PASSWORD_BCRYPT);
                $token = bin2hex(random_bytes(32));

                $coords = geocoder_ville($ville);
                $lat = $coords ? $coords['latitude'] : null;
                $lon = $coords ? $coords['longitude'] : null;

                $stmt = $pdo->prepare("INSERT INTO users (prenom, email, email_verifie, token_verification, mot_de_passe, ville, date_naissance, latitude, longitude) VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$prenom, $email, 0, $token, $hash, $ville, $date_naissance, $lat, $lon]);

                $lien = "http://localhost/Site_rencontre/RencontreIRL/auth/verifier-email.php?token=" . $token;

                $corps = "
                <div style='font-family: sans-serif; max-width: 500px; margin: 0 auto; padding: 2rem;'>
                    <h2 style='color: #8b1a2a;'>Bienvenue sur Rencontre IRL, {$prenom} !</h2>
                    <p style='color: #7a5060; line-height: 1.7;'>Pour activer ton compte, clique sur le bouton ci-dessous :</p>
                    <a href='{$lien}' style='display: inline-block; margin: 1.5rem 0; padding: 14px 28px; background: #8b1a2a; color: white; border-radius: 8px; text-decoration: none; font-size: 15px;'>
                        Vérifier mon email
                    </a>
                    <p style='color: #c4a0a8; font-size: 12px;'>Si tu n'as pas créé de compte, ignore cet email.</p>
                </div>";

                envoyerEmail($email, 'Vérifie ton adresse email — Rencontre IRL', $corps);

                $succes = 'Compte créé ! Vérifie ta boîte mail pour activer ton compte.';
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

    <?php if (!$succes): ?>
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
      <div class="form-group">
        <label for="date_naissance">Date de naissance</label>
        <input type="date" id="date_naissance" name="date_naissance" required />
      </div>
      <button type="submit" class="submit-btn">Créer mon compte</button>
    </form>
    <?php endif; ?>

    <p class="auth-link">Déjà un compte ? <a href="connexion.php">Se connecter</a></p>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>