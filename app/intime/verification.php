<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/intime.php';
require_once __DIR__ . '/../services/intime-access.php';
require_once __DIR__ . '/../services/age-verification-provider.php';

$user_id = exiger_connexion();
$status = statut_verification_age($pdo, $user_id);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (($_POST['action'] ?? '') === 'validate_local_test') {
        $result = valider_verification_age_test(
            $pdo,
            $user_id,
            $_POST['legal_first_name'] ?? null,
            $_POST['legal_last_name'] ?? null,
            $_POST['legal_birth_date'] ?? null
        );
        $message = $result['message'];

        if (!empty($result['ok'])) {
            journaliser_audit($pdo, $user_id, 'age_verification_local_test_validated', 'user', $user_id, $result['reference'] ?? null);
            if (!empty($_POST['legal_first_name'])) {
                $_SESSION['prenom'] = trim((string) $_POST['legal_first_name']);
            }
        }
    } else {
        $session = creer_session_verification_age($pdo, $user_id);
        journaliser_audit($pdo, $user_id, 'age_verification_session_created', 'user', $user_id, $session['reference']);
        $message = $session['message'];
    }

    $status = statut_verification_age($pdo, $user_id);
}
?>

<section class="intime-shell">
  <div class="intime-card">
    <a href="index.php" class="back-link">Retour</a>
    <h1>Verification 18+</h1>
    <p>
      Le site ne stocke pas de document d identite. Cette page prepare la session vers un prestataire
      externe qui devra retourner uniquement un statut de majorite.
    </p>

    <div class="safety-banner">
      Statut actuel : <strong><?= e((string) ($status['verification_status'] ?? 'not_started')) ?></strong>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="" data-disable-on-submit="true">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_session">
      <button type="submit" class="intime-btn">Creer une session de verification</button>
    </form>

    <?php if (intime_test_verification_active() && empty($status['age_verified'])): ?>
      <form method="POST" action="" data-disable-on-submit="true" style="margin-top: 0.75rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="validate_local_test">
        <div class="form-grid-2">
          <div class="form-group">
            <label for="legal_first_name">Prenom legal test</label>
            <input id="legal_first_name" name="legal_first_name" placeholder="Comme sur la piece d'identite">
          </div>
          <div class="form-group">
            <label for="legal_last_name">Nom legal test</label>
            <input id="legal_last_name" name="legal_last_name" placeholder="Comme sur la piece d'identite">
          </div>
          <div class="form-group">
            <label for="legal_birth_date">Date de naissance test</label>
            <input type="date" id="legal_birth_date" name="legal_birth_date">
          </div>
        </div>
        <button type="submit" class="intime-btn-secondary">Valider en mode test local</button>
      </form>
    <?php endif; ?>

    <p class="intime-note">
      Le bouton de validation locale sert uniquement aux tests XAMPP. En production, il faudra utiliser
      un prestataire KYC et desactiver ce mode.
    </p>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
