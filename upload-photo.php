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

$stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ?");
$stmt->execute([$user_id]);
$nb_photos = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['photos'])) {
        $fichiers = $_FILES['photos'];
        $nb_nouvelles = count($fichiers['name']);

        if ($nb_photos + $nb_nouvelles > 6) {
            $erreur = 'Tu ne peux pas avoir plus de 6 photos de profil.';
        } else {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $uploaded = 0;

            for ($i = 0; $i < $nb_nouvelles; $i++) {
                if ($fichiers['error'][$i] !== UPLOAD_ERR_OK) continue;

                $ext = strtolower(pathinfo($fichiers['name'][$i], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) continue;
                if ($fichiers['size'][$i] > 2 * 1024 * 1024) continue;

                $nom_fichier = 'gallery_' . $user_id . '_' . time() . '_' . $i . '.' . $ext;
                $chemin = $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/' . $nom_fichier;

                if (move_uploaded_file($fichiers['tmp_name'][$i], $chemin)) {
                    $stmt = $pdo->prepare("INSERT INTO photos_profil (user_id, nom_fichier, ordre) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $nom_fichier, $nb_photos + $uploaded]);
                    $uploaded++;
                }
            }

            if ($uploaded > 0) {
                $succes = $uploaded . ' photo(s) ajoutée(s) avec succès.';
                $nb_photos += $uploaded;
            }
        }
    }

    if (isset($_POST['supprimer_id'])) {
        $photo_id = (int) $_POST['supprimer_id'];
        $stmt = $pdo->prepare("SELECT nom_fichier FROM photos_profil WHERE id = ? AND user_id = ?");
        $stmt->execute([$photo_id, $user_id]);
        $photo = $stmt->fetch();

        if ($photo) {
            $chemin = $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/' . $photo['nom_fichier'];
            if (file_exists($chemin)) unlink($chemin);

            $stmt = $pdo->prepare("DELETE FROM photos_profil WHERE id = ? AND user_id = ?");
            $stmt->execute([$photo_id, $user_id]);
            $succes = 'Photo supprimée.';

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $nb_photos = $stmt->fetchColumn();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM photos_profil WHERE user_id = ? ORDER BY ordre ASC");
$stmt->execute([$user_id]);
$photos = $stmt->fetchAll();
?>

<section class="section" style="max-width: 760px; margin: 0 auto;">
  <div class="section-header">
    <h1 class="section-title">Mes photos</h1>
    <a href="profil.php" class="cta-btn-outline">← Retour au profil</a>
  </div>

  <?php if ($erreur): ?>
    <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
  <?php endif; ?>

  <?php if ($succes): ?>
    <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
  <?php endif; ?>

  <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
    <?php foreach ($photos as $photo): ?>
      <div style="position: relative;">
        <img src="/Site_rencontre/RencontreIRL/uploads/<?= htmlspecialchars($photo['nom_fichier']) ?>"
             style="width: 100%; height: 180px; object-fit: cover; border-radius: 12px; border: 0.5px solid #e8c8cc;"/>
        <form method="POST" action="" style="position: absolute; top: 8px; right: 8px;">
          <input type="hidden" name="supprimer_id" value="<?= $photo['id'] ?>" />
          <button type="submit" style="width: 28px; height: 28px; border-radius: 50%; background: #8b1a2a; border: none; color: white; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center;">✕</button>
        </form>
      </div>
    <?php endforeach; ?>

    <?php if ($nb_photos < 6): ?>
      <label style="display: flex; align-items: center; justify-content: center; height: 180px; border: 1px dashed #d4909a; border-radius: 12px; cursor: pointer; color: #8b1a2a; font-size: 13px; flex-direction: column; gap: 0.5rem;">
        <span style="font-size: 28px;">+</span>
        <span>Ajouter une photo</span>
        <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
                 style="display: none;" id="photoInput"
                 onchange="document.getElementById('uploadForm').submit()" />
        </form>
      </label>
    <?php endif; ?>
  </div>

  <p style="font-size: 12px; color: #a07080;">
    <?= $nb_photos ?>/6 photos — JPG, PNG ou WEBP, 2 Mo max par photo.
  </p>
</section>

<script>
document.querySelector('label')?.addEventListener('click', function() {
  document.getElementById('photoInput').click();
});
</script>

<?php require_once 'includes/footer.php'; ?>