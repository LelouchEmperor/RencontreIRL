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

if (!(bool) $stmt->fetchColumn()) {
    http_response_code(403);
    die('Acces refuse.');
}

$statuts_valides = ['pending', 'approved', 'rejected', 'all'];
$statut = isset($_GET['statut']) ? (string) $_GET['statut'] : 'pending';

if (!in_array($statut, $statuts_valides, true)) {
    $statut = 'pending';
}

$message = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $photo_id = (int) ($_POST['photo_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $raison = trim((string) ($_POST['raison'] ?? ''));

    $stmt = $pdo->prepare("
        SELECT p.*, u.photo AS photo_principale
        FROM photos_profil p
        JOIN users u ON u.id = p.user_id
        WHERE p.id = ?
    ");
    $stmt->execute([$photo_id]);
    $photo = $stmt->fetch();

    if (!$photo) {
        $erreur = 'Photo introuvable.';
    } elseif ($action === 'approve') {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE photos_profil
                SET moderation_status = 'approved',
                    moderation_reason = NULL,
                    moderated_at = NOW(),
                    moderated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$admin_id, $photo_id]);

            if (empty($photo['photo_principale'])) {
                $stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
                $stmt->execute([$photo['nom_fichier'], (int) $photo['user_id']]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, lien)
                VALUES (?, 'photo_approuvee', ?, ?)
            ");
            $stmt->execute([
                (int) $photo['user_id'],
                'Ta photo de profil a ete validee.',
                'app/actions/upload-photo.php',
            ]);

            $pdo->commit();
            $message = 'Photo validee.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreur = 'Impossible de valider cette photo.';
        }
    } elseif ($action === 'reject') {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                UPDATE photos_profil
                SET moderation_status = 'rejected',
                    moderation_reason = ?,
                    moderated_at = NOW(),
                    moderated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$raison !== '' ? $raison : null, $admin_id, $photo_id]);

            if ($photo['photo_principale'] === $photo['nom_fichier']) {
                $stmt = $pdo->prepare("
                    SELECT nom_fichier
                    FROM photos_profil
                    WHERE user_id = ?
                    AND id <> ?
                    AND moderation_status = 'approved'
                    ORDER BY ordre ASC
                    LIMIT 1
                ");
                $stmt->execute([(int) $photo['user_id'], $photo_id]);
                $nouvelle_photo = $stmt->fetchColumn() ?: null;

                $stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
                $stmt->execute([$nouvelle_photo, (int) $photo['user_id']]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, lien)
                VALUES (?, 'photo_refusee', ?, ?)
            ");
            $stmt->execute([
                (int) $photo['user_id'],
                'Une photo de profil a ete refusee' . ($raison !== '' ? ' : ' . texte_court($raison, 80) : '.'),
                'app/actions/upload-photo.php',
            ]);

            $pdo->commit();
            $message = 'Photo refusee.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreur = 'Impossible de refuser cette photo.';
        }
    }
}

$where = '';
$params = [];

if ($statut !== 'all') {
    $where = 'WHERE p.moderation_status = ?';
    $params[] = $statut;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.prenom, u.nom, u.email
    FROM photos_profil p
    JOIN users u ON u.id = p.user_id
    $where
    ORDER BY
        FIELD(p.moderation_status, 'pending', 'approved', 'rejected'),
        p.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$photos = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN moderation_status = 'pending' THEN 1 ELSE 0 END) AS pending_total,
        SUM(CASE WHEN moderation_status = 'approved' THEN 1 ELSE 0 END) AS approved_total,
        SUM(CASE WHEN moderation_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_total
    FROM photos_profil
");
$stats = $stmt->fetch();
?>

<section class="section">
  <div class="section-header">
    <div>
      <a href="index.php" class="back-link">Retour admin</a>
      <h1 class="section-title" style="margin-top: 0.75rem;">Moderation des photos</h1>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>

  <?php if ($erreur): ?>
    <div class="alert alert-error"><?= e($erreur) ?></div>
  <?php endif; ?>

  <div class="admin-stats-grid" style="margin-bottom: 1.5rem;">
    <div class="admin-stat-card"><strong><?= (int) ($stats['pending_total'] ?? 0) ?></strong><span>En attente</span></div>
    <div class="admin-stat-card"><strong><?= (int) ($stats['approved_total'] ?? 0) ?></strong><span>Validees</span></div>
    <div class="admin-stat-card"><strong><?= (int) ($stats['rejected_total'] ?? 0) ?></strong><span>Refusees</span></div>
  </div>

  <div class="admin-report-toolbar">
    <a href="photos.php?statut=pending" class="admin-filter-link <?= $statut === 'pending' ? 'is-active' : '' ?>">En attente <span><?= (int) ($stats['pending_total'] ?? 0) ?></span></a>
    <a href="photos.php?statut=approved" class="admin-filter-link <?= $statut === 'approved' ? 'is-active' : '' ?>">Validees <span><?= (int) ($stats['approved_total'] ?? 0) ?></span></a>
    <a href="photos.php?statut=rejected" class="admin-filter-link <?= $statut === 'rejected' ? 'is-active' : '' ?>">Refusees <span><?= (int) ($stats['rejected_total'] ?? 0) ?></span></a>
    <a href="photos.php?statut=all" class="admin-filter-link <?= $statut === 'all' ? 'is-active' : '' ?>">Toutes <span><?= (int) ($stats['total'] ?? 0) ?></span></a>
  </div>

  <?php if (empty($photos)): ?>
    <div class="empty-state">
      <p>Aucune photo pour ce filtre.</p>
    </div>
  <?php else: ?>
    <div class="admin-photo-grid">
      <?php foreach ($photos as $photo): ?>
        <?php $status = $photo['moderation_status'] ?? 'pending'; ?>
        <article class="admin-photo-card">
          <img src="/Site_rencontre/RencontreIRL/public/uploads/<?= e($photo['nom_fichier']) ?>" alt="Photo a moderer" />
          <div class="admin-photo-body">
            <span class="photo-status photo-status-<?= e($status) ?>">
              <?= $status === 'approved' ? 'Validee' : ($status === 'rejected' ? 'Refusee' : 'En attente') ?>
            </span>
            <h2 class="admin-user-name"><?= e(trim($photo['prenom'] . ' ' . ($photo['nom'] ?? ''))) ?></h2>
            <p class="admin-report-meta"><?= e($photo['email']) ?> - <?= date('d/m/Y H:i', strtotime($photo['created_at'])) ?></p>

            <?php if (!empty($photo['moderation_reason'])): ?>
              <p class="sortie-meta">Motif : <?= e($photo['moderation_reason']) ?></p>
            <?php endif; ?>

            <div class="admin-photo-actions">
              <a href="../pages/profil-public.php?id=<?= (int) $photo['user_id'] ?>" class="cta-btn-small">Voir profil</a>

              <?php if ($status !== 'approved'): ?>
                <form method="POST" data-disable-on-submit="true" action="">
                  <?= csrf_field() ?>
                  <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="cta-btn-small">Valider</button>
                </form>
              <?php endif; ?>

              <?php if ($status !== 'rejected'): ?>
                <form method="POST" data-disable-on-submit="true" action="">
                  <?= csrf_field() ?>
                  <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <input type="text" name="raison" placeholder="Motif optionnel" />
                  <button type="submit" class="cta-btn-small danger-link">Refuser</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
