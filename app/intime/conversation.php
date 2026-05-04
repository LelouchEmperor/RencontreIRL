<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';
require_once __DIR__ . '/../services/security-log.php';

$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];
$conversation_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT c.*, p1.pseudo AS pseudo_one, p2.pseudo AS pseudo_two
    FROM intime_conversations c
    LEFT JOIN user_profiles_intime p1 ON p1.user_id = c.user_one_id
    LEFT JOIN user_profiles_intime p2 ON p2.user_id = c.user_two_id
    WHERE c.id = ?
    AND (c.user_one_id = ? OR c.user_two_id = ?)
    LIMIT 1
");
$stmt->execute([$conversation_id, $user_id, $user_id]);
$conversation = $stmt->fetch();

if (!$conversation) {
    http_response_code(404);
    die('Conversation introuvable.');
}

$other_name = ((int) $conversation['user_one_id'] === $user_id)
    ? ($conversation['pseudo_two'] ?? 'Profil')
    : ($conversation['pseudo_one'] ?? 'Profil');
$other_id = ((int) $conversation['user_one_id'] === $user_id)
    ? (int) $conversation['user_two_id']
    : (int) $conversation['user_one_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $contenu = trim($_POST['contenu'] ?? '');

    if ($contenu !== '' && strlen($contenu) <= 2000) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM intime_messages
            WHERE expediteur_id = ?
            AND created_at >= (NOW() - INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$user_id]);

        if ((int) $stmt->fetchColumn() < 10) {
            if (preg_match('/\b(crypto|bitcoin|usdt|western union|transfert|argent|urgence|whatsapp|telegram|email|gmail|invest|placement)\b/i', $contenu)) {
                journaliser_evenement_securite($pdo, 'intime_message_risk_keyword', $user_id, null, 'conversation=' . $conversation_id);
            }

            $stmt = $pdo->prepare("
                INSERT INTO intime_messages (conversation_id, expediteur_id, contenu)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$conversation_id, $user_id, $contenu]);
        }
    }

    header('Location: conversation.php?id=' . $conversation_id);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE intime_messages
    SET lu = 1
    WHERE conversation_id = ?
    AND expediteur_id = ?
    AND lu = 0
");
$stmt->execute([$conversation_id, $other_id]);

$stmt = $pdo->prepare("
    SELECT *
    FROM intime_messages
    WHERE conversation_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll();
?>

<section class="intime-shell">
  <article class="intime-card">
    <div class="intime-profile-topbar">
      <a href="personnes.php" class="back-link">Retour aux personnes</a>
      <a href="<?= e(app_url('app/pages/sorties.php')) ?>" class="quick-exit-btn">Sortie rapide</a>
    </div>
    <h1>Conversation avec <?= e($other_name) ?></h1>
    <div class="safety-banner">
      Conversation ouverte apres interet mutuel. Ne partage pas de coordonnees personnelles trop vite, et signale toute demande d'argent ou pression.
    </div>
    <div class="conversation-starters">
      <strong>Pour lancer proprement</strong>
      <span>Qu'est-ce qui t'a donne envie de repondre ?</span>
      <span>Est-ce que tu preferes discuter avant une sortie publique ?</span>
      <span>Quelles limites veux-tu poser des le depart ?</span>
    </div>

    <div class="intime-message-list">
      <?php if (empty($messages)): ?>
        <p class="intime-note">Aucun message pour le moment.</p>
      <?php else: ?>
        <?php foreach ($messages as $message): ?>
          <div class="intime-message <?= (int) $message['expediteur_id'] === $user_id ? 'is-mine' : '' ?>">
            <?= nl2br(e($message['contenu'])) ?>
            <small><?= e(date('d/m/Y H:i', strtotime($message['created_at']))) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <form method="POST" action="" class="intime-chat-form" data-disable-on-submit="true">
      <?= csrf_field() ?>
      <textarea name="contenu" rows="3" maxlength="2000" placeholder="Message respectueux, clair, sans pression..." required></textarea>
      <button type="submit" class="intime-btn">Envoyer</button>
    </form>
  </article>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
