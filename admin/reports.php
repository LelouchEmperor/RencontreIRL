<?php
require_once '../includes/header.php';
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

$is_admin = !empty($currentUser['is_admin']);

if (!$is_admin) {
    http_response_code(403);
    die('Accès refusé.');
}

$allowed_statuses = ['open', 'reviewed', 'resolved', 'dismissed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_report_status') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        $status    = $_POST['status'] ?? '';

        if ($report_id > 0 && in_array($status, $allowed_statuses, true)) {
            $stmt = $pdo->prepare("
                UPDATE reports
                SET status = ?, reviewed_at = NOW(), reviewed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $_SESSION['user_id'], $report_id]);
        }
    }

    if ($action === 'update_user_status') {
        $user_id = (int) ($_POST['target_user_id'] ?? 0);
        $account_status = $_POST['account_status'] ?? '';

        $allowed_account_statuses = ['active', 'suspended', 'banned'];

        if ($user_id > 0 && in_array($account_status, $allowed_account_statuses, true)) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET account_status = ?
                WHERE id = ?
            ");
            $stmt->execute([$account_status, $user_id]);
        }
    }

    header('Location: /Site_rencontre/RencontreIRL/admin/reports.php');
    exit;
}

$stmt = $pdo->query("
    SELECT
        r.*,
        reporter.prenom AS reporter_prenom,

        target_user.prenom AS target_user_prenom,
        target_user.ville AS target_user_ville,
        target_user.account_status AS target_user_account_status,

        target_sortie.titre AS target_sortie_titre,
        target_sortie.activite AS target_sortie_activite,
        target_sortie.ville AS target_sortie_ville,

        target_message.contenu AS target_message_contenu,
        message_author.prenom AS target_message_author
    FROM reports r
    JOIN users reporter
        ON reporter.id = r.reporter_id

    LEFT JOIN users target_user
        ON r.target_type = 'user'
       AND target_user.id = r.target_id

    LEFT JOIN sorties target_sortie
        ON r.target_type = 'sortie'
       AND target_sortie.id = r.target_id

    LEFT JOIN messages target_message
        ON r.target_type = 'message'
       AND target_message.id = r.target_id

    LEFT JOIN users message_author
        ON target_message.expediteur_id = message_author.id

    ORDER BY
        CASE r.status
            WHEN 'open' THEN 1
            WHEN 'reviewed' THEN 2
            WHEN 'resolved' THEN 3
            WHEN 'dismissed' THEN 4
            ELSE 5
        END,
        r.created_at DESC
");
$reports = $stmt->fetchAll();

function report_target_type_label(string $type): string {
    return match ($type) {
        'user'    => 'Profil',
        'sortie'  => 'Sortie',
        'message' => 'Message',
        default   => 'Inconnu',
    };
}

function report_reason_label(string $reason): string {
    return match ($reason) {
        'faux_profil'         => 'Faux profil',
        'contenu_inapproprie' => 'Contenu inapproprié',
        'harcelement'         => 'Harcèlement',
        'spam'                => 'Spam',
        'autre'               => 'Autre',
        default               => 'Inconnu',
    };
}

function report_target_summary(array $report): string {
    if ($report['target_type'] === 'user') {
        if (!empty($report['target_user_prenom'])) {
            $ville = !empty($report['target_user_ville']) ? ' — ' . $report['target_user_ville'] : '';
            return 'Profil de ' . $report['target_user_prenom'] . $ville;
        }
        return 'Profil introuvable';
    }

    if ($report['target_type'] === 'sortie') {
        if (!empty($report['target_sortie_titre'])) {
            $activite = !empty($report['target_sortie_activite']) ? ' (' . $report['target_sortie_activite'] . ')' : '';
            $ville = !empty($report['target_sortie_ville']) ? ' — ' . $report['target_sortie_ville'] : '';
            return $report['target_sortie_titre'] . $activite . $ville;
        }
        return 'Sortie introuvable';
    }

    if ($report['target_type'] === 'message') {
        if (!empty($report['target_message_contenu'])) {
            $contenu = mb_strimwidth($report['target_message_contenu'], 0, 120, '...');
            $auteur = !empty($report['target_message_author']) ? ' — par ' . $report['target_message_author'] : '';
            return $contenu . $auteur;
        }
        return 'Message introuvable';
    }

    return 'Cible inconnue';
}

function report_status_label(string $status): string {
    return match ($status) {
        'open'      => 'En attente',
        'reviewed'  => 'Vu',
        'resolved'  => 'Traité',
        'dismissed' => 'Rejeté',
        default     => 'Inconnu',
    };
}

function account_status_label(?string $status): string {
    return match ($status) {
        'active'    => 'Actif',
        'suspended' => 'Suspendu',
        'banned'    => 'Banni',
        default     => 'Inconnu',
    };
}
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Modération — Signalements</h1>
  </div>

  <?php if (empty($reports)): ?>
    <div class="empty-state">
      <p>Aucun signalement pour le moment.</p>
    </div>
  <?php else: ?>
    <div style="display: grid; gap: 1rem;">
      <?php foreach ($reports as $report): ?>
        <div class="profil-section" style="margin-bottom: 0;">
          <div style="display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: flex-start;">
            <div style="flex: 1; min-width: 280px;">
              <p style="margin-bottom: 0.4rem;">
                <strong>#<?= (int) $report['id'] ?></strong>
              </p>

              <p style="margin-bottom: 0.4rem;">
                <strong>Signalé par :</strong>
                <?= htmlspecialchars($report['reporter_prenom']) ?>
              </p>

              <p style="margin-bottom: 0.4rem;">
                <strong>Type :</strong>
                <?= htmlspecialchars(report_target_type_label($report['target_type'])) ?>
              </p>

              <p style="margin-bottom: 0.4rem;">
                <strong>Cible :</strong>
                <?= htmlspecialchars(report_target_summary($report)) ?>
              </p>

              <p style="margin-bottom: 0.4rem;">
                <strong>ID cible :</strong>
                <?= (int) $report['target_id'] ?>
              </p>

              <p style="margin-bottom: 0.4rem;">
                <strong>Motif :</strong>
                <?= htmlspecialchars(report_reason_label($report['reason'])) ?>
              </p>

                <p style="margin-bottom: 0.4rem;">
                <strong>Statut actuel :</strong>
                <span style="
                    display: inline-block;
                    padding: 3px 10px;
                    border-radius: 999px;
                    font-size: 12px;
                    border: 1px solid #e4c9ce;
                    background: #faf3f4;
                    color: #8b1a2a;
                    cursor: default;
                    user-select: none;
                ">
                    <?= htmlspecialchars(report_status_label($report['status'])) ?>                </span>
                </p>

              <p style="margin-bottom: 0;">
                <strong>Date :</strong>
                <?= date('d/m/Y à H:i', strtotime($report['created_at'])) ?>
              </p>
            </div>

            <form method="POST" action="" style="width: 260px; max-width: 100%;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_report_status">
              <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

              <div class="form-group">
                <label for="status_<?= (int) $report['id'] ?>">Changer le statut</label>
                <select name="status" id="status_<?= (int) $report['id'] ?>">
                    <option value="open" <?= $report['status'] === 'open' ? 'selected' : '' ?>>En attente</option>
                    <option value="reviewed" <?= $report['status'] === 'reviewed' ? 'selected' : '' ?>>Vu</option>
                    <option value="resolved" <?= $report['status'] === 'resolved' ? 'selected' : '' ?>>Traité</option>
                    <option value="dismissed" <?= $report['status'] === 'dismissed' ? 'selected' : '' ?>>Rejeté</option>
                </select>
              </div>

              <button type="submit" class="submit-btn" style="width: 100%;">
                Mettre à jour
              </button>
            </form>
            <div style="display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: flex-start;">
          </div>

          <?php if (!empty($report['details'])): ?>
            <div style="
              margin-top: 1rem;
              background: #fff7f8;
              border: 1px solid #f0d6da;
              border-radius: 12px;
              padding: 12px;
              color: #6f4b56;
              line-height: 1.6;
            ">
              <strong style="display:block; margin-bottom: 0.5rem;">Détails</strong>
              <?= nl2br(htmlspecialchars($report['details'])) ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($report['reviewed_at'])): ?>
            <p style="margin-top: 0.75rem; font-size: 12px; color: #a07080;">
              Dernière revue : <?= date('d/m/Y à H:i', strtotime($report['reviewed_at'])) ?>
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once '../includes/footer.php'; ?>