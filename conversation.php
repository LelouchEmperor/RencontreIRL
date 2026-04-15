<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id    = $_SESSION['user_id'];
$sortie_id  = isset($_GET['sortie']) ? (int) $_GET['sortie'] : 0;
$other_id   = isset($_GET['user']) ? (int) $_GET['user'] : 0;

if (!$sortie_id || !$other_id) {
    header('Location: /Site_rencontre/RencontreIRL/messages.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$other_id]);
$interlocuteur = $stmt->fetch();

if (!$sortie || !$interlocuteur) {
    header('Location: /Site_rencontre/RencontreIRL/messages.php');
    exit;
}

$stmt = $pdo->prepare("SELECT p.id FROM participations p WHERE p.sortie_id = ? AND p.user_id = ?");
$stmt->execute([$sortie_id, $user_id]);
$est_participant = $stmt->fetch();

$est_organisateur = ($sortie['user_id'] === $user_id);

$stmt = $pdo->prepare("SELECT p.id FROM participations p WHERE p.sortie_id = ? AND p.user_id = ?");
$stmt->execute([$sortie_id, $other_id]);
$autre_est_participant = $stmt->fetch();

$autre_est_organisateur = ($sortie['user_id'] === $other_id);

if (!($est_participant || $est_organisateur) || !($autre_est_participant || $autre_est_organisateur)) {
    header('Location: /Site_rencontre/RencontreIRL/sorties.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['contenu']))) {
    csrf_verify();    
    $contenu = trim($_POST['contenu']);
    $stmt = $pdo->prepare("INSERT INTO messages (sortie_id, expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?, ?)");
    $stmt->execute([$sortie_id, $user_id, $other_id, $contenu]);
    header("Location: conversation.php?sortie=$sortie_id&user=$other_id");
    exit;
}

$stmt = $pdo->prepare("
    UPDATE messages SET lu = 1
    WHERE sortie_id = ? AND expediteur_id = ? AND destinataire_id = ?
");

$stmt->execute([$sortie_id, $other_id, $user_id]);

$stmt = $pdo->prepare("
    SELECT m.*, u.prenom
    FROM messages m
    JOIN users u ON m.expediteur_id = u.id
    WHERE m.sortie_id = ?
    AND (
        (m.expediteur_id = ? AND m.destinataire_id = ?)
        OR
        (m.expediteur_id = ? AND m.destinataire_id = ?)
    )
    ORDER BY m.created_at ASC
");
$stmt->execute([$sortie_id, $user_id, $other_id, $other_id, $user_id]);
$messages = $stmt->fetchAll();
?>

<section class="section">
  <div class="conv-topbar">
    <a href="messages.php" class="back-link">← Messages</a>
    <div class="conv-topbar-info">
      <div class="conv-avatar"><?= strtoupper(substr($interlocuteur['prenom'], 0, 1)) ?></div>
      <div>
        <div class="conv-nom"><?= htmlspecialchars($interlocuteur['prenom']) ?></div>
        <div class="conv-sortie"><?= htmlspecialchars($sortie['titre']) ?></div>
      </div>
    </div>
  </div>

  <div class="chat-box" id="chatBox">
    <?php if (empty($messages)): ?>
      <div class="chat-empty">Commence la conversation !</div>
    <?php else: ?>
      <?php foreach ($messages as $msg): ?>
        <div class="chat-msg <?= $msg['expediteur_id'] === $user_id ? 'moi' : 'lui' ?>">
          <div class="chat-bubble"><?= nl2br(htmlspecialchars($msg['contenu'])) ?></div>
          <div class="chat-time"><?= date('d/m à H:i', strtotime($msg['created_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<form method="POST" action="" class="chat-form">
  <?= csrf_field() ?>
  <textarea name="contenu" placeholder="Ton message..." rows="2" required></textarea>
    <button type="submit" class="submit-btn" style="width: auto; padding: 12px 24px;">Envoyer</button>
  </form>
</section>

<script>
  const chatBox = document.getElementById('chatBox');
  chatBox.scrollTop = chatBox.scrollHeight;
</script>

<?php require_once 'includes/footer.php'; ?>