<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/geocode.php';

$ville      = isset($_GET['ville']) ? trim($_GET['ville']) : '';
$activite   = isset($_GET['activite']) ? trim($_GET['activite']) : '';
$date_min   = isset($_GET['date_min']) ? trim($_GET['date_min']) : '';
$date_max   = isset($_GET['date_max']) ? trim($_GET['date_max']) : '';
$places_min = isset($_GET['places_min']) ? max(0, (int) $_GET['places_min']) : 0;
$rayon      = isset($_GET['rayon']) ? (int) $_GET['rayon'] : 0;
$tri        = isset($_GET['tri']) ? trim($_GET['tri']) : 'date';
$tris_valides = ['date', 'places', 'popularite', 'distance'];

if (!in_array($tri, $tris_valides, true)) {
    $tri = 'date';
}

if ($date_min !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_min)) {
    $date_min = '';
}

if ($date_max !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_max)) {
    $date_max = '';
}

$sql = "SELECT s.*, u.prenom, COALESCE(likes.nb_likes, 0) AS nb_likes
        FROM sorties s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN (
            SELECT sortie_id, COUNT(*) AS nb_likes
            FROM likes_sorties
            GROUP BY sortie_id
        ) likes ON likes.sortie_id = s.id
        WHERE s.places_restantes > 0 
        AND s.date_sortie > NOW()
        AND (s.status IS NULL OR s.status = '' OR s.status = 'open')
        AND (u.account_status IS NULL OR u.account_status = '' OR u.account_status = 'active')";

$params = [];

if (isset($_SESSION['user_id'])) {
    $sql .= " AND s.user_id <> ?";
    $params[] = (int) $_SESSION['user_id'];
}

if ($ville) {
    $sql .= " AND s.ville LIKE ?";
    $params[] = "%$ville%";
}

if ($activite) {
    $sql .= " AND s.activite LIKE ?";
    $params[] = "%$activite%";
}

if ($date_min !== '') {
    $sql .= " AND s.date_sortie >= ?";
    $params[] = $date_min . ' 00:00:00';
}

if ($date_max !== '') {
    $sql .= " AND s.date_sortie <= ?";
    $params[] = $date_max . ' 23:59:59';
}

if ($places_min > 0) {
    $sql .= " AND s.places_restantes >= ?";
    $params[] = $places_min;
}

