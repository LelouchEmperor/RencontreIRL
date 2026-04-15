<?php
require_once 'includes/header.php';
require_once 'config/db.php';
require_once 'config/upload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$erreur  = '';
$succes  = '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ?");
$stmt->execute([$user_id]);
$nb_photos = (int) $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Suppression ──
    if (isset($_POST['supprimer_id'])) {
        csrf_verify();
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
            $nb_photos = (int) $stmt->fetchColumn();
        }

    // ── Upload ──
    } elseif (isset($_FILES['photos'])) {
        csrf_verify();
        $fichiers     = $_FILES['photos'];
        $nb_nouvelles = count($fichiers['name']);

        if ($nb_photos + $nb_nouvelles > 6) {
            $erreur = 'Tu ne peux pas avoir plus de 6 photos de profil.';
        } else {
            $uploaded = 0;

            for ($i = 0; $i < $nb_nouvelles; $i++) {
                // Construire un tableau compatible avec valider_et_upload_photo()
                $file_single = [
                    'name'     => $fichiers['name'][$i],
                    'type'     => $fichiers['type'][$i],
                    'tmp_name' => $fichiers['tmp_name'][$i],
                    'error'    => $fichiers['error'][$i],
                    'size'     => $fichiers['size'][$i],
                ];

                $result = valider_et_upload_photo($file_single, $user_id);

                if (!$result['ok']) {
                    // On skip silencieusement les fichiers invalides
                    // (ou tu peux accumuler les erreurs si tu préfères)
                    continue;
                }

                $stmt = $pdo->prepare("INSERT INTO photos_profil (user_id, nom_fichier, ordre) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $result['nom'], $nb_photos + $uploaded]);
                $uploaded++;
            }

            if ($uploaded > 0) {
                $succes     = $uploaded . ' photo(s) ajoutée(s) avec succès.';
                $nb_photos += $uploaded;
            } else {
                $erreur = 'Aucune photo valide. Utilise JPG, PNG ou WEBP, 2 Mo max.';
            }
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