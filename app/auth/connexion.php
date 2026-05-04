<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/security-log.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if ($email === '' || $mdp === '') {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (connexion_temporairement_bloquee($pdo, $email)) {
        journaliser_evenement_securite($pdo, 'login_rate_limited', null, $email);
        $erreur = 'Trop de tentatives. Reessaie dans quelques minutes.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            if (password_needs_rehash($user['mot_de_passe'], PASSWORD_DEFAULT)) {
                $nouveau_hash = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt_rehash = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
                $stmt_rehash->execute([$nouveau_hash, (int) $user['id']]);
            }

            if (!$user['email_verifie']) {
                enregistrer_tentative_connexion($pdo, $email, false);
                journaliser_evenement_securite($pdo, 'login_email_not_verified', (int) $user['id'], $email);
                $erreur = 'Ton email n\'est pas encore verifie. Consulte ta boite mail.';
            } elseif (($user['account_status'] ?? 'active') !== 'active') {
                enregistrer_tentative_connexion($pdo, $email, false);
                journaliser_evenement_securite($pdo, 'login_restricted_account', (int) $user['id'], $email, (string) ($user['account_status'] ?? 'unknown'));
                $erreur = 'Ton compte est actuellement restreint. Contacte le support si besoin.';
            } else {
                enregistrer_tentative_connexion($pdo, $email, true);
                journaliser_evenement_securite($pdo, 'login_success', (int) $user['id'], $email);
                nettoyer_anciennes_tentatives_connexion($pdo);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['prenom']  = $user['prenom'];
                $_SESSION['derniere_activite'] = time();
                header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
                exit;
            }
        } else {
            enregistrer_tentative_connexion($pdo, $email, false);
            journaliser_evenement_securite($pdo, 'login_failed', $user ? (int) $user['id'] : null, $email);
            $erreur = 'Email ou mot de passe incorrect.';
        }
    }
}
?>

<section class="auth-section">
  <div class="auth-card">
    <h1 class="auth-title">Se connecter</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" data-disable-on-submit="true" action="">
      <?= csrf_field() ?>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
