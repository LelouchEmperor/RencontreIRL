<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
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

$allowed_statuses = ['all', 'active', 'suspended', 'banned'];
$allowed_roles = ['all', 'admin', 'user'];
$allowed_email = ['all', 'verified', 'unverified'];

$filtre_status = $_GET['status'] ?? 'all';
$filtre_role = $_GET['role'] ?? 'all';
$filtre_email = $_GET['email'] ?? 'all';
$recherche = trim($_GET['q'] ?? '');

if (!in_array($filtre_status, $allowed_statuses, true)) {
    $filtre_status = 'all';
}

if (!in_array($filtre_role, $allowed_roles, true)) {
    $filtre_role = 'all';
}

if (!in_array($filtre_email, $allowed_email, true)) {
    $filtre_email = 'all';
}

$where = [];
$params = [];

if ($filtre_status !== 'all') {
    if ($filtre_status === 'active') {
        $where[] = "(u.account_status IS NULL OR u.account_status = '' OR u.account_status = 'active')";
    } else {
        $where[] = "u.account_status = ?";
        $params[] = $filtre_status;
    }
}

if ($filtre_role === 'admin') {
    $where[] = "u.is_admin = 1";
} elseif ($filtre_role === 'user') {
    $where[] = "(u.is_admin IS NULL OR u.is_admin = 0)";
}

if ($filtre_email === 'verified') {
    $where[] = "u.email_verifie = 1";
} elseif ($filtre_email === 'unverified') {
    $where[] = "(u.email_verifie IS NULL OR u.email_verifie = 0)";
}

if ($recherche !== '') {
    $where[] = "(u.prenom LIKE ? OR u.email LIKE ? OR u.ville LIKE ?)";
    $terme = '%' . $recherche . '%';
    $params[] = $terme;
    $params[] = $terme;
    $params[] = $terme;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN account_status IS NULL OR account_status = '' OR account_status = 'active' THEN 1 ELSE 0 END) AS active_total,
        SUM(CASE WHEN account_status = 'suspended' THEN 1 ELSE 0 END) AS suspended_total,
        SUM(CASE WHEN account_status = 'banned' THEN 1 ELSE 0 END) AS banned_total,
        SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) AS admin_total
    FROM users
");
$stats = $stmt->fetch();

$par_page = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where_sql");
$stmt->execute($params);
$total_filtre = (int) $stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_filtre / $par_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $par_page;
}

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.prenom,
        u.email,
        u.email_verifie,
        u.ville,
        u.created_at,
        u.account_status,
        u.is_admin,
        COUNT(DISTINCT s.id) AS nb_sorties,
        COUNT(DISTINCT p.id) AS nb_participations,
        COUNT(DISTINCT r.id) AS nb_reports_recus
    FROM users u
    LEFT JOIN sorties s ON s.user_id = u.id
    LEFT JOIN participations p ON p.user_id = u.id
    LEFT JOIN reports r ON r.target_type = 'user' AND r.target_id = u.id
    $where_sql
    GROUP BY u.id
    ORDER BY u.created_at DESC, u.id DESC
    LIMIT $par_page OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

