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
    $prenom = trim($_POST['prenom']);
    $ville  = trim($_POST['ville']);
    $bio    = trim($_POST['bio']);

    if (empty($prenom) || empty($ville)) {
        $erreur = 'Le prénom et la ville sont obligatoires.';
    } else {
        $coords = geocoder_ville($ville);
        $lat = $coords ? $coords['latitude'] : $user['latitude'];
        $lon = $coords ? $coords['longitude'] : $user['longitude'];

        if (!$coords) {
            $erreur = 'Ville introuvable — coordonnées inchangées.';
        }

        $stmt = $pdo->prepare("UPDATE users SET prenom = ?, ville = ?, bio = ?, latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$prenom, $ville, $bio, $lat, $lon, $user_id]);

        $_SESSION['prenom'] = $prenom;

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$erreur) {
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
      <div class="profil-avatar">
        <?= strtoupper(substr($user['prenom'], 0, 1)) ?>
      </div>
      <h2 class="profil-nom"><?= htmlspecialchars($user['prenom']) ?></h2>
      <p class="profil-ville">
        <?= htmlspecialchars($user['ville']) ?>
        <?php if ($user['latitude']): ?>
          <span style="color: #4a8a4a; font-size: 11px;">✓ géolocalisé</span>
        <?php else: ?>
          <span style="color: #8a4a4a; font-size: 11px;">non géolocalisé</span>
        <?php endif; ?>
      </p>
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

        <form method="POST" action="">
          <div class="form-group">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom"
                   value="<?= htmlspecialchars($user['prenom']) ?>" required />
          </div>
          <div class="form-group">
            <label for="ville">Ville</label>
            <input type="text" id="ville" name="ville"
                   value="<?= htmlspecialchars($user['ville']) ?>" required />
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
                <span style="font-size: 12px; color: #4a5a4a;">par <?= htmlspecialchars($sortie['organisateur']) ?></span>
              </div>
              <div class="sortie-titre" style="font-size: 15px;"><?= htmlspecialchars($sortie['titre']) ?></div>
              <div class="sortie-meta">
                <?= htmlspecialchars($sortie['ville']) ?> —
                <?= date('d/m/Y à H:i', strtotime($sortie['date_sortie'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>