<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/geocode.php';
require_once __DIR__ . '/../../config/mailer.php';

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $prenom         = trim($_POST['prenom']);
    $email          = trim($_POST['email']);
    $mdp            = $_POST['mot_de_passe'];
    $ville          = trim($_POST['ville']);

    if (empty($prenom) || empty($email) || empty($mdp) || empty($ville)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Email invalide.';
    } elseif ($erreur_mdp = erreur_mot_de_passe($mdp, [$email, $prenom, $ville])) {
        $erreur = $erreur_mdp;
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erreur = 'Cet email est deja utilise.';
        } else {
            $hash  = password_hash($mdp, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));

            $coords = geocoder_ville($ville);
            $lat = $coords ? $coords['latitude'] : null;
            $lon = $coords ? $coords['longitude'] : null;

            $stmt = $pdo->prepare("INSERT INTO users (prenom, email, email_verifie, token_verification, mot_de_passe, ville, date_naissance, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)");
            $stmt->execute([$prenom, $email, 0, $token, $hash, $ville, $lat, $lon]);
            $lien = "http://localhost/Site_rencontre/RencontreIRL/app/auth/verifier-email.php?token=" . $token;

            $prenom_email = e($prenom);
            $lien_email = e($lien);

            $corps = "
            <div style='font-family: sans-serif; max-width: 500px; margin: 0 auto; padding: 2rem;'>
                <h2 style='color: #8b1a2a;'>Bienvenue sur Rencontre IRL, {$prenom_email} !</h2>
                <p style='color: #7a5060; line-height: 1.7;'>Pour activer ton compte, clique sur le bouton ci-dessous :</p>
                <a href='{$lien_email}' style='display: inline-block; margin: 1.5rem 0; padding: 14px 28px; background: #8b1a2a; color: white; border-radius: 8px; text-decoration: none; font-size: 15px;'>
                    Verifier mon email
                </a>
                <p style='color: #c4a0a8; font-size: 12px;'>Si tu n'as pas cree de compte, ignore cet email.</p>
            </div>";

            envoyerEmail($email, 'Verifie ton adresse email - Rencontre IRL', $corps);

            $succes = 'Compte cree ! Verifie ta boite mail pour activer ton compte.';
        }
    }
}
?>

<section class="auth-section">
  <div class="auth-card">
    <h1 class="auth-title">Creer un compte</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
      <div class="alert alert-success"><?= $succes ?></div>
      <div class="onboarding-card auth-onboarding">
        <strong>Apres verification</strong>
        <span>Connecte-toi pour completer ton profil.</span>
        <span>Ajoute tes centres d'interet pour recevoir des sorties plus pertinentes.</span>
        <span>Commence par rejoindre ou proposer une premiere sortie.</span>
      </div>
      <p class="auth-link">
        <a href="connexion.php">Aller a la connexion</a>
      </p>
    <?php endif; ?>

    <?php if (!$succes): ?>
    <form method="POST" data-disable-on-submit="true" action="">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="prenom">Pseudo public provisoire</label>
        <input type="text" id="prenom" name="prenom" placeholder="Alex" required />
        <small class="form-help">Ton prenom legal sera recupere plus tard via la verification d'identite.</small>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="toi@example.com" required />
      </div>
      <div class="form-group">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="10 caracteres, majuscule et chiffre" minlength="10" autocomplete="new-password" required />
      </div>
      <div class="form-group">
        <label for="ville">Ta ville</label>
        <input type="text" id="ville" name="ville" placeholder="Caen" required />
      </div>
      <button type="submit" class="submit-btn">Creer mon compte</button>
    </form>
    <?php endif; ?>

    <?php if (!$succes): ?>
      <p class="auth-link">Deja un compte ? <a href="connexion.php">Se connecter</a></p>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
