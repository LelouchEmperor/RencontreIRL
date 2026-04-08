<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT DISTINCT
        m.sortie_id,
        s.titre as sortie_titre,
        CASE
            WHEN m.expediteur_id = ? THEN m.destinataire_id
            ELSE m.expediteur_id
        END as interlocuteur_id,
        CASE
            WHEN m.expediteur_id = ? THEN ud.prenom
            ELSE ue.prenom
        END as interlocuteur_prenom,
        MAX(m.created_at) as dernier_message,
        SUM(CASE WHEN m.lu = 0 AND m.destinataire_id = ? THEN 1 ELSE 0 END) as non_lus
    FROM messages m
    JOIN sorties s ON m.sortie_id = s.id
    JOIN users ue ON m.expediteur_id = ue.id
    JOIN users ud ON m.destinataire_id = ud.id
    WHERE m.expediteur_id = ? OR m.destinataire_id = ?
    GROUP BY m.sortie_id, interlocuteur_id
    ORDER BY dernier_message DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$conversations = $stmt->fetchAll();
?>

<section class="section">
  <div class="section-header">
    <h1 class="section-title">Mes messages</h1>
  </div>

  <?php if (empty($conversations)): ?>
    <div class="empty-state">
      <p>Aucune conversation pour le moment.</p>
      <a href="sorties.php" class="cta-btn" style="display: inline-block; margin-top: 1rem;">
        Rejoindre une sortie pour commencer
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
          </div>
          <?php if ($conv['non_lus'] > 0): ?>
            <span class="conv-badge"><?= $conv['non_lus'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>