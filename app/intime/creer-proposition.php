<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';

$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim($_POST['title'] ?? '');
    $intention = trim($_POST['intention'] ?? '');
    $approximate_location = trim($_POST['approximate_location'] ?? '');
    $time_window = trim($_POST['time_window'] ?? '');
    $limits = trim($_POST['limits_expectations'] ?? '');
    $visibility = $_POST['visibility'] ?? 'verified_users_only';
    $expires_days = max(1, min(30, (int) ($_POST['expires_days'] ?? 7)));
    $consent = isset($_POST['consent_rules_accepted']);

    if ($title === '' || $intention === '' || $approximate_location === '' || $time_window === '') {
        $erreur = 'Tous les champs principaux sont obligatoires.';
    } elseif (!$consent) {
        $erreur = 'Tu dois confirmer les regles de consentement.';
    } elseif (!in_array($visibility, ['verified_users_only', 'matched_only'], true)) {
        $erreur = 'Visibilite invalide.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO proposals_intime
                (creator_id, title, intention, approximate_location, time_window, visibility, limits_expectations, consent_rules_accepted, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL $expires_days DAY))
        ");
        $stmt->execute([$user_id, $title, $intention, $approximate_location, $time_window, $visibility, $limits ?: null]);
        journaliser_audit($pdo, $user_id, 'intime_proposal_created', 'proposal_intime', (int) $pdo->lastInsertId());
        header('Location: ' . app_url('app/intime/propositions.php'));
        exit;
    }
}
?>

<section class="intime-shell">
  <form method="POST" action="" class="intime-card intime-form" data-disable-on-submit="true">
    <?= csrf_field() ?>
    <a href="propositions.php" class="back-link">Retour</a>
    <h1>Creer une proposition intime</h1>
    <p>Reste volontairement vague sur le lieu. Les details precis ne doivent jamais etre publics.</p>

    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>

    <div class="form-group"><label for="title">Titre</label><input id="title" name="title" required maxlength="120"></div>
    <div class="form-group"><label for="intention">Intention</label><input id="intention" name="intention" required maxlength="255"></div>
    <div class="form-group"><label for="approximate_location">Lieu approximatif</label><input id="approximate_location" name="approximate_location" required placeholder="Ville, quartier large, secteur"></div>
    <div class="form-group"><label for="time_window">Creneau</label><input id="time_window" name="time_window" required placeholder="Ex : vendredi soir, ce week-end"></div>
    <div class="form-group"><label for="limits_expectations">Limites et attentes</label><textarea id="limits_expectations" name="limits_expectations" rows="4"></textarea></div>
    <div class="form-grid-2">
      <div class="form-group">
        <label for="visibility">Visibilite</label>
        <select id="visibility" name="visibility">
          <option value="verified_users_only">Utilisateurs verifies uniquement</option>
          <option value="matched_only">Apres accord mutuel</option>
        </select>
      </div>
      <div class="form-group">
        <label for="expires_days">Expire dans</label>
        <select id="expires_days" name="expires_days">
          <option value="3">3 jours</option>
          <option value="7" selected>7 jours</option>
          <option value="14">14 jours</option>
          <option value="30">30 jours</option>
        </select>
      </div>
    </div>
    <label class="consent-row"><input type="checkbox" name="consent_rules_accepted" required> Je confirme que cette proposition respecte les regles de consentement.</label>
    <button type="submit" class="intime-btn">Publier</button>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
