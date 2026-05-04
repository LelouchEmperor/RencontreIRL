<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$acces = $user_id > 0 ? charger_acces_intime($pdo, $user_id) : [
    'can_access' => false,
    'age_verified' => false,
    'onboarding_completed' => false,
    'verification_status' => 'not_started',
    'reason' => 'Connecte-toi pour demander l acces.',
];
?>

<section class="intime-shell">
  <div class="intime-hero">
    <p class="mode-pill">Mode Intime 18+</p>
    <h1>Un espace plus prive, plus lent, plus encadre.</h1>
    <p>
      Cette partie du site est separee des sorties classiques. Elle demande une verification d age,
      un onboarding securite et des consentements explicites avant tout acces.
    </p>

    <div class="intime-actions">
      <?php if (empty($_SESSION['user_id'])): ?>
        <a href="<?= e(app_url('app/auth/connexion.php')) ?>" class="intime-btn">Se connecter</a>
      <?php elseif ($acces['can_access']): ?>
        <a href="<?= e(app_url('app/intime/personnes.php')) ?>" class="intime-btn">Decouvrir les personnes</a>
      <?php elseif (empty($acces['age_verified'])): ?>
        <a href="<?= e(app_url('app/intime/verification.php')) ?>" class="intime-btn">Demander la verification 18+</a>
      <?php elseif (empty($acces['onboarding_completed'])): ?>
        <a href="<?= e(app_url('app/intime/onboarding.php')) ?>" class="intime-btn">Terminer l onboarding</a>
      <?php endif; ?>
      <a href="<?= e(app_url('app/pages/sorties.php')) ?>" class="intime-btn-secondary">Retour au mode Sorties</a>
    </div>
  </div>

  <div class="intime-grid">
    <article class="intime-panel">
      <h2>Acces protege</h2>
      <p><?= e($acces['reason'] ?? 'Acces autorise.') ?></p>
      <ul class="intime-checklist">
        <li class="<?= !empty($acces['age_verified']) ? 'is-ok' : '' ?>">Verification 18+ cote serveur</li>
        <li class="<?= !empty($acces['onboarding_completed']) ? 'is-ok' : '' ?>">Regles et consentements acceptes</li>
        <li class="<?= !empty($acces['account_ok']) ? 'is-ok' : '' ?>">Compte actif et non limite</li>
      </ul>
    </article>

    <article class="intime-panel">
      <h2>Confidentialite par defaut</h2>
      <p>
        Les profils intimes sont separes du profil social. La localisation exacte n est pas affichee,
        et la decouverte est limitee aux utilisateurs verifies.
      </p>
    </article>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
