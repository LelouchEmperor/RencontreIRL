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

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS reports_open
    FROM reports
");
$reports_stats = $stmt->fetch();

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN account_status = 'suspended' THEN 1 ELSE 0 END) AS suspended_total,
        SUM(CASE WHEN account_status = 'banned' THEN 1 ELSE 0 END) AS banned_total
    FROM users
");
$users_stats = $stmt->fetch();

$stmt = $pdo->query("
    SELECT COUNT(*) AS failed_24h
    FROM security_events
    WHERE event_type IN ('login_failed', 'login_rate_limited')
    AND created_at >= (NOW() - INTERVAL 24 HOUR)
");
$security_stats = $stmt->fetch();

$stmt = $pdo->query("
    SELECT COUNT(*) AS photos_pending
    FROM photos_profil
    WHERE moderation_status = 'pending'
");
$photos_stats = $stmt->fetch();

$admin_links = [
    [
        'href' => 'users.php',
        'titre' => 'Utilisateurs',
        'description' => 'Gerer les comptes, statuts et suppressions.',
        'badge' => (int) ($users_stats['suspended_total'] ?? 0) + (int) ($users_stats['banned_total'] ?? 0) . ' comptes restreints',
    ],
    [
        'href' => 'reports.php',
        'titre' => 'Signalements',
        'description' => 'Traiter les signalements de profils, sorties et messages.',
        'badge' => (int) ($reports_stats['reports_open'] ?? 0) . ' en attente',
    ],
    [
        'href' => 'security.php',
        'titre' => 'Securite',
        'description' => 'Consulter les connexions, echecs et actions sensibles.',
        'badge' => (int) ($security_stats['failed_24h'] ?? 0) . ' alertes / 24h',
    ],
    [
        'href' => 'photos.php',
        'titre' => 'Photos',
        'description' => 'Valider ou refuser les photos de profil envoyees.',
        'badge' => (int) ($photos_stats['photos_pending'] ?? 0) . ' en attente',
    ],
];
?>

<section class="section settings-section">
  <h1 class="section-title">Administration</h1>

  <div class="admin-stats-grid" style="margin-bottom: 1.5rem;">
    <div class="admin-stat-card">
      <strong><?= (int) ($users_stats['total'] ?? 0) ?></strong>
      <span>Utilisateurs</span>
    </div>
    <div class="admin-stat-card">
      <strong><?= (int) ($reports_stats['reports_open'] ?? 0) ?></strong>
      <span>Signalements ouverts</span>
    </div>
    <div class="admin-stat-card">
      <strong><?= (int) ($security_stats['failed_24h'] ?? 0) ?></strong>
      <span>Alertes securite / 24h</span>
    </div>
    <div class="admin-stat-card">
      <strong><?= (int) ($photos_stats['photos_pending'] ?? 0) ?></strong>
      <span>Photos en attente</span>
    </div>
  </div>

  <div class="settings-links">
    <?php foreach ($admin_links as $link): ?>
      <a href="<?= e($link['href']) ?>" class="settings-link">
        <div>
          <strong><?= e($link['titre']) ?></strong>
          <small><?= e($link['description']) ?></small>
        </div>
        <span class="admin-status-pill"><?= e($link['badge']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
