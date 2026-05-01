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

$allowed_statuses = ['open', 'reviewed', 'resolved', 'dismissed'];
$allowed_account_statuses = ['active', 'suspended', 'banned'];
$filtre_status = $_GET['status'] ?? 'open';

if ($filtre_status !== 'all' && !in_array($filtre_status, $allowed_statuses, true)) {
    $filtre_status = 'open';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_report_status') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($report_id > 0 && in_array($status, $allowed_statuses, true)) {
            $stmt = $pdo->prepare("
                UPDATE reports
                SET status = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $admin_id, $report_id]);
        }
    }

    if ($action === 'update_user_status') {
        $user_id = (int) ($_POST['target_user_id'] ?? 0);
        $account_status = $_POST['account_status'] ?? '';

        if ($user_id > 0 && $user_id !== $admin_id && in_array($account_status, $allowed_account_statuses, true)) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET account_status = ?
                WHERE id = ?
            ");
            $stmt->execute([$account_status, $user_id]);
        }
    }

    $redirect = 'app/admin/reports.php';
    $redirect_params = [];
    if ($filtre_status !== 'open') {
        $redirect_params['status'] = $filtre_status;
    }
    if ((int) ($_GET['page'] ?? 1) > 1) {
        $redirect_params['page'] = (int) $_GET['page'];
    }
    if ($redirect_params) {
        $redirect .= '?' . http_build_query($redirect_params);
    }

    header('Location: ' . app_url($redirect));
    exit;
}

$stmt = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM reports
    GROUP BY status
");
$report_counts = array_fill_keys($allowed_statuses, 0);
foreach ($stmt->fetchAll() as $row) {
    if (isset($report_counts[$row['status']])) {
        $report_counts[$row['status']] = (int) $row['total'];
    }
}
$total_reports = array_sum($report_counts);

$where_status = '';
$params = [];
if ($filtre_status !== 'all') {
    $where_status = 'WHERE r.status = ?';
    $params[] = $filtre_status;
}

$par_page = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reports r $where_status");
$stmt->execute($params);
$total_filtre = (int) $stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_filtre / $par_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $par_page;
}

