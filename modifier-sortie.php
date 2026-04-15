<?php
require_once 'includes/header.php';
require_once 'config/db.php';
require_once 'config/geocode.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /RencontreIRL/auth/connexion.php');
    exit;
}

$sortie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$sortie_id) {
    header('Location: /RencontreIRL/sorties.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ? AND user_id = ?");
$stmt->execute([$sortie_id, $_SESSION['user_id']]);
$sortie = $stmt->fetch();

if (!$sortie) {
    header('Location: /RencontreIRL/sorties.php');
    exit;
}

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_verify();
    $titre       = trim($_POST['titre']);
    $activite    = trim($_POST['activite']);
    $ville       = trim($_POST['ville']);
    $adresse = trim($_POST['adresse'] ?? '');
    $description = trim($_POST['description']);
    $date_sortie = $_POST['date_sortie'];
    $places      = (int) $_POST['places'];

    if (empty($titre) || empty($activite) || empty($ville) || empty($date_sortie) || $places < 2) {
        $erreur = 'Tous les champs obligatoires doivent être remplis.';
    } else {
        $coords = geocoder_ville($ville);

        if (!$coords && $ville !== $sortie['ville']) {
            $erreur = 'Ville introuvable. Vérifie l\'orthographe.';
        } else {
            $lat = $coords ? $coords['latitude'] : $sortie['latitude'];
            $lon = $coords ? $coords['longitude'] : $sortie['longitude'];

            $nb_participants = $sortie['places_total'] - $sortie['places_restantes'];
            $nouvelles_restantes = max(0, $places - $nb_participants);

            $stmt = $pdo->prepare("
                UPDATE sorties 
                SET titre = ?, activite = ?, ville = ?,adresse = ?, description = ?, 
                    date_sortie = ?, places_total = ?, places_restantes = ?,
                    latitude = ?, longitude = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $titre, $activite, $ville, $adresse, $description,
                $date_sortie, $places, $nouvelles_restantes,
                $lat, $lon, $sortie_id, $_SESSION['user_id']
            ]);
            header('Location: /Site_rencontre/RencontreIRL/profil.php');
            exit;
        }
    }
}
?>

<section class="auth-section">
  <div class="auth-card" style="max-width: 560px;">
    <h1 class="auth-title">Modifier la sortie</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
      <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>
      <div class="form-group">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" value="<?= htmlspecialchars($sortie['titre']) ?>" required />
      </div>
      <div class="form-group">
        <label for="activite">Activité</label>
        <input type="text" id="activite" name="activite" value="<?= htmlspecialchars($sortie['activite']) ?>" required />
      </div>
      <div class="form-group">
        <label for="ville">Ville</label>
        <input type="text" id="ville" name="ville" value="<?= htmlspecialchars($sortie['ville']) ?>" required />
      </div>
      <div class="form-group">
  <label for="adresse">Adresse (optionnel)</label>
  <input type="text" id="adresse" name="adresse"
         value="<?= htmlspecialchars($sortie['adresse'] ?? '') ?>"
         placeholder="12 rue de la Paix, Place du marché..." />
</div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($sortie['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label for="date_sortie">Date et heure</label>
        <input type="datetime-local" id="date_sortie" name="date_sortie"
               value="<?= date('Y-m-d\TH:i', strtotime($sortie['date_sortie'])) ?>" required />
      </div>
      <div class="form-group">
        <label for="places">Nombre de places total</label>
        <input type="number" id="places" name="places" min="2" max="20"
               value="<?= $sortie['places_total'] ?>" required />
      </div>
      <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
        <a href="profil.php" class="cta-btn-outline" style="flex: 1; text-align: center; padding: 13px;">Annuler</a>
        <button type="submit" class="submit-btn" style="flex: 1; margin-top: 0;">Sauvegarder</button>
      </div>
    </form>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>