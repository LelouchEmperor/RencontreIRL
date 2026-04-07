<?php
require_once 'includes/header.php';
require_once 'config/db.php';
require_once 'config/geocode.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /rencontre_site_web/auth/connexion.php');
    exit;
}

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']);
    $activite    = trim($_POST['activite']);
    $ville       = trim($_POST['ville']);
    $description = trim($_POST['description']);
    $date_sortie = $_POST['date_sortie'];
    $places      = (int) $_POST['places'];

    if (empty($titre) || empty($activite) || empty($ville) || empty($date_sortie) || $places < 2) {
        $erreur = 'Tous les champs obligatoires doivent être remplis.';
    } else {
      $coords = geocoder_ville($ville);

      if (!$coords) {
          $erreur = 'Ville introuvable. Vérifie l\'orthographe et réessaie.';
      } else {
          $lat = $coords['latitude'];
          $lon = $coords['longitude'];
  
          $stmt = $pdo->prepare("INSERT INTO sorties (user_id, titre, activite, ville, description, date_sortie, places_total, places_restantes, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $stmt->execute([
              $_SESSION['user_id'],
              $titre,
              $activite,
              $ville,
              $description,
              $date_sortie,
              $places,
              $places,
              $lat,
              $lon
          ]);
        $succes = 'Sortie créée ! <a href="sorties.php">Voir les sorties</a>';
        header('Location: /rencontre_site_web/sorties.php');
          exit;
        }
    }
}
?>

<section class="auth-section">
  <div class="auth-card" style="max-width: 560px;">
    <h1 class="auth-title">Proposer une sortie</h1>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
      <div class="alert alert-success"><?= $succes ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="titre">Titre de la sortie</label>
        <input type="text" id="titre" name="titre" placeholder="Rando au Mont Saint-Michel" required />
      </div>
      <div class="form-group">
        <label for="activite">Activité</label>
        <input type="text" id="activite" name="activite" placeholder="Randonnée, Running, Cuisine..." required />
      </div>
      <div class="form-group">
        <label for="ville">Ville</label>
        <input type="text" id="ville" name="ville" placeholder="Caen" required />
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" placeholder="Décris ta sortie..."></textarea>
      </div>
      <div class="form-group">
        <label for="date_sortie">Date et heure</label>
        <input type="datetime-local" id="date_sortie" name="date_sortie" required />
      </div>
      <div class="form-group">
        <label for="places">Nombre de places (vous inclus)</label>
        <input type="number" id="places" name="places" min="2" max="20" value="2" required />
      </div>
      <button type="submit" class="submit-btn">Proposer la sortie</button>
    </form>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>