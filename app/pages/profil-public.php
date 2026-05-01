<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/user-blocks.php';

$profil_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$viewer_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$viewer_is_admin = false;

if ($viewer_id > 0) {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$viewer_id]);
    $viewer_is_admin = (bool) $stmt->fetchColumn();
}

if (!$profil_id) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, prenom, ville, bio, photo, date_naissance, created_at, account_status, email_verifie
    FROM users
    WHERE id = ?
");
$stmt->execute([$profil_id]);
$profil = $stmt->fetch();

if ($profil && !$viewer_is_admin && !in_array(($profil['account_status'] ?? 'active') ?: 'active', ['', 'active'], true)) {
    $profil = false;
}

if (!$profil) {
    http_response_code(404);
}

$photos = [];
$reponses = [];
$sorties = [];
$avis_recents = [];
$stats_profil = [
    'sorties_creees' => 0,
    'sorties_rejointes' => 0,
    'likes_recus' => 0,
    'avis_recus' => 0,
    'note_moyenne' => null,
];
$confiance_items = [];
$confiance_score = 0;
$bloque_par_moi = false;

if ($profil) {
    if ($viewer_id > 0 && $viewer_id !== (int) $profil['id']) {
        $bloque_par_moi = utilisateur_bloque_par_moi($pdo, $viewer_id, (int) $profil['id']);
    }

    $stmt = $pdo->prepare("SELECT nom_fichier FROM photos_profil WHERE user_id = ? ORDER BY ordre ASC");
    $stmt->execute([$profil_id]);
    $photos = $stmt->fetchAll();
    $photo_affichee = $profil['photo'] ?: ($photos[0]['nom_fichier'] ?? null);

    $stmt = $pdo->prepare("
        SELECT p.question, rp.reponse
        FROM reponses_prompts rp
        JOIN prompts p ON p.id = rp.prompt_id
        WHERE rp.user_id = ?
        ORDER BY rp.id ASC
        LIMIT 3
    ");
    $stmt->execute([$profil_id]);
    $reponses = $stmt->fetchAll();

    if (in_array(($profil['account_status'] ?? 'active') ?: 'active', ['', 'active'], true)) {
        $stmt = $pdo->prepare("
            SELECT id, titre, activite, ville, date_sortie, places_restantes
            FROM sorties
            WHERE user_id = ?
            AND date_sortie > NOW()
            AND (status IS NULL OR status = '' OR status = 'open')
            ORDER BY date_sortie ASC
            LIMIT 6
        ");
        $stmt->execute([$profil_id]);
        $sorties = $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sorties WHERE user_id = ?");
    $stmt->execute([$profil_id]);
    $stats_profil['sorties_creees'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM participations WHERE user_id = ?");
    $stmt->execute([$profil_id]);
    $stats_profil['sorties_rejointes'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM likes_sorties l
        JOIN sorties s ON s.id = l.sortie_id
        WHERE s.user_id = ?
    ");
    $stmt->execute([$profil_id]);
    $stats_profil['likes_recus'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, AVG(note) AS moyenne FROM sortie_reviews WHERE reviewed_id = ?");
    $stmt->execute([$profil_id]);
    $avis_stats = $stmt->fetch();
    $stats_profil['avis_recus'] = (int) ($avis_stats['total'] ?? 0);
    $stats_profil['note_moyenne'] = $avis_stats['moyenne'] !== null ? round((float) $avis_stats['moyenne'], 1) : null;

    $stmt = $pdo->prepare("
        SELECT r.note, r.commentaire, r.created_at, reviewer.prenom AS reviewer_prenom, s.titre AS sortie_titre
        FROM sortie_reviews r
        JOIN users reviewer ON reviewer.id = r.reviewer_id
        JOIN sorties s ON s.id = r.sortie_id
        WHERE r.reviewed_id = ?
        ORDER BY r.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$profil_id]);
    $avis_recents = $stmt->fetchAll();

    $confiance_items = [
        'Email verifie' => !empty($profil['email_verifie']),
        'Questions completees' => count($reponses) >= 3,
        'Activite sur le site' => $stats_profil['sorties_creees'] > 0 || $stats_profil['sorties_rejointes'] > 0,
        'Avis apres sortie' => $stats_profil['avis_recus'] > 0,
    ];
    $confiance_score = (int) round((count(array_filter($confiance_items)) / count($confiance_items)) * 100);
}
?>

<section class="section">
  <?php if (!$profil): ?>
    <div class="empty-state">
      <p>Ce profil est introuvable.</p>
      <a href="sorties.php" class="cta-btn" style="margin-top: 1rem;">Retour aux sorties</a>
    </div>
  <?php else: ?>
    <div class="public-profile">
      <aside class="profil-sidebar">
        <?php if (!empty($photo_affichee)): ?>
          <img src="/Site_rencontre/RencontreIRL/public/uploads/<?= e($photo_affichee) ?>"
               alt="Photo de profil"
               style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 0.5px solid #e8c8cc;" />
        <?php else: ?>
          <div class="profil-avatar"><?= e(strtoupper(substr($profil['prenom'], 0, 1))) ?></div>
        <?php endif; ?>

        <h1 class="profil-nom"><?= e($profil['prenom']) ?></h1>
        <p class="profil-ville"><?= e($profil['ville']) ?></p>

        <?php if (!empty($profil['date_naissance'])): ?>
          <p class="sortie-meta"><?= (int) date('Y') - (int) date('Y', strtotime($profil['date_naissance'])) ?> ans</p>
        <?php endif; ?>

        <div class="trust-badges">
          <span class="trust-badge <?= !empty($profil['email_verifie']) ? 'is-success' : 'is-warning' ?>">
            <?= !empty($profil['email_verifie']) ? 'Email verifie' : 'Email non verifie' ?>
          </span>
          <span class="trust-badge <?= $confiance_score >= 67 ? 'is-success' : 'is-warning' ?>">
            Confiance <?= (int) $confiance_score ?>%
          </span>
          <?php if (count($reponses) >= 3): ?>
            <span class="trust-badge is-success">Questions completees</span>
          <?php endif; ?>
          <?php if ($stats_profil['sorties_creees'] > 0): ?>
            <span class="trust-badge is-muted">Organisateur actif</span>
          <?php elseif ($stats_profil['sorties_rejointes'] > 0): ?>
            <span class="trust-badge is-muted">Participant actif</span>
          <?php endif; ?>
          <?php if ($stats_profil['avis_recus'] > 0): ?>
            <span class="trust-badge is-success">Note <?= e((string) $stats_profil['note_moyenne']) ?>/5</span>
          <?php endif; ?>
          <span class="trust-badge is-muted">
            Membre depuis <?= date('m/Y', strtotime($profil['created_at'])) ?>
          </span>
        </div>

        <div class="profil-stats profil-stats-public">
          <div class="stat">
            <span class="stat-nombre"><?= (int) $stats_profil['sorties_creees'] ?></span>
            <span class="stat-label">Sorties creees</span>
          </div>
          <div class="stat">
            <span class="stat-nombre"><?= (int) $stats_profil['sorties_rejointes'] ?></span>
            <span class="stat-label">Sorties rejointes</span>
          </div>
          <div class="stat">
            <span class="stat-nombre"><?= (int) $stats_profil['likes_recus'] ?></span>
            <span class="stat-label">Likes recus</span>
          </div>
          <div class="stat">
            <span class="stat-nombre"><?= $stats_profil['note_moyenne'] !== null ? e((string) $stats_profil['note_moyenne']) : '-' ?></span>
            <span class="stat-label">Note moyenne</span>
          </div>
        </div>

        <?php if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] !== (int) $profil['id']): ?>
          <a href="../actions/signaler.php?type=user&target=<?= (int) $profil['id'] ?>" class="back-link" style="margin-top: 1rem;">Signaler ce profil</a>

          <form method="POST" action="../actions/block-user.php" data-disable-on-submit="true" class="block-user-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $bloque_par_moi ? 'unblock' : 'block' ?>">
            <input type="hidden" name="target_user_id" value="<?= (int) $profil['id'] ?>">
            <input type="hidden" name="redirect" value="<?= e('app/pages/profil-public.php?id=' . (int) $profil['id']) ?>">
            <button type="submit" class="cta-btn-small <?= $bloque_par_moi ? '' : 'danger-link' ?>">
              <?= $bloque_par_moi ? 'Debloquer ce profil' : 'Bloquer ce profil' ?>
            </button>
          </form>
        <?php endif; ?>

        <?php if ($viewer_is_admin && $viewer_id !== (int) $profil['id']): ?>
          <div class="admin-profile-actions">
            <span class="admin-status-pill">Compte : <?= e(account_status_label($profil['account_status'] ?? 'active')) ?></span>

            <form method="POST" action="../actions/admin-user.php" data-disable-on-submit="true">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="set_status">
              <input type="hidden" name="target_user_id" value="<?= (int) $profil['id'] ?>">
              <input type="hidden" name="redirect" value="<?= e('app/pages/profil-public.php?id=' . (int) $profil['id']) ?>">
              <div class="form-group">
                <label for="admin_status_<?= (int) $profil['id'] ?>">Moderation</label>
                <select name="account_status" id="admin_status_<?= (int) $profil['id'] ?>">
                  <option value="active" <?= (($profil['account_status'] ?? 'active') === 'active' || empty($profil['account_status'])) ? 'selected' : '' ?>>Actif</option>
                  <option value="suspended" <?= ($profil['account_status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspendu</option>
                  <option value="banned" <?= ($profil['account_status'] ?? '') === 'banned' ? 'selected' : '' ?>>Banni</option>
                </select>
              </div>
              <button type="submit" class="cta-btn-small">Mettre a jour</button>
            </form>

            <form method="POST" action="../actions/admin-user.php" data-disable-on-submit="true" onsubmit="return confirm('Supprimer definitivement ce compte et ses sorties ?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="target_user_id" value="<?= (int) $profil['id'] ?>">
              <button type="submit" class="cta-btn-small danger-link">Supprimer le compte</button>
            </form>
          </div>
        <?php endif; ?>
      </aside>

      <div class="profil-content">
        <?php if ($bloque_par_moi): ?>
          <section class="profil-section">
            <h2 class="profil-section-title">Profil bloque</h2>
            <p class="sortie-meta">Tu as bloque ce profil. La messagerie avec cette personne est desactivee.</p>
          </section>
        <?php endif; ?>

        <section class="profil-section">
          <h2 class="profil-section-title">A propos</h2>
          <?php if (!empty($profil['bio'])): ?>
            <p class="profil-bio" style="text-align: left; margin-bottom: 0;"><?= nl2br(e($profil['bio'])) ?></p>
          <?php else: ?>
            <p class="sortie-meta">Aucune bio ajoutee pour le moment.</p>
          <?php endif; ?>
        </section>

        <section class="profil-section">
          <h2 class="profil-section-title">Signaux de confiance</h2>
          <div class="profile-checklist">
            <?php foreach ($confiance_items as $label => $done): ?>
              <span class="profile-check <?= $done ? 'is-done' : '' ?>">
                <?= e($label) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </section>

        <?php if (!empty($photos)): ?>
          <section class="profil-section">
            <h2 class="profil-section-title">Photos</h2>
            <div class="photo-grid">
              <?php foreach ($photos as $photo): ?>
                <img src="/Site_rencontre/RencontreIRL/public/uploads/<?= e($photo['nom_fichier']) ?>" alt="Photo de profil" />
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if (!empty($reponses)): ?>
          <section class="profil-section">
            <h2 class="profil-section-title">Questions</h2>
            <div class="prompt-list">
              <?php foreach ($reponses as $reponse): ?>
                <div class="prompt-item">
                  <strong><?= e($reponse['question']) ?></strong>
                  <span><?= e($reponse['reponse']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if (!empty($avis_recents)): ?>
          <section class="profil-section">
            <h2 class="profil-section-title">Avis recus</h2>
            <div class="reviews-list">
              <?php foreach ($avis_recents as $avis): ?>
                <article class="review-card">
                  <div class="review-head">
                    <strong><?= e($avis['reviewer_prenom']) ?></strong>
                    <span><?= (int) $avis['note'] ?>/5</span>
                  </div>
                  <small><?= e($avis['sortie_titre']) ?> - <?= date('d/m/Y', strtotime($avis['created_at'])) ?></small>
                  <?php if (!empty($avis['commentaire'])): ?>
                    <p><?= nl2br(e($avis['commentaire'])) ?></p>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <section class="profil-section">
          <h2 class="profil-section-title">Sorties proposees</h2>
          <?php if (empty($sorties)): ?>
            <p class="sortie-meta">Aucune sortie a venir.</p>
          <?php else: ?>
            <div class="profil-sorties">
              <?php foreach ($sorties as $sortie): ?>
                <a href="sortie.php?id=<?= (int) $sortie['id'] ?>" class="profil-sortie-card" style="text-decoration: none;">
                  <span class="sortie-activite"><?= e($sortie['activite']) ?></span>
                  <div class="sortie-titre" style="margin-top: 0.75rem;"><?= e($sortie['titre']) ?></div>
                  <div class="sortie-meta"><?= e($sortie['ville']) ?> - <?= date('d/m/Y a H:i', strtotime($sortie['date_sortie'])) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
