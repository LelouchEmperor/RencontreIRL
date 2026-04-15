<?php
require_once 'includes/header.php';
require_once 'config/db.php';
require_once 'config/geocode.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$erreur  = '';
$succes  = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $prenom = trim($_POST['prenom']);
    $ville          = trim($_POST['ville']);
    $bio            = trim($_POST['bio']);
    $date_naissance = $_POST['date_naissance'] ?? $user['date_naissance'];
    $photo          = $user['photo'];

    if (empty($prenom) || empty($ville)) {
        $erreur = 'Le prénom et la ville sont obligatoires.';
    } else {
        if (!empty($_FILES['photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $erreur = 'Format non autorisé. Utilise JPG, PNG ou WEBP.';
            } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
                $erreur = 'La photo ne doit pas dépasser 2 Mo.';
            } else {
                $nom_fichier = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $chemin = $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/' . $nom_fichier;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $chemin)) {
                    if ($user['photo'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/' . $user['photo'])) {
                        unlink($_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/' . $user['photo']);
                    }
                    $photo = $nom_fichier;
                } else {
                    $erreur = 'Erreur lors de l\'upload. Réessaie.';
                }
            }
        }

        if (!$erreur) {
            $coords = geocoder_ville($ville);
            $lat = $coords ? $coords['latitude'] : $user['latitude'];
            $lon = $coords ? $coords['longitude'] : $user['longitude'];

            $stmt = $pdo->prepare("UPDATE users SET prenom = ?, ville = ?, bio = ?, date_naissance = ?, latitude = ?, longitude = ?, photo = ? WHERE id = ?");
            $stmt->execute([$prenom, $ville, $bio, $date_naissance, $lat, $lon, $photo, $user_id]);

            $_SESSION['prenom'] = $prenom;

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $succes = 'Profil mis à jour avec succès.';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(p.id) as nb_participants
    FROM sorties s
    LEFT JOIN participations p ON s.id = p.sortie_id
    WHERE s.user_id = ?
    GROUP BY s.id
    ORDER BY s.date_sortie DESC
");
$stmt->execute([$user_id]);
$mes_sorties = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT s.*, u.prenom as organisateur
    FROM participations p
    JOIN sorties s ON p.sortie_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE p.user_id = ?
    ORDER BY s.date_sortie DESC
");
$stmt->execute([$user_id]);
$sorties_rejointes = $stmt->fetchAll();
?>

<section class="section">
  <div class="profil-wrap">

    <div class="profil-sidebar">
      <div onclick="document.getElementById('photo').click()" style="cursor: pointer; position: relative; display: inline-block; margin-bottom: 1rem;">
        <?php if ($user['photo']): ?>
          <img src="/Site_rencontre/RencontreIRL/uploads/<?= htmlspecialchars($user['photo']) ?>"
               id="sidebarPreview"
               style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 0.5px solid #e8c8cc;"/>
        <?php else: ?>
          <div class="profil-avatar" id="sidebarPreview">
            <?= strtoupper(substr($user['prenom'], 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div style="position: absolute; bottom: 0; right: 0; width: 24px; height: 24px; background: #8b1a2a; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fdf4f5;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </div>
      </div>

      <h2 class="profil-nom"><?= htmlspecialchars($user['prenom']) ?></h2>
      <p class="profil-ville">
        <?= htmlspecialchars($user['ville']) ?>
        <?php if ($user['latitude']): ?>
          <span style="color: #8b1a2a; font-size: 11px;">✓ géolocalisé</span>
        <?php else: ?>
          <span style="color: #c4a0a8; font-size: 11px;">non géolocalisé</span>
        <?php endif; ?>
      </p>
      <?php if ($user['date_naissance']): ?>
        <p style="font-size: 13px; color: #a07080; margin-bottom: 0.5rem;">
          <?= (int) date('Y') - (int) date('Y', strtotime($user['date_naissance'])) ?> ans
        </p>
      <?php endif; ?>
      <?php if ($user['bio']): ?>
        <p class="profil-bio"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
      <?php endif; ?>

      <div class="profil-stats">
        <div class="stat">
          <span class="stat-nombre"><?= count($mes_sorties) ?></span>
          <span class="stat-label">Sorties créées</span>
        </div>
        <div class="stat">
          <span class="stat-nombre"><?= count($sorties_rejointes) ?></span>
          <span class="stat-label">Sorties rejointes</span>
        </div>
      </div>
      <a href="upload-photo.php" class="cta-btn-small" style="margin-top: 1rem;">
        Gérer mes photos
    </a>
    </div>
    

    <div class="profil-content">

      <div class="profil-section">
        <h3 class="profil-section-title">Modifier mon profil</h3>

        <?php if ($erreur): ?>
          <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($succes): ?>
          <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>
<form method="POST" action="" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="file" id="photo" name="photo" ...

          <div class="form-group">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required />
          </div>
          <div class="form-group">
            <label for="ville">Ville</label>
            <input type="text" id="ville" name="ville" value="<?= htmlspecialchars($user['ville']) ?>" required />
          </div>
          <div class="form-group">
            <label for="date_naissance">Date de naissance</label>
            <input type="date" id="date_naissance" name="date_naissance"
                   value="<?= htmlspecialchars($user['date_naissance'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="3"
                      placeholder="Parle de toi en quelques mots..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="submit-btn">Sauvegarder</button>
          <p class="auth-link" style="margin-top: 1rem;">
            <a href="modifier-mdp.php">Modifier mon mot de passe</a>
          </p>
        </form>
      </div>

      <?php if (!empty($mes_sorties)): ?>
      <div class="profil-section">
        <h3 class="profil-section-title">Mes sorties créées</h3>
        <div class="profil-sorties">
          <?php foreach ($mes_sorties as $sortie): ?>
            <div class="profil-sortie-card">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span class="sortie-activite"><?= htmlspecialchars($sortie['activite']) ?></span>
                <span style="font-size: 12px; color: #a07080;"><?= $sortie['nb_participants'] ?> participant(s)</span>
              </div>
              <div class="sortie-titre" style="font-size: 15px;"><?= htmlspecialchars($sortie['titre']) ?></div>
              <div class="sortie-meta">
                <?= htmlspecialchars($sortie['ville']) ?> —
                <?= date('d/m/Y à H:i', strtotime($sortie['date_sortie'])) ?>
              </div>
              <div style="display: flex; gap: 0.75rem; margin-top: 0.75rem;">
                <a href="modifier-sortie.php?id=<?= $sortie['id'] ?>" class="cta-btn-small">Modifier</a>
                <a href="supprimer-sortie.php?id=<?= $sortie['id'] ?>" style="font-size: 12px; color: #8b1a2a; border: 0.5px solid #d4909a; padding: 6px 14px; border-radius: 6px; text-decoration: none;">Supprimer</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($sorties_rejointes)): ?>
      <div class="profil-section">
        <h3 class="profil-section-title">Sorties que j'ai rejointes</h3>
        <div class="profil-sorties">
          <?php foreach ($sorties_rejointes as $sortie): ?>
            <div class="profil-sortie-card">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span class="sortie-activite"><?= htmlspecialchars($sortie['activite']) ?></span>
                <span style="font-size: 12px; color: #a07080;">par <?= htmlspecialchars($sortie['organisateur']) ?></span>
              </div>
              <div class="sortie-titre" style="font-size: 15px;"><?= htmlspecialchars($sortie['titre']) ?></div>
              <div class="sortie-meta">
                <?= htmlspecialchars($sortie['ville']) ?> —
                <?= date('d/m/Y à H:i', strtotime($sortie['date_sortie'])) ?>
              </div>
              <div style="margin-top: 0.75rem;">
                <a href="quitter-sortie.php?id=<?= $sortie['id'] ?>" style="font-size: 12px; color: #8b1a2a; border: 0.5px solid #d4909a; padding: 6px 14px; border-radius: 6px; text-decoration: none;">Quitter la sortie</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>

<script>
document.getElementById('photo').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(ev) {
    const preview = document.getElementById('sidebarPreview');
    const img = document.createElement('img');
    img.id = 'sidebarPreview';
    img.src = ev.target.result;
    img.style.cssText = 'width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #8b1a2a;';
    preview.replaceWith(img);
  };
  reader.readAsDataURL(file);
});
</script>

<?php require_once 'includes/footer.php'; ?> 