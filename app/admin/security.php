<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$admin_id = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$currentUser = $stmt->fetch();

if (empty($currentUser['is_admin'])) {
    http_response_code(403);
    die('Acces refuse.');
}

$types = [
    'all' => 'Tous',
    'login_success' => 'Connexions reussies',
    'login_failed' => 'Echecs connexion',
    'login_rate_limited' => 'Blocages temporaires',
    'password_changed' => 'Mots de passe',
    'admin_user_status_changed' => 'Actions admin',
    'report_created' => 'Signalements crees',
    'report_rate_limited' => 'Signalements limites',
    'message_rate_limited' => 'Messages limites',
];

$filtre_type = $_GET['type'] ?? 'all';
if (!array_key_exists($filtre_type, $types)) {
    $filtre_type = 'all';
}

$where = '';
$params = [];
if ($filtre_type !== 'all') {
    $where = 'WHERE se.event_type = ?';
    $params[] = $filtre_type;
}

$stmt = $pdo->prepare("
    SELECT se.*, u.prenom
    FROM security_events se
    LEFT JOIN users u ON u.id = se.user_id
    $where
    ORDER BY se.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$events = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT
        SUM(CASE WHEN event_type = 'login_failed' AND created_at >= (NOW() - INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS failed_24h,
        SUM(CASE WHEN event_type = 'login_rate_limited' AND created_at >= (NOW() - INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS limited_24h,
        SUM(CASE WHEN event_type = 'login_success' AND created_at >= (NOW() - INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS success_24h
    FROM security_events
");
$stats = $stmt->fetch();
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Journal de securite</h1>
  </div>

  <div class="admin-stats-grid">
    <div class="admin-stat-card">
      <strong><?= (int) ($stats['success_24h'] ?? 0) ?></strong>
      <span>Connexions reussies / 24h</span>
    </div>
    <div class="admin-stat-card">
      <strong><?= (int) ($stats['failed_24h'] ?? 0) ?></strong>
      <span>Echecs / 24h</span>
    </div>
    <div class="admin-stat-card">
      <strong><?= (int) ($stats['limited_24h'] ?? 0) ?></strong>
      <span>Blocages / 24h</span>
    </div>
  </div>

  <form method="GET" class="admin-users-filters" style="margin-top: 1.5rem;">
    <select name="type">
      <?php foreach ($types as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filtre_type === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="cta-btn-small">Filtrer</button>
  </form>

  <div class="admin-report-list" style="margin-top: 1.5rem;">
    <?php if (empty($events)): ?>
      <div class="empty-state">Aucun evenement trouve.</div>
    <?php else: ?>
      <?php foreach ($events as $event): ?>
        <article class="admin-report-card">
          <div class="admin-report-main">
            <div>
              <p class="admin-report-meta">#<?= (int) $event['id'] ?> - <?= date('d/m/Y a H:i', strtotime($event['created_at'])) ?></p>
              <h2 class="admin-report-title"><?= e($event['event_type']) ?></h2>
              <p class="admin-report-meta">
                Utilisateur : <?= e($event['prenom'] ?: ($event['email'] ?: 'Inconnu')) ?> -
                IP : <?= e($event['ip_address']) ?>
              </p>
              <?php if (!empty($event['details'])): ?>
                <p class="admin-report-details"><?= e($event['details']) ?></p>
              <?php endif; ?>
              <?php if (!empty($event['user_agent'])): ?>
                <p class="admin-report-meta"><?= e(texte_court($event['user_agent'], 160)) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
