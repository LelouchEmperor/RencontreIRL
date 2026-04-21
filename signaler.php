<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$reporter_id = (int) $_SESSION['user_id'];
$target_type = $_GET['type'] ?? '';
$target_id   = isset($_GET['target']) ? (int) $_GET['target'] : 0;

$allowed_types = ['user', 'sortie', 'message'];
$allowed_reasons = ['faux_profil', 'contenu_inapproprie', 'harcelement', 'spam', 'autre'];

if (!in_array($target_type, $allowed_types, true) || $target_id <= 0) {
    http_response_code(400);
    die('Signalement invalide.');
}

$target_label = '';
$target_data = null;

// Charger la cible pour affichage + validation
if ($target_type === 'user') {
    $stmt = $pdo->prepare("SELECT id, prenom, ville FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $target_data = $stmt->fetch();

    if (!$target_data) {
        die('Utilisateur introuvable.');
    }

    if ((int)$target_data['id'] === $reporter_id) {
        die('Tu ne peux pas te signaler toi-même.');
    }

    $target_label = "le profil de " . $target_data['prenom'];
}

if ($target_type === 'sortie') {
    $stmt = $pdo->prepare("
        SELECT s.id, s.titre, s.user_id, u.prenom
        FROM sorties s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
    ");
    $stmt->execute([$target_id]);
    $target_data = $stmt->fetch();

    if (!$target_data) {
        die('Sortie introuvable.');
    }

    $target_label = "la sortie « " . $target_data['titre'] . " »";
}

if ($target_type === 'message') {
    $stmt = $pdo->prepare("
        SELECT m.id, m.contenu, m.expediteur_id, u.prenom
        FROM messages m
        JOIN users u ON u.id = m.expediteur_id
        WHERE m.id = ?
    ");
    $stmt->execute([$target_id]);
    $target_data = $stmt->fetch();

    if (!$target_data) {
        die('Message introuvable.');
    }

    if ((int)$target_data['expediteur_id'] === $reporter_id) {
        die('Tu ne peux pas signaler ton propre message.');
    }

    $target_label = "un message de " . $target_data['prenom'];
}

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $reason  = $_POST['reason'] ?? '';
    $details = trim($_POST['details'] ?? '');

    if (!in_array($reason, $allowed_reasons, true)) {
        $erreur = 'Motif invalide.';
    } elseif ($reason === 'autre' && $details === '') {
        $erreur = 'Merci de préciser ton signalement.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM reports
            WHERE reporter_id = ? AND target_type = ? AND target_id = ?
            LIMIT 1
        ");
        $stmt->execute([$reporter_id, $target_type, $target_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $erreur = 'Tu as déjà signalé cet élément.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO reports (reporter_id, target_type, target_id, reason, details)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$reporter_id, $target_type, $target_id, $reason, $details ?: null]);

            $succes = 'Merci, ton signalement a bien été envoyé.';
        }
    }
}
?>

<section class="section">
  <div class="auth-card" style="max-width: 720px;">
    <h1 class="auth-title">Signaler un contenu</h1>

    <p style="margin-bottom: 1rem; color: #7a5060;">
      Tu signales <?= htmlspecialchars($target_label) ?>.
    </p>

    <?php if ($target_type === 'message' && $target_data): ?>
      <div style="background:#fff7f8; border:1px solid #f0d6da; border-radius:12px; padding:12px; margin-bottom:1rem;">
        <strong>Message concerné :</strong><br>
        <?= nl2br(htmlspecialchars($target_data['contenu'])) ?>
      </div>
    <?php endif; ?>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
      <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
      <p class="auth-link"><a href="javascript:history.back()">Retour</a></p>
    <?php else: ?>
      <form method="POST" action="">
  <?= csrf_field() ?>

  <div class="form-group">
    <label for="reason">Pourquoi tu signales ?</label>
    <select name="reason" id="reason" required>
      <option value="">Choisir un motif</option>
      <option value="faux_profil">Faux profil</option>
      <option value="contenu_inapproprie">Contenu inapproprié</option>
      <option value="harcelement">Harcèlement</option>
      <option value="spam">Spam</option>
      <option value="autre">Autre</option>
    </select>
  </div>

  <div class="form-group" id="detailsGroup" style="display: none;">
    <label for="details">Précise ton signalement</label>
    <textarea
      name="details"
      id="details"
      rows="5"
      placeholder="Explique ce que tu veux signaler..."
    ></textarea>
  </div>

  <button type="submit" class="submit-btn">Envoyer le signalement</button>
</form>
    <?php endif; ?>
  </div>
</section>

<script>
  const reasonSelect = document.getElementById('reason');
  const detailsGroup = document.getElementById('detailsGroup');
  const detailsField = document.getElementById('details');

  function toggleDetailsField() {
    const isAutre = reasonSelect.value === 'autre';

    detailsGroup.style.display = isAutre ? 'block' : 'none';
    detailsField.required = isAutre;

    if (!isAutre) {
      detailsField.value = '';
    }
  }

  reasonSelect.addEventListener('change', toggleDetailsField);

  // utile si la page se recharge avec une valeur déjà sélectionnée
  toggleDetailsField();
</script>

<?php require_once 'includes/footer.php'; ?>