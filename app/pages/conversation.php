<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/conversation-access.php';
require_once __DIR__ . '/../services/user-blocks.php';
require_once __DIR__ . '/../services/security-log.php';
require_once __DIR__ . '/../services/notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$sortie_id  = isset($_GET['sortie']) ? (int) $_GET['sortie'] : 0;
$other_id = isset($_GET['user']) ? (int) $_GET['user'] : 0;

if (!$sortie_id || !$other_id) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/messages.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$other_id]);
$interlocuteur = $stmt->fetch();

if (!$sortie || !$interlocuteur) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/messages.php');
    exit;
}

if (!conversation_autorisee($pdo, $sortie_id, $user_id, $other_id)) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$bloque_par_moi = utilisateur_bloque_par_moi($pdo, $user_id, $other_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['contenu']))) {
    csrf_verify();
    $contenu = trim($_POST['contenu']);

    if (strlen($contenu) > 2000) {
        http_response_code(422);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'message_trop_long']);
            exit;
        }

        die('Message trop long.');
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM messages
        WHERE expediteur_id = ?
        AND created_at >= (NOW() - INTERVAL 1 MINUTE)
    ");
    $stmt->execute([$user_id]);

    if ((int) $stmt->fetchColumn() >= 12) {
        journaliser_evenement_securite($pdo, 'message_rate_limited', $user_id, null, 'sortie=' . $sortie_id . ';destinataire=' . $other_id);
        http_response_code(429);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'trop_de_messages']);
            exit;
        }

        die('Trop de messages envoyes en peu de temps.');
    }

    if (preg_match('/\b(crypto|bitcoin|usdt|western union|transfert|argent|urgence|whatsapp|telegram|email|gmail|invest|placement)\b/i', $contenu)) {
        journaliser_evenement_securite($pdo, 'message_risk_keyword', $user_id, null, 'sortie=' . $sortie_id . ';destinataire=' . $other_id);
    }

    $stmt = $pdo->prepare("INSERT INTO messages (sortie_id, expediteur_id, destinataire_id, contenu) VALUES (?, ?, ?, ?)");
    $stmt->execute([$sortie_id, $user_id, $other_id, $contenu]);

    creer_notification(
        $pdo,
        $other_id,
        'message',
        ($_SESSION['prenom'] ?? 'Un utilisateur') . ' t a envoye un message pour "' . $sortie['titre'] . '".',
        'app/pages/conversation.php?sortie=' . (int) $sortie_id . '&user=' . (int) $user_id
    );

    // Si requête AJAX : répondre JSON, pas de redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // Fallback navigateur classique
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
        <div class="conversation-links">
          <a href="profil-public.php?id=<?= (int) $interlocuteur['id'] ?>">Voir le profil</a>
          <a href="sortie.php?id=<?= (int) $sortie['id'] ?>">Voir la sortie</a>
          <a href="../actions/signaler.php?type=user&target=<?= (int)$interlocuteur['id'] ?>">Signaler</a>
          <form method="POST" action="../actions/block-user.php" data-disable-on-submit="true">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $bloque_par_moi ? 'unblock' : 'block' ?>">
            <input type="hidden" name="target_user_id" value="<?= (int) $interlocuteur['id'] ?>">
            <input type="hidden" name="redirect" value="<?= e('app/pages/messages.php') ?>">
            <button type="submit" class="link-button-inline">
              <?= $bloque_par_moi ? 'Debloquer' : 'Bloquer' ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="safety-inline-banner">
    Securite : garde les premiers echanges ici. Refuse les demandes d'argent, crypto, liens suspects ou pression pour passer trop vite ailleurs.
  </div>

  <div class="chat-box" id="chatBox">
    <?php if (empty($messages)): ?>
      <div class="chat-empty">Commence la conversation !</div>
    <?php else: ?>
      <?php foreach ($messages as $msg): ?>
          <div class="chat-msg <?= (int)$msg['expediteur_id'] === $user_id ? 'moi' : 'lui' ?>" data-id="<?= $msg['id'] ?>">
          <div class="chat-bubble"><?= nl2br(htmlspecialchars($msg['contenu'])) ?></div>
          <?php if ((int)$msg['expediteur_id'] !== $user_id): ?>
  <div style="margin-top: 4px;">
    <a href="../actions/signaler.php?type=message&target=<?= (int)$msg['id'] ?>"
       style="font-size: 11px; color: #c4a0a8; text-decoration: none;">
      Signaler
    </a>
  </div>
