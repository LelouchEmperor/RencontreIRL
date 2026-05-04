<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/upload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$erreur  = '';
$succes  = '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ?");
$stmt->execute([$user_id]);
$nb_photos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ? AND moderation_status = 'pending'");
$stmt->execute([$user_id]);
$nb_photos_en_attente = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$photo_principale = $stmt->fetchColumn() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['principale_id'])) {
        csrf_verify();
        $photo_id = (int) $_POST['principale_id'];

        $stmt = $pdo->prepare("SELECT nom_fichier FROM photos_profil WHERE id = ? AND user_id = ? AND moderation_status = 'approved'");
        $stmt->execute([$photo_id, $user_id]);
        $photo = $stmt->fetch();

        if ($photo) {
            $stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
            $stmt->execute([$photo['nom_fichier'], $user_id]);
            $photo_principale = $photo['nom_fichier'];
            $succes = 'Photo principale mise a jour.';
        }

    // ── Suppression ──
    } elseif (isset($_POST['supprimer_id'])) {
        csrf_verify();
        $photo_id = (int) $_POST['supprimer_id'];
        $stmt = $pdo->prepare("SELECT nom_fichier FROM photos_profil WHERE id = ? AND user_id = ?");
        $stmt->execute([$photo_id, $user_id]);
        $photo = $stmt->fetch();

        if ($photo) {
            $chemin = chemin_upload($photo['nom_fichier']);
            if ($chemin && file_exists($chemin)) {
                unlink($chemin);
            }

            $stmt = $pdo->prepare("DELETE FROM photos_profil WHERE id = ? AND user_id = ?");
            $stmt->execute([$photo_id, $user_id]);

            if ($photo_principale === $photo['nom_fichier']) {
                $stmt = $pdo->prepare("SELECT nom_fichier FROM photos_profil WHERE user_id = ? AND moderation_status = 'approved' ORDER BY ordre ASC LIMIT 1");
                $stmt->execute([$user_id]);
                $photo_principale = $stmt->fetchColumn() ?: null;

                $stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
                $stmt->execute([$photo_principale, $user_id]);
            }
            $succes = 'Photo supprimée.';

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $nb_photos = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ? AND moderation_status = 'pending'");
            $stmt->execute([$user_id]);
            $nb_photos_en_attente = (int) $stmt->fetchColumn();
        }

    // ── Upload ──
    } elseif (isset($_FILES['photos'])) {
        csrf_verify();
        $fichiers     = $_FILES['photos'];
        $nb_nouvelles = count($fichiers['name']);

        if ($nb_photos_en_attente > 0) {
            $erreur = 'Tu as deja une photo en attente de validation. Attends la reponse admin avant d\'en proposer une autre.';
        } elseif ($nb_nouvelles > 1) {
            $erreur = 'Tu peux proposer une seule photo a la fois.';
        } elseif ($nb_photos + $nb_nouvelles > 6) {
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

                $stmt = $pdo->prepare("INSERT INTO photos_profil (user_id, nom_fichier, ordre, moderation_status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $result['nom'], $nb_photos + $uploaded]);

                $uploaded++;
                $nb_photos_en_attente = 1;
            }

            if ($uploaded > 0) {
                $succes     = 'Photo envoyee. Elle sera visible apres validation admin.';
                $nb_photos += $uploaded;
            } else {
                $erreur = 'Aucune photo valide. Utilise JPG, PNG ou WEBP, 2 Mo max.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM photos_profil WHERE user_id = ? ORDER BY FIELD(moderation_status, 'approved', 'pending', 'rejected'), ordre ASC");
$stmt->execute([$user_id]);
$photos = $stmt->fetchAll();
?>
<section class="section" style="max-width: 760px; margin: 0 auto;">
  <div class="section-header">
    <h1 class="section-title">Mes photos</h1>
    <a href="../pages/profil.php" class="cta-btn-outline">← Retour au profil</a>
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
        <img src="/Site_rencontre/RencontreIRL/public/uploads/<?= htmlspecialchars($photo['nom_fichier']) ?>"
             style="width: 100%; height: 180px; object-fit: cover; border-radius: 12px; border: 0.5px solid #e8c8cc;"/>
        <?php $statut_photo = $photo['moderation_status'] ?? 'pending'; ?>
        <span class="photo-status photo-status-<?= e($statut_photo) ?>">
          <?= $statut_photo === 'approved' ? 'Validee' : ($statut_photo === 'rejected' ? 'Refusee' : 'En attente') ?>
        </span>
        <?php if ($photo_principale === $photo['nom_fichier']): ?>
          <span style="position: absolute; left: 8px; top: 38px; background: #eef8f1; color: #216b36; border: 0.5px solid #9ed3ad; border-radius: 999px; padding: 4px 10px; font-size: 11px;">
            Principale
          </span>
        <?php endif; ?>
        <form method="POST" data-disable-on-submit="true" action="" style="position: absolute; top: 8px; right: 8px;">
          <?= csrf_field() ?>
          <input type="hidden" name="supprimer_id" value="<?= $photo['id'] ?>" />
          <button type="submit" style="width: 28px; height: 28px; border-radius: 50%; background: #8b1a2a; border: none; color: white; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center;">✕</button>
        </form>
        <?php if ($photo_principale !== $photo['nom_fichier'] && $statut_photo === 'approved'): ?>
          <form method="POST" data-disable-on-submit="true" action="" style="margin-top: 0.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="principale_id" value="<?= (int) $photo['id'] ?>" />
            <button type="submit" class="cta-btn-small" style="width: 100%;">
              Definir comme principale
            </button>
          </form>
        <?php elseif ($statut_photo === 'pending'): ?>
          <p class="sortie-meta" style="margin-top: 0.5rem;">Validation admin en attente.</p>
        <?php elseif ($statut_photo === 'rejected'): ?>
          <p class="sortie-meta" style="margin-top: 0.5rem;">Photo refusee<?= !empty($photo['moderation_reason']) ? ' : ' . e($photo['moderation_reason']) : '.' ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($nb_photos_en_attente > 0): ?>
      <div class="photo-upload-locked">
        Une photo est deja en attente de validation. Tu pourras en proposer une autre apres validation ou refus.
      </div>
    <?php elseif ($nb_photos < 6): ?>
      <label style="display: flex; align-items: center; justify-content: center; height: 180px; border: 1px dashed #d4909a; border-radius: 12px; cursor: pointer; color: #8b1a2a; font-size: 13px; flex-direction: column; gap: 0.5rem;">
        <span style="font-size: 28px;">+</span>
        <span>Ajouter une photo</span>
        <form method="POST" data-disable-on-submit="true" action="" enctype="multipart/form-data" id="uploadForm">
          <?= csrf_field() ?>
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp"
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
