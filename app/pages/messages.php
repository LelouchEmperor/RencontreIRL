<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$recherche = trim($_GET['q'] ?? '');
$filtre = trim($_GET['filtre'] ?? 'tous');

if (!in_array($filtre, ['tous', 'non_lus'], true)) {
    $filtre = 'tous';
}

$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.sortie_id,
        m.expediteur_id,
        m.destinataire_id,
        m.contenu,
        m.lu,
        m.created_at,
        s.titre as sortie_titre,
        CASE
            WHEN m.expediteur_id = ? THEN m.destinataire_id
            ELSE m.expediteur_id
        END as interlocuteur_id,
        CASE
            WHEN m.expediteur_id = ? THEN ud.prenom
            ELSE ue.prenom
        END as interlocuteur_prenom
    FROM messages m
    JOIN sorties s ON m.sortie_id = s.id
    JOIN users organisateur ON organisateur.id = s.user_id
    JOIN users ue ON m.expediteur_id = ue.id
    JOIN users ud ON m.destinataire_id = ud.id
    LEFT JOIN user_blocks b1 ON b1.blocker_id = ? AND b1.blocked_id = CASE WHEN m.expediteur_id = ? THEN m.destinataire_id ELSE m.expediteur_id END
    LEFT JOIN user_blocks b2 ON b2.blocker_id = CASE WHEN m.expediteur_id = ? THEN m.destinataire_id ELSE m.expediteur_id END AND b2.blocked_id = ?
    WHERE (m.expediteur_id = ? OR m.destinataire_id = ?)
    AND (organisateur.account_status IS NULL OR organisateur.account_status = '' OR organisateur.account_status = 'active')
    AND b1.id IS NULL
    AND b2.id IS NULL
    ORDER BY m.created_at DESC, m.id DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$messages = $stmt->fetchAll();

$conversations = [];
foreach ($messages as $message) {
    $cle = $message['sortie_id'] . '-' . $message['interlocuteur_id'];

    if (!isset($conversations[$cle])) {
        $conversations[$cle] = [
            'sortie_id' => (int) $message['sortie_id'],
            'sortie_titre' => $message['sortie_titre'],
            'interlocuteur_id' => (int) $message['interlocuteur_id'],
            'interlocuteur_prenom' => $message['interlocuteur_prenom'],
            'dernier_message' => $message['created_at'],
            'dernier_contenu' => $message['contenu'],
            'dernier_expediteur_id' => (int) $message['expediteur_id'],
            'non_lus' => 0,
        ];
    }

    if ((int) $message['lu'] === 0 && (int) $message['destinataire_id'] === $user_id) {
        $conversations[$cle]['non_lus']++;
    }
}

$conversations = array_values($conversations);
$total_conversations = count($conversations);
$total_non_lus = array_sum(array_column($conversations, 'non_lus'));

if ($recherche !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($recherche, 'UTF-8') : strtolower($recherche);

    $conversations = array_filter($conversations, static function (array $conversation) use ($needle): bool {
        $haystack = $conversation['interlocuteur_prenom'] . ' ' . $conversation['sortie_titre'] . ' ' . $conversation['dernier_contenu'];
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);

        return str_contains($haystack, $needle);
    });
}

if ($filtre === 'non_lus') {
    $conversations = array_filter($conversations, static fn (array $conversation): bool => $conversation['non_lus'] > 0);
}

$conversations = array_values($conversations);

function apercu_message(string $contenu): string
{
    $contenu = trim((string) preg_replace('/\s+/', ' ', $contenu));

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($contenu, 0, 90, '...', 'UTF-8');
    }

    return strlen($contenu) > 90 ? substr($contenu, 0, 87) . '...' : $contenu;
}
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Mes messages</h1>
  </div>

  <div class="messages-toolbar">
    <div class="messages-summary">
      <span><?= (int) $total_conversations ?> conversation<?= $total_conversations > 1 ? 's' : '' ?></span>
      <span><?= (int) $total_non_lus ?> non lu<?= $total_non_lus > 1 ? 's' : '' ?></span>
    </div>

    <form method="GET" action="" class="messages-filters">
      <input type="search" name="q" placeholder="Rechercher..." value="<?= e($recherche) ?>" />
      <select name="filtre">
        <option value="tous" <?= $filtre === 'tous' ? 'selected' : '' ?>>Toutes</option>
        <option value="non_lus" <?= $filtre === 'non_lus' ? 'selected' : '' ?>>Non lues</option>
      </select>
      <button type="submit" class="cta-btn-small">Filtrer</button>
      <?php if ($recherche !== '' || $filtre !== 'tous'): ?>
        <a href="messages.php" class="cta-btn-small">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($conversations)): ?>
    <div class="empty-state">
      <p>Aucune conversation trouvee.</p>
      <a href="sorties.php" class="cta-btn" style="display: inline-block; margin-top: 1rem;">
        Découvrir les sorties
      </a>
    </div>
  <?php else: ?>
    <div class="conversations-list">
      <?php foreach ($conversations as $conv): ?>
        <a href="conversation.php?sortie=<?= $conv['sortie_id'] ?>&user=<?= $conv['interlocuteur_id'] ?>"
           class="conversation-item <?= $conv['non_lus'] > 0 ? 'non-lu' : '' ?>">
          <div class="conv-avatar">
            <?= strtoupper(substr($conv['interlocuteur_prenom'], 0, 1)) ?>
          </div>
          <div class="conv-info">
            <div class="conv-header">
              <span class="conv-nom"><?= htmlspecialchars($conv['interlocuteur_prenom']) ?></span>
              <span class="conv-date"><?= date('d/m à H:i', strtotime($conv['dernier_message'])) ?></span>
            </div>
            <div class="conv-sortie"><?= htmlspecialchars($conv['sortie_titre']) ?></div>
            <div class="conv-preview">
              <?= (int) $conv['dernier_expediteur_id'] === $user_id ? 'Vous : ' : '' ?><?= htmlspecialchars(apercu_message($conv['dernier_contenu'])) ?>
            </div>
          </div>
          <?php if ($conv['non_lus'] > 0): ?>
            <span class="conv-badge"><?= $conv['non_lus'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