$sql .= match ($tri) {
    'places' => " ORDER BY s.places_restantes DESC, s.date_sortie ASC",
    'popularite' => " ORDER BY nb_likes DESC, s.date_sortie ASC",
    default => " ORDER BY s.date_sortie ASC",
};

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
            if ((int) $p['user_id'] === (int) $_SESSION['user_id']) {
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

if ($tri === 'distance' && $user_lat && $user_lon) {
    usort($sorties, static function (array $a, array $b): int {
        if ($a['distance'] === null && $b['distance'] === null) {
            return strtotime((string) $a['date_sortie']) <=> strtotime((string) $b['date_sortie']);
        }

        if ($a['distance'] === null) {
            return 1;
        }

        if ($b['distance'] === null) {
            return -1;
        }

        return $a['distance'] <=> $b['distance'];
    });
}

$likes_user = [];

if (isset($_SESSION['user_id']) && !empty($sorties)) {
    $ids_sorties = array_column($sorties, 'id');
    $placeholders = implode(',', array_fill(0, count($ids_sorties), '?'));
    $params_likes = array_merge([(int) $_SESSION['user_id']], $ids_sorties);

    $stmt = $pdo->prepare("
        SELECT sortie_id
        FROM likes_sorties
        WHERE user_id = ?
        AND sortie_id IN ($placeholders)
    ");
    $stmt->execute($params_likes);

    foreach ($stmt->fetchAll() as $like) {
        $likes_user[(int) $like['sortie_id']] = true;
    }
}

$filtres_actifs = $ville !== ''
    || $activite !== ''
    || $date_min !== ''
    || $date_max !== ''
    || $places_min > 0
    || $rayon > 0
    || $tri !== 'date';
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Sorties disponibles</h1>
    <?php if (isset($_SESSION['user_id'])): ?>
      <div class="sortie-actions">
        <a href="mes-sorties.php" class="cta-btn-outline">Mes sorties</a>
        <a href="../actions/creer-sortie.php" class="cta-btn">+ Proposer une sortie</a>
      </div>
    <?php endif; ?>
  </div>

  <form method="GET" action="" class="filtres">
    <input type="text" name="ville" placeholder="Ville..." value="<?= e($ville) ?>" />
    <input type="date" name="date_min" value="<?= e($date_min) ?>" aria-label="Date minimum" title="Date minimum" />
    <input type="date" name="date_max" value="<?= e($date_max) ?>" aria-label="Date maximum" title="Date maximum" />
    <input type="number" name="places_min" min="0" placeholder="Places min." value="<?= $places_min > 0 ? (int) $places_min : '' ?>" />
    <input type="text" name="activite" placeholder="Activite..." value="<?= e($activite) ?>" />
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
    <select name="tri">
      <option value="date" <?= $tri === 'date' ? 'selected' : '' ?>>Plus proche date</option>
      <option value="places" <?= $tri === 'places' ? 'selected' : '' ?>>Places disponibles</option>
      <option value="popularite" <?= $tri === 'popularite' ? 'selected' : '' ?>>Plus aimees</option>
      <?php if (isset($_SESSION['user_id']) && $user_lat && $user_lon): ?>
        <option value="distance" <?= $tri === 'distance' ? 'selected' : '' ?>>Plus proche de moi</option>
      <?php endif; ?>
    </select>
    <div class="filtres-actions">
      <button type="submit" class="submit-btn">Filtrer</button>
      <?php if ($filtres_actifs): ?>
        <a href="sorties.php" class="cta-btn-outline">Reinitialiser</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if (empty($sorties)): ?>
    <div class="empty-state">
      <p>Aucune sortie disponible pour le moment.</p>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="../actions/creer-sortie.php" class="cta-btn" style="margin-top: 1rem; display: inline-block;">
          Sois le premier à en proposer une
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p class="result-count"><?= count($sorties) ?> sortie<?= count($sorties) > 1 ? 's' : '' ?> trouvee<?= count($sorties) > 1 ? 's' : '' ?></p>
    <div class="sorties-grid">
      <?php foreach ($sorties as $sortie): ?>

        <?php
          // ── AVANT : requête SQL ici dans la boucle ──
          // ── MAINTENANT : simple lookup tableau ──
        $deja_inscrit = isset($participations_user[$sortie['id']]);
        $participants = $participants_par_sortie[$sortie['id']] ?? [];
        $deja_like = isset($likes_user[(int) $sortie['id']]);
        $nb_likes = (int) $sortie['nb_likes'];
        ?>

        <div class="sortie-card">
          <div class="sortie-header">
            <span class="sortie-activite"><?= htmlspecialchars($sortie['activite']) ?></span>
            <span class="sortie-places"><?= $sortie['places_restantes'] ?> place(s)</span>
          </div>
          <h2 class="sortie-titre"><a href="sortie.php?id=<?= (int) $sortie['id'] ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($sortie['titre']) ?></a></h2>
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

<div class="sortie-footer <?= isset($_SESSION['user_id']) && $sortie['user_id'] !== $_SESSION['user_id'] && !$deja_inscrit ? 'sortie-footer-join' : '' ?>">
  <div class="sortie-author-block">
    <span class="sortie-auteur">Propose par <a href="profil-public.php?id=<?= (int) $sortie['user_id'] ?>" class="back-link"><?= htmlspecialchars($sortie['prenom']) ?></a></span>
  </div>

  <?php if (isset($_SESSION['user_id'])): ?>
    <?php if ($sortie['user_id'] === $_SESSION['user_id']): ?>
      <div class="sortie-card-actions">
        <div class="sortie-actions">
          <a href="sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Details</a><a href="../actions/modifier-sortie.php?id=<?= $sortie['id'] ?>" class="cta-btn-small">Modifier</a>
          <a href="../actions/supprimer-sortie.php?id=<?= $sortie['id'] ?>" class="cta-btn-small">Supprimer</a>
        </div>
        <?php if (!empty($participants)): ?>
          <a href="messages.php" class="cta-btn-small">
            Messages (<?= count($participants) ?>)
          </a>
        <?php else: ?>
          <span class="sortie-note">En attente de participants</span>
        <?php endif; ?>
      </div>

    <?php elseif ($deja_inscrit): ?>
      <div class="sortie-card-actions">
        <div class="sortie-actions">
          <a href="sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Details</a>
          <a href="conversation.php?sortie=<?= (int) $sortie['id'] ?>&user=<?= (int) $sortie['user_id'] ?>" class="cta-btn-small">Messagerie</a>
        </div>
        <div class="report-links">
          <a href="../actions/signaler.php?type=user&target=<?= (int)$sortie['user_id'] ?>">Signaler le profil</a>
          <a href="../actions/signaler.php?type=sortie&target=<?= (int)$sortie['id'] ?>">Signaler la sortie</a>
        </div>
      </div>

        <?php else: ?>
      <div class="sortie-card-actions">
        <div class="sortie-actions sortie-actions-join">
          <button type="button"
                  class="like-btn <?= $deja_like ? 'is-liked' : '' ?>"
                  onclick="toggleLike(<?= $sortie['id'] ?>, this)" 
                  data-liked="<?= $deja_like ? '1' : '0' ?>"
                  data-count="<?= $nb_likes ?>">
            <?= $deja_like ? '♥ ' . $nb_likes : '♡ ' . $nb_likes ?>
          </button>
          <a href="sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Details</a>
          <a href="conversation.php?sortie=<?= (int) $sortie['id'] ?>&user=<?= (int) $sortie['user_id'] ?>" class="cta-btn-small">Message</a>
          <a href="../actions/rejoindre.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Rejoindre</a>
        </div>
        <div class="report-links">
          <a href="../actions/signaler.php?type=user&target=<?= (int)$sortie['user_id'] ?>">Signaler le profil</a>
          <a href="../actions/signaler.php?type=sortie&target=<?= (int)$sortie['id'] ?>">Signaler la sortie</a>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</div>

<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<script>
function toggleLike(sortieId, btn) {
  const liked = btn.dataset.liked === '1';
  const count = parseInt(btn.dataset.count);
  const newLiked = !liked;
  const newCount = newLiked ? count + 1 : count - 1;

  btn.dataset.liked = newLiked ? '1' : '0';
  btn.dataset.count = newCount;
  btn.textContent = (newLiked ? '♥ ' : '♡ ') + newCount;
  btn.classList.toggle('is-liked', newLiked);

  fetch('/Site_rencontre/RencontreIRL/app/actions/like-sortie.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'sortie_id=' + encodeURIComponent(sortieId)
      + '&ajax=1'
      + '&csrf_token=<?= urlencode(csrf_token()) ?>'
  });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