function admin_users_url(array $overrides = []): string
{
    $params = array_merge([
        'status' => $_GET['status'] ?? 'all',
        'role' => $_GET['role'] ?? 'all',
        'email' => $_GET['email'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ], $overrides);

    $params = array_filter($params, static fn ($value) => $value !== '' && $value !== null);

    return app_url('app/admin/users.php' . ($params ? '?' . http_build_query($params) : ''));
}

$redirect_courant = 'app/admin/users.php';
$query_courante = $_SERVER['QUERY_STRING'] ?? '';
if ($query_courante !== '') {
    $redirect_courant .= '?' . $query_courante;
}
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Admin - Utilisateurs</h1>
    <a href="reports.php" class="cta-btn-outline">Signalements</a>
  </div>

  <div class="admin-report-toolbar">
    <a href="<?= e(admin_users_url(['status' => 'all', 'page' => 1])) ?>" class="admin-filter-link <?= $filtre_status === 'all' ? 'is-active' : '' ?>">
      Tous <span><?= (int) $stats['total'] ?></span>
    </a>
    <a href="<?= e(admin_users_url(['status' => 'active', 'page' => 1])) ?>" class="admin-filter-link <?= $filtre_status === 'active' ? 'is-active' : '' ?>">
      Actifs <span><?= (int) $stats['active_total'] ?></span>
    </a>
    <a href="<?= e(admin_users_url(['status' => 'suspended', 'page' => 1])) ?>" class="admin-filter-link <?= $filtre_status === 'suspended' ? 'is-active' : '' ?>">
      Suspendus <span><?= (int) $stats['suspended_total'] ?></span>
    </a>
    <a href="<?= e(admin_users_url(['status' => 'banned', 'page' => 1])) ?>" class="admin-filter-link <?= $filtre_status === 'banned' ? 'is-active' : '' ?>">
      Bannis <span><?= (int) $stats['banned_total'] ?></span>
    </a>
    <a href="<?= e(admin_users_url(['role' => 'admin', 'page' => 1])) ?>" class="admin-filter-link <?= $filtre_role === 'admin' ? 'is-active' : '' ?>">
      Admins <span><?= (int) $stats['admin_total'] ?></span>
    </a>
  </div>

  <form method="GET" action="" class="admin-users-filters">
    <input type="text" name="q" placeholder="Prenom, email, ville..." value="<?= e($recherche) ?>">
    <select name="status">
      <option value="all" <?= $filtre_status === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
      <option value="active" <?= $filtre_status === 'active' ? 'selected' : '' ?>>Actifs</option>
      <option value="suspended" <?= $filtre_status === 'suspended' ? 'selected' : '' ?>>Suspendus</option>
      <option value="banned" <?= $filtre_status === 'banned' ? 'selected' : '' ?>>Bannis</option>
    </select>
    <select name="role">
      <option value="all" <?= $filtre_role === 'all' ? 'selected' : '' ?>>Tous les roles</option>
      <option value="user" <?= $filtre_role === 'user' ? 'selected' : '' ?>>Utilisateurs</option>
      <option value="admin" <?= $filtre_role === 'admin' ? 'selected' : '' ?>>Admins</option>
    </select>
    <select name="email">
      <option value="all" <?= $filtre_email === 'all' ? 'selected' : '' ?>>Tous les emails</option>
      <option value="verified" <?= $filtre_email === 'verified' ? 'selected' : '' ?>>Emails verifies</option>
      <option value="unverified" <?= $filtre_email === 'unverified' ? 'selected' : '' ?>>Emails non verifies</option>
    </select>
    <button type="submit" class="submit-btn">Filtrer</button>
  </form>

  <p class="admin-report-count">
    <?= (int) $total_filtre ?> utilisateur<?= $total_filtre > 1 ? 's' : '' ?> affiche<?= $total_filtre > 1 ? 's' : '' ?>
    <?php if ($total_pages > 1): ?>
      - page <?= (int) $page ?> / <?= (int) $total_pages ?>
    <?php endif; ?>
  </p>

  <?php if (empty($users)): ?>
    <div class="empty-state">
      <p>Aucun utilisateur ne correspond aux filtres.</p>
    </div>
  <?php else: ?>
    <div class="admin-users-list">
      <?php foreach ($users as $user): ?>
        <?php $status = ($user['account_status'] ?? 'active') ?: 'active'; ?>
        <article class="admin-user-row">
          <div class="admin-user-main">
            <div class="conv-avatar"><?= e(strtoupper(substr($user['prenom'] ?: '?', 0, 1))) ?></div>
            <div>
              <h2 class="admin-user-name">
                <?= e($user['prenom'] ?: 'Sans prenom') ?>
                <?php if ((int) $user['is_admin'] === 1): ?>
                  <span class="admin-status-pill">Admin</span>
                <?php endif; ?>
              </h2>
              <p class="admin-report-meta">
                <?= e($user['email']) ?> - <?= e($user['ville'] ?: 'Ville non renseignee') ?>
              </p>
              <p class="admin-report-meta">
                Inscrit le <?= date('d/m/Y', strtotime($user['created_at'])) ?> -
                <?= (int) $user['nb_sorties'] ?> sortie<?= (int) $user['nb_sorties'] > 1 ? 's' : '' ?> -
                <?= (int) $user['nb_participations'] ?> participation<?= (int) $user['nb_participations'] > 1 ? 's' : '' ?> -
                <?= (int) $user['nb_reports_recus'] ?> signalement<?= (int) $user['nb_reports_recus'] > 1 ? 's' : '' ?>
              </p>
            </div>
          </div>

          <div class="admin-user-badges">
            <span class="admin-status-pill"><?= e(account_status_label($status)) ?></span>
            <span class="admin-status-pill <?= (int) $user['email_verifie'] === 1 ? 'is-success' : 'is-warning' ?>">
              <?= (int) $user['email_verifie'] === 1 ? 'Email verifie' : 'Email non verifie' ?>
            </span>
          </div>

          <div class="admin-user-actions">
            <a href="<?= e(app_url('app/pages/profil-public.php?id=' . (int) $user['id'])) ?>" class="cta-btn-small">Profil</a>

            <?php if ((int) $user['id'] !== $admin_id): ?>
              <form method="POST" action="../actions/admin-user.php" data-disable-on-submit="true">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="target_user_id" value="<?= (int) $user['id'] ?>">
                <input type="hidden" name="redirect" value="<?= e($redirect_courant) ?>">
                <select name="account_status" aria-label="Statut du compte">
                  <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actif</option>
                  <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspendu</option>
                  <option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>Banni</option>
                </select>
                <button type="submit" class="cta-btn-small">Appliquer</button>
              </form>

              <form method="POST" action="../actions/admin-user.php" data-disable-on-submit="true" onsubmit="return confirm('Supprimer definitivement ce compte et ses donnees ?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="target_user_id" value="<?= (int) $user['id'] ?>">
                <input type="hidden" name="redirect_after_delete" value="<?= e($redirect_courant) ?>">
                <button type="submit" class="cta-btn-small danger-link">Supprimer</button>
              </form>
            <?php else: ?>
              <span class="admin-report-meta">Compte admin courant</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
      <nav class="admin-pagination" aria-label="Pagination des utilisateurs">
        <?php if ($page > 1): ?>
          <a href="<?= e(admin_users_url(['page' => $page - 1])) ?>">Precedent</a>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?= e(admin_users_url(['page' => $page + 1])) ?>">Suivant</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