$stmt = $pdo->prepare("
    SELECT
        r.*,
        reporter.prenom AS reporter_prenom,
        target_user.id AS target_user_id,
        target_user.prenom AS target_user_prenom,
        target_user.ville AS target_user_ville,
        target_user.bio AS target_user_bio,
        target_user.created_at AS target_user_created_at,
        target_user.account_status AS target_user_account_status,
        target_sortie.titre AS target_sortie_titre,
        target_sortie.activite AS target_sortie_activite,
        target_sortie.ville AS target_sortie_ville,
        target_sortie.description AS target_sortie_description,
        target_sortie.date_sortie AS target_sortie_date,
        sortie_author.prenom AS target_sortie_author,
        message_sortie.titre AS target_message_sortie_titre,
        target_message.contenu AS target_message_contenu,
        target_message.created_at AS target_message_created_at,
        message_author.prenom AS target_message_author
    FROM reports r
    JOIN users reporter ON reporter.id = r.reporter_id
    LEFT JOIN users target_user ON r.target_type = 'user' AND target_user.id = r.target_id
    LEFT JOIN sorties target_sortie ON r.target_type = 'sortie' AND target_sortie.id = r.target_id
    LEFT JOIN users sortie_author ON target_sortie.user_id = sortie_author.id
    LEFT JOIN messages target_message ON r.target_type = 'message' AND target_message.id = r.target_id
    LEFT JOIN sorties message_sortie ON target_message.sortie_id = message_sortie.id
    LEFT JOIN users message_author ON target_message.expediteur_id = message_author.id
    $where_status
    ORDER BY
        CASE r.status
            WHEN 'open' THEN 1
            WHEN 'reviewed' THEN 2
            WHEN 'resolved' THEN 3
            WHEN 'dismissed' THEN 4
            ELSE 5
        END,
        r.created_at DESC
    LIMIT $par_page OFFSET $offset
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

function report_target_type_label(string $type): string
{
    return match ($type) {
        'user' => 'Profil',
        'sortie' => 'Sortie',
        'message' => 'Message',
        default => 'Inconnu',
    };
}

function report_reason_label(string $reason): string
{
    return match ($reason) {
        'faux_profil' => 'Faux profil',
        'contenu_inapproprie' => 'Contenu inapproprie',
        'harcelement' => 'Harcelement',
        'spam' => 'Spam',
        'autre' => 'Autre',
        default => 'Inconnu',
    };
}

function report_status_label(string $status): string
{
    return match ($status) {
        'open' => 'En attente',
        'reviewed' => 'Vu',
        'resolved' => 'Traite',
        'dismissed' => 'Rejete',
        default => 'Inconnu',
    };
}

function report_target_summary(array $report): string
{
    if ($report['target_type'] === 'user') {
        if (!empty($report['target_user_prenom'])) {
            $ville = !empty($report['target_user_ville']) ? ' - ' . $report['target_user_ville'] : '';
            return 'Profil de ' . $report['target_user_prenom'] . $ville;
        }

        return 'Profil introuvable';
    }

    if ($report['target_type'] === 'sortie') {
        if (!empty($report['target_sortie_titre'])) {
            $activite = !empty($report['target_sortie_activite']) ? ' (' . $report['target_sortie_activite'] . ')' : '';
            $ville = !empty($report['target_sortie_ville']) ? ' - ' . $report['target_sortie_ville'] : '';
            return $report['target_sortie_titre'] . $activite . $ville;
        }

        return 'Sortie introuvable';
    }

    if ($report['target_type'] === 'message') {
        if (!empty($report['target_message_contenu'])) {
            $auteur = !empty($report['target_message_author']) ? ' - par ' . $report['target_message_author'] : '';
            return texte_court($report['target_message_contenu'], 120) . $auteur;
        }

        return 'Message introuvable';
    }

    return 'Cible inconnue';
}

function report_target_link(array $report): ?string
{
    return match ($report['target_type']) {
        'user' => !empty($report['target_user_id']) ? app_url('app/pages/profil-public.php?id=' . (int) $report['target_user_id']) : null,
        'sortie' => !empty($report['target_id']) ? app_url('app/pages/sortie.php?id=' . (int) $report['target_id']) : null,
        default => null,
    };
}

function report_context_lines(array $report): array
{
    if ($report['target_type'] === 'user') {
        return array_filter([
            !empty($report['target_user_ville']) ? 'Ville : ' . $report['target_user_ville'] : null,
            !empty($report['target_user_created_at']) ? 'Compte cree le ' . date('d/m/Y', strtotime($report['target_user_created_at'])) : null,
            !empty($report['target_user_bio']) ? 'Bio : ' . texte_court($report['target_user_bio'], 220) : 'Bio vide',
            'Statut actuel : ' . account_status_label($report['target_user_account_status'] ?? null),
        ]);
    }

    if ($report['target_type'] === 'sortie') {
        return array_filter([
            !empty($report['target_sortie_author']) ? 'Organisateur : ' . $report['target_sortie_author'] : null,
            !empty($report['target_sortie_date']) ? 'Date : ' . date('d/m/Y a H:i', strtotime($report['target_sortie_date'])) : null,
            !empty($report['target_sortie_ville']) ? 'Ville : ' . $report['target_sortie_ville'] : null,
            !empty($report['target_sortie_description']) ? 'Description : ' . texte_court($report['target_sortie_description'], 260) : 'Description vide',
        ]);
    }

    if ($report['target_type'] === 'message') {
        return array_filter([
            !empty($report['target_message_author']) ? 'Auteur : ' . $report['target_message_author'] : null,
            !empty($report['target_message_sortie_titre']) ? 'Sortie : ' . $report['target_message_sortie_titre'] : null,
            !empty($report['target_message_created_at']) ? 'Envoye le ' . date('d/m/Y a H:i', strtotime($report['target_message_created_at'])) : null,
            !empty($report['target_message_contenu']) ? 'Message exact : ' . $report['target_message_contenu'] : 'Message introuvable',
        ]);
    }

    return [];
}
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Moderation - Signalements</h1>
  </div>

  <div class="admin-report-toolbar">
    <a href="<?= e(app_url('app/admin/reports.php?status=all')) ?>"
       class="admin-filter-link <?= $filtre_status === 'all' ? 'is-active' : '' ?>">
      Tous <span><?= (int) $total_reports ?></span>
    </a>
    <a href="<?= e(app_url('app/admin/reports.php?status=open')) ?>"
       class="admin-filter-link <?= $filtre_status === 'open' ? 'is-active' : '' ?>">
      En attente <span><?= (int) $report_counts['open'] ?></span>
    </a>
    <a href="<?= e(app_url('app/admin/reports.php?status=reviewed')) ?>"
       class="admin-filter-link <?= $filtre_status === 'reviewed' ? 'is-active' : '' ?>">
      Vu <span><?= (int) $report_counts['reviewed'] ?></span>
    </a>
    <a href="<?= e(app_url('app/admin/reports.php?status=resolved')) ?>"
       class="admin-filter-link <?= $filtre_status === 'resolved' ? 'is-active' : '' ?>">
      Traite <span><?= (int) $report_counts['resolved'] ?></span>
    </a>
    <a href="<?= e(app_url('app/admin/reports.php?status=dismissed')) ?>"
       class="admin-filter-link <?= $filtre_status === 'dismissed' ? 'is-active' : '' ?>">
      Rejete <span><?= (int) $report_counts['dismissed'] ?></span>
    </a>
  </div>

  <p class="admin-report-count">
    <?= (int) $total_filtre ?> signalement<?= $total_filtre > 1 ? 's' : '' ?> affiche<?= $total_filtre > 1 ? 's' : '' ?>
    <?php if ($total_pages > 1): ?>
      - page <?= (int) $page ?> / <?= (int) $total_pages ?>
    <?php endif; ?>
  </p>

  <?php if (empty($reports)): ?>
    <div class="empty-state">
      <p>Aucun signalement pour le moment.</p>
    </div>
  <?php else: ?>
    <div class="admin-report-list">
      <?php foreach ($reports as $report): ?>
        <?php
          $target_link = report_target_link($report);
          $context_lines = report_context_lines($report);
        ?>
        <article class="admin-report-card">
          <div class="admin-report-main">
            <div>
              <div class="admin-report-kicker">
                #<?= (int) $report['id'] ?> - <?= e(report_target_type_label($report['target_type'])) ?>
              </div>
              <h2 class="admin-report-title"><?= e(texte_court(report_target_summary($report), 90)) ?></h2>
              <p class="admin-report-meta">
                Signale par <?= e($report['reporter_prenom']) ?> le <?= date('d/m/Y a H:i', strtotime($report['created_at'])) ?>
              </p>
            </div>

            <span class="admin-status-pill"><?= e(report_status_label($report['status'])) ?></span>
          </div>

          <div class="admin-report-grid">
            <div>
              <p><strong>Motif :</strong> <?= e(report_reason_label($report['reason'])) ?></p>
              <p><strong>ID cible :</strong> <?= (int) $report['target_id'] ?></p>
              <?php if ($target_link): ?>
                <p><a class="back-link" href="<?= e($target_link) ?>">Voir la cible</a></p>
              <?php endif; ?>
            </div>

            <form method="POST" data-disable-on-submit="true" action="">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_report_status">
              <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

              <div class="form-group">
                <label for="status_<?= (int) $report['id'] ?>">Statut du signalement</label>
                <select name="status" id="status_<?= (int) $report['id'] ?>">
                  <option value="open" <?= $report['status'] === 'open' ? 'selected' : '' ?>>En attente</option>
                  <option value="reviewed" <?= $report['status'] === 'reviewed' ? 'selected' : '' ?>>Vu</option>
                  <option value="resolved" <?= $report['status'] === 'resolved' ? 'selected' : '' ?>>Traite</option>
                  <option value="dismissed" <?= $report['status'] === 'dismissed' ? 'selected' : '' ?>>Rejete</option>
                </select>
              </div>

              <button type="submit" class="submit-btn">Mettre a jour</button>
            </form>
          </div>

          <?php if ($report['target_type'] === 'user' && !empty($report['target_user_id'])): ?>
            <form method="POST" data-disable-on-submit="true" action="" class="admin-user-status-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_user_status">
              <input type="hidden" name="target_user_id" value="<?= (int) $report['target_user_id'] ?>">

              <div class="form-group">
                <label for="account_status_<?= (int) $report['id'] ?>">
                  Statut du compte : <?= e(account_status_label($report['target_user_account_status'])) ?>
                </label>
                <select name="account_status" id="account_status_<?= (int) $report['id'] ?>" <?= (int) $report['target_user_id'] === $admin_id ? 'disabled' : '' ?>>
                  <option value="active" <?= $report['target_user_account_status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                  <option value="suspended" <?= $report['target_user_account_status'] === 'suspended' ? 'selected' : '' ?>>Suspendu</option>
                  <option value="banned" <?= $report['target_user_account_status'] === 'banned' ? 'selected' : '' ?>>Banni</option>
                </select>
              </div>

              <button type="submit" class="cta-btn-small" <?= (int) $report['target_user_id'] === $admin_id ? 'disabled' : '' ?>>
                Modifier le compte
              </button>
            </form>
          <?php endif; ?>

          <?php if (!empty($report['details'])): ?>
            <details class="admin-report-details">
              <summary>Details donnes par le signalant</summary>
              <p><?= nl2br(e($report['details'])) ?></p>
            </details>
          <?php endif; ?>

          <?php if (!empty($context_lines)): ?>
            <details class="admin-report-details">
              <summary>Contexte de la cible</summary>
              <ul class="admin-context-list">
                <?php foreach ($context_lines as $line): ?>
                  <li><?= e($line) ?></li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endif; ?>

          <?php if (!empty($report['reviewed_at'])): ?>
            <p class="admin-report-reviewed">
              Derniere revue : <?= date('d/m/Y a H:i', strtotime($report['reviewed_at'])) ?>
            </p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
      <nav class="admin-pagination" aria-label="Pagination des signalements">
        <?php if ($page > 1): ?>
          <a href="<?= e(app_url('app/admin/reports.php?status=' . urlencode($filtre_status) . '&page=' . ($page - 1))) ?>">Precedent</a>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?= e(app_url('app/admin/reports.php?status=' . urlencode($filtre_status) . '&page=' . ($page + 1))) ?>">Suivant</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