<?php endif; ?>
          <div class="chat-time"><?= date('d/m à H:i', strtotime($msg['created_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<form method="POST" action="" class="chat-form">
  <?= csrf_field() ?>
  <textarea name="contenu" placeholder="Ton message..." rows="2" maxlength="2000" required></textarea>
    <button type="submit" class="submit-btn" style="width: auto; padding: 12px 24px;">Envoyer</button>
  </form>
  <p class="chat-error" id="chatError" style="display:none;"></p>
</section>

<script>
const chatBox    = document.getElementById('chatBox');
const sortieId   = <?= $sortie_id ?>;
const otherId    = <?= $other_id ?>;
const moiId      = <?= $user_id ?>;

// Scroll initial
chatBox.scrollTop = chatBox.scrollHeight;

// Récupère le dernier ID connu au chargement
function getLastId() {
    const msgs = chatBox.querySelectorAll('.chat-msg[data-id]');
    if (!msgs.length) return 0;
    return parseInt(msgs[msgs.length - 1].dataset.id) || 0;
}

function ajouterMessage(msg) {
    // Supprimer le placeholder "Commence la conversation !" si présent
    const empty = chatBox.querySelector('.chat-empty');
    if (empty) empty.remove();

    const div = document.createElement('div');
    div.className = 'chat-msg ' + (msg.moi ? 'moi' : 'lui');
    div.dataset.id = msg.id;
    div.innerHTML = `
        <div class="chat-bubble">${escapeHtml(msg.contenu).replace(/\n/g, '<br>')}</div>
        <div class="chat-time">${msg.heure}</div>
    `;
    chatBox.appendChild(div);
}

function escapeHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

let isPolling = false;

async function poll() {
    if (isPolling) return;
    isPolling = true;

    try {
        const lastId = getLastId();
        const url = `/Site_rencontre/RencontreIRL/app/api/messages-poll.php?sortie=${sortieId}&user=${otherId}&last_id=${lastId}`;
        const res  = await fetch(url, { credentials: 'same-origin' });

        if (!res.ok) return;

        const data = await res.json();

        if (data.error) return;

        if (data.length > 0) {
            const wasAtBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 60;

            data.forEach(msg => ajouterMessage(msg));

            // Auto-scroll uniquement si l'user était déjà en bas
            if (wasAtBottom) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        }
    } catch (e) {
        // Silencieux — pas de console.error en prod
    } finally {
        isPolling = false;
    }
}

// Lancer le polling toutes les 3 secondes
const pollInterval = setInterval(poll, 3000);

// Envoyer un message via AJAX (plus de rechargement de page)
const form     = document.querySelector('.chat-form');
const textarea = form.querySelector('textarea');
const chatError = document.getElementById('chatError');

textarea.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        form.requestSubmit();
    }
});

form.addEventListener('submit', async function(e) {
    e.preventDefault();

    const contenu = textarea.value.trim();
    if (!contenu) return;

    chatError.style.display = 'none';
    chatError.textContent = '';

    const formData = new FormData(form);

    try {
        const res = await fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        if (!res.ok || !data.ok) {
            chatError.textContent = data.error === 'trop_de_messages'
                ? 'Tu envoies trop de messages trop vite. Attends un instant.'
                : 'Ton message ne peut pas etre envoye pour le moment.';
            chatError.style.display = 'block';
            return;
        }

        textarea.value = '';
        textarea.style.height = 'auto';
        await poll();
        chatBox.scrollTop = chatBox.scrollHeight;

    } catch (e) {
        // fallback : soumettre normalement
        form.submit();
    }
});

// Stop polling si l'user quitte la page
window.addEventListener('beforeunload', () => clearInterval(pollInterval));
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
