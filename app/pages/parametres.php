<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/user-cleanup.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: ' . app_url('public/'));
    exit;
}

$erreur_suppression = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer_compte') {
    csrf_verify();

    $mdp = $_POST['mot_de_passe'] ?? '';

    if (empty($user['mot_de_passe']) || !password_verify($mdp, $user['mot_de_passe'])) {
        $erreur_suppression = 'Mot de passe incorrect.';
    } else {
        try {
            $photos = supprimer_utilisateur_complet($pdo, $user_id);
            supprimer_fichiers_upload($photos);

            session_destroy();
            header('Location: ' . app_url('public/'));
            exit;
        } catch (Throwable $e) {
            $erreur_suppression = 'Impossible de supprimer le compte pour le moment.';
        }
    }
}

$liens_parametres = [
    [
        'href' => 'app/pages/profil.php',
        'titre' => 'Modifier mon profil',
        'description' => 'Prenom, ville, bio, photo principale',
    ],
    [
        'href' => 'app/actions/upload-photo.php',
        'titre' => 'Mes photos',
        'description' => 'Gerer ta galerie de photos',
    ],
    [
        'href' => 'app/services/prompts.php',
        'titre' => 'Mes questions',
        'description' => 'Questions brise-glace sur ton profil',
    ],
    [
        'href' => 'app/actions/modifier-mdp.php',
        'titre' => 'Modifier mon mot de passe',
        'description' => 'Changer ton mot de passe actuel',
    ],
    [
        'href' => 'app/pages/blocked-users.php',
        'titre' => 'Profils bloques',
        'description' => 'Voir et debloquer les profils que tu as bloques',
    ],
];
?>

<section class="section settings-section">
  <h1 class="section-title">Parametres</h1>

  <div class="profil-section">
    <h3 class="profil-section-title">Mon compte</h3>

    <div class="settings-links">
      <?php foreach ($liens_parametres as $lien): ?>
        <a href="<?= e(app_url($lien['href'])) ?>" class="settings-link">
          <span>
            <strong><?= e($lien['titre']) ?></strong>
            <small><?= e($lien['description']) ?></small>
          </span>
          <span class="settings-link-arrow">-></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="profil-section danger-section">
    <h3 class="profil-section-title">Zone dangereuse</h3>

    <?php if ($erreur_suppression): ?>
      <div class="alert alert-error"><?= e($erreur_suppression) ?></div>
    <?php endif; ?>

    <p class="danger-text">
      La suppression de ton compte est irreversible. Tes donnees principales seront effacees :
      profil, sorties, messages, participations et photos.
    </p>

    <details class="danger-details">
      <summary>Supprimer mon compte definitivement</summary>

      <form method="POST" data-disable-on-submit="true" action="" class="danger-form">
        <input type="hidden" name="action" value="supprimer_compte" />
        <?= csrf_field() ?>

        <div class="form-group">
          <label for="mot_de_passe">Confirme ton mot de passe</label>
          <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="Ton mot de passe" required />
        </div>

        <button type="submit" class="danger-btn">
          Supprimer definitivement
        </button>
      </form>
    </details>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
