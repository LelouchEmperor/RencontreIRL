<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$erreur_suppression = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer_compte') {
    $mdp = $_POST['mot_de_passe'];

    if (!password_verify($mdp, $user['mot_de_passe'])) {
        $erreur_suppression = 'Mot de passe incorrect.';
    } else {
        if ($user['photo'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/' . $user['photo'])) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/' . $user['photo']);
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        session_destroy();
        header('Location: /Site_rencontre/RencontreIRL/');
        exit;
    }
}
?>

<section class="section" style="max-width: 680px; margin: 0 auto;">

  <h1 class="section-title" style="margin-bottom: 3rem;">Paramètres</h1>

  <!-- Compte -->
  <div class="profil-section" style="margin-bottom: 1.5rem;">
    <h3 class="profil-section-title">Mon compte</h3>
    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <a href="profil.php" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #fdf4f5; border: 0.5px solid #e8c8cc; border-radius: 8px; text-decoration: none; transition: border-color 0.2s;">
        <div>
          <div style="font-size: 14px; font-weight: 500; color: #1a0810;">Modifier mon profil</div>
          <div style="font-size: 12px; color: #a07080; margin-top: 0.2rem;">Prénom, ville, bio, photo</div>
        </div>
        <span style="color: #c4a0a8; font-size: 18px;">→</span>
      </a>
      <a href="modifier-mdp.php" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #fdf4f5; border: 0.5px solid #e8c8cc; border-radius: 8px; text-decoration: none; transition: border-color 0.2s;">
        <div>
          <div style="font-size: 14px; font-weight: 500; color: #1a0810;">Modifier mon mot de passe</div>
          <div style="font-size: 12px; color: #a07080; margin-top: 0.2rem;">Changer ton mot de passe actuel</div>
        </div>
        <span style="color: #c4a0a8; font-size: 18px;">→</span>
      </a>
    </div>
  </div>

  <!-- Zone danger -->
  <div class="profil-section" style="border-color: #e8c0c0;">
    <h3 class="profil-section-title" style="color: #8b1a2a;">Zone dangereuse</h3>

    <?php if ($erreur_suppression): ?>
      <div class="alert alert-error" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($erreur_suppression) ?></div>
    <?php endif; ?>

    <p style="font-size: 14px; color: #7a5060; margin-bottom: 1rem; line-height: 1.7;">
      La suppression de ton compte est <strong style="color: #8b1a2a;">irréversible</strong>. 
      Toutes tes données seront effacées — profil, sorties, messages, participations.
    </p>

    <details style="margin-top: 1rem;">
      <summary style="font-size: 14px; color: #8b1a2a; cursor: pointer; font-weight: 500; list-style: none; padding: 0.75rem 1rem; background: #fff0f2; border: 0.5px solid #d4909a; border-radius: 8px;">
        Supprimer mon compte définitivement
      </summary>
      <div style="margin-top: 1rem; padding: 1rem; background: #fdf4f5; border: 0.5px solid #e8c8cc; border-radius: 8px;">
        <form method="POST" action="">
          <input type="hidden" name="action" value="supprimer_compte" />
          <div class="form-group">
            <label for="mot_de_passe">Confirme ton mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="Ton mot de passe" required />
          </div>
          <button type="submit" style="width: 100%; padding: 13px; background: #8b1a2a; border: none; border-radius: 8px; color: #fdf4f5; font-size: 14px; cursor: pointer; margin-top: 0.5rem;">
            Supprimer définitivement
          </button>
        </form>
      </div>
    </details>
  </div>

</section>

<?php require_once 'includes/footer.php'; ?>