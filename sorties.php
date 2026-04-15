<?php
require_once 'includes/header.php';
require_once 'config/db.php';
require_once 'config/geocode.php';

$ville    = isset($_GET['ville']) ? trim($_GET['ville']) : '';
$activite = isset($_GET['activite']) ? trim($_GET['activite']) : '';
$rayon    = isset($_GET['rayon']) ? (int) $_GET['rayon'] : 0;

$sql = "SELECT s.*, u.prenom FROM sorties s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.places_restantes > 0 
        AND s.date_sortie > NOW()";

$params = [];

if ($ville) {
    $sql .= " AND s.ville LIKE ?";
    $params[] = "%$ville%";
}

if ($activite) {
    $sql .= " AND s.activite LIKE ?";
    $params[] = "%$activite%";
}

$sql .= " ORDER BY s.date_sortie ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$toutes_sorties = $stmt->fetchAll();

$user_lat = null;
$user_lon = null;

// ── NOUVEAU : récupérer les participations ET les participants en une seule requête ──
$participations_user = [];   // [sortie_id => true]
$participants_par_sortie = []; // [sortie_id => [{user_id, prenom}, ...]]

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT latitude, longitude FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $user_lat = $user['latitude'];
    $user_lon = $user['longitude'];

    if (!empty($toutes_sorties)) {
        $ids_sorties = array_column($toutes_sorties, 'id');
        $placeholders = implode(',', array_fill(0, count($ids_sorties), '?'));

        // Toutes les participations sur ces sorties
        $stmt = $pdo->prepare("
            SELECT p.sortie_id, p.user_id, u.prenom
            FROM participations p
            JOIN users u ON p.user_id = u.id
            WHERE p.sortie_id IN ($placeholders)
        ");
        $stmt->execute($ids_sorties);
        $toutes_participations = $stmt->fetchAll();

        foreach ($toutes_participations as $p) {
            // Est-ce que l'user courant participe à cette sortie ?
            if ($p['user_id'] === $_SESSION['user_id']) {
                $participations_user[$p['sortie_id']] = true;
            }
            // Liste des participants par sortie (pour l'organisateur)
            $participants_par_sortie[$p['sortie_id']][] = $p;
        }
    }
}

// Filtrage par rayon (inchangé)
$sorties = [];
foreach ($toutes_sorties as $sortie) {
    if ($rayon > 0 && $user_lat && $user_lon && $sortie['latitude'] && $sortie['longitude']) {
        $dist = distance_km($user_lat, $user_lon, $sortie['latitude'], $sortie['longitude']);
        if ($dist <= $rayon) {
            $sortie['distance'] = $dist;
            $sorties[] = $sortie;
        }
    } else {
        $sortie['distance'] = null;
        $sorties[] = $sortie;
    }
}
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Sorties disponibles</h1>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="creer-sortie.php" class="cta-btn">+ Proposer une sortie</a>
    <?php endif; ?>
  </div>

  <form method="GET" action="" class="filtres">
    <input type="text" name="ville" placeholder="Ville..." value="<?= htmlspecialchars($ville) ?>" />
    <input type="text" name="activite" placeholder="Activité..." value="<?= htmlspecialchars($activite) ?>" />
    <?php if (isset($_SESSION['user_id']) && $user_lat && $user_lon): ?>
      <select name="rayon">
        <option value="0" <?= $rayon === 0 ? 'selected' : '' ?>>Toute la France</option>
        <option value="10" <?= $rayon === 10 ? 'selected' : '' ?>>Dans 10 km</option>
        <option value="25" <?= $rayon === 25 ? 'selected' : '' ?>>Dans 25 km</option>
        <option value="50" <?= $rayon === 50 ? 'selected' : '' ?>>Dans 50 km</option>
        <option value="100" <?= $rayon === 100 ? 'selected' : '' ?>>Dans 100 km</option>
        <option value="200" <?= $rayon === 200 ? 'selected' : '' ?>>Dans 200 km</option>
      </select>
    <?php endif; ?>
    <button type="submit" class="submit-btn" style="width: auto; padding: 10px 20px;">Filtrer</button>
  </form>

  <?php if (empty($sorties)): ?>
    <div class="empty-state">
      <p>Aucune sortie disponible pour le moment.</p>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="creer-sortie.php" class="cta-btn" style="margin-top: 1rem; display: inline-block;">
          Sois le premier à en proposer une
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="sorties-grid">
      <?php foreach ($sorties as $sortie): ?>

        <?php
          // ── AVANT : requête SQL ici dans la boucle ──
          // ── MAINTENANT : simple lookup tableau ──
        $deja_inscrit = isset($participations_user[$sortie['id']]);
        $participants = $participants_par_sortie[$sortie['id']] ?? [];

        ?>

        <div class="sortie-card">
          <div class="sortie-header">
            <span class="sortie-activite"><?= htmlspecialchars($sortie['activite']) ?></span>
            <span class="sortie-places"><?= $sortie['places_restantes'] ?> place(s)</span>
          </div>
          <h2 class="sortie-titre"><?= htmlspecialchars($sortie['titre']) ?></h2>
<p class="sortie-meta">
  <?= htmlspecialchars($sortie['ville']) ?>
  <?php if (!empty($sortie['adresse'])): ?>
    — <?= htmlspecialchars($sortie['adresse']) ?>
  <?php endif; ?>
</p>
<p class="sortie-meta" style="margin-top: 0.25rem;">
  <?= date('d/m/Y à H:i', strtotime($sortie['date_sortie'])) ?>
  <?php if ($sortie['distance'] !== null): ?>
    — <span style="color: #8b1a2a;"><?= $sortie['distance'] ?> km</span>
  <?php endif; ?>
</p>
          <?php if ($sortie['description']): ?>
            <p class="sortie-desc"><?= htmlspecialchars($sortie['description']) ?></p>
          <?php endif; ?>

          <div class="sortie-footer">
            <span class="sortie-auteur">Proposé par <?= htmlspecialchars($sortie['prenom']) ?></span>

            <?php if (isset($_SESSION['user_id'])): ?>
                  <?php if ($sortie['user_id'] === $_SESSION['user_id']): ?>
      <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end;">
        <div style="display: flex; gap: 0.5rem;">
          <a href="modifier-sortie.php?id=<?= $sortie['id'] ?>" class="cta-btn-small">Modifier</a>
          <a href="supprimer-sortie.php?id=<?= $sortie['id'] ?>" style="font-size: 12px; color: #8b1a2a; border: 0.5px solid #d4909a; padding: 6px 14px; border-radius: 6px; text-decoration: none;">Supprimer</a>
        </div>
        <?php if (!empty($participants)): ?>
          <a href="messages.php" class="cta-btn-small">
            Messages (<?= count($participants) ?>)
          </a>
        <?php else: ?>
          <span style="font-size: 12px; color: #c4a0a8;">En attente de participants</span>
        <?php endif; ?>
      </div>

    <?php elseif ($deja_inscrit): ?>
      <a href="conversation.php?sortie=<?= $sortie['id'] ?>&user=<?= $sortie['user_id'] ?>"
         class="cta-btn-small">Messagerie</a>

    <?php else: ?>
      <a href="rejoindre.php?id=<?= $sortie['id'] ?>" class="cta-btn-small">Rejoindre</a>

    <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>