<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';

$user_id = exiger_connexion();
$acces = charger_acces_intime($pdo, $user_id);

if (empty($acces['age_verified'])) {
    header('Location: ' . app_url('app/intime/verification.php'));
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $acceptations = $_POST['consents'] ?? [];
    $requis = ['majorite', 'communaute', 'confidentialite', 'consentement', 'securite'];
    $manquants = array_diff($requis, $acceptations);

    if (!empty($manquants)) {
        $erreur = 'Tous les engagements sont obligatoires pour acceder au mode intime.';
    } else {
        foreach ($requis as $type) {
            enregistrer_consentement($pdo, $user_id, 'intime_' . $type, '2026-05-04', true);
        }

        $stmt = $pdo->prepare("SELECT prenom, ville FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch() ?: [];
        $pseudo_defaut = trim((string) ($user['prenom'] ?? 'Profil'));
        $zone_defaut = trim((string) ($user['ville'] ?? ''));

        $stmt = $pdo->prepare("
            INSERT INTO user_profiles_intime (user_id, pseudo, zone_approximative)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pseudo = pseudo,
                zone_approximative = COALESCE(zone_approximative, VALUES(zone_approximative))
        ");
        $stmt->execute([$user_id, $pseudo_defaut !== '' ? $pseudo_defaut : 'Profil', $zone_defaut ?: null]);

        $stmt = $pdo->prepare("
            INSERT INTO privacy_settings (user_id)
            VALUES (?)
            ON DUPLICATE KEY UPDATE user_id = user_id
        ");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("
            UPDATE users
            SET safety_onboarding_completed = 1,
                safety_onboarding_completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        journaliser_audit($pdo, $user_id, 'intime_onboarding_completed', 'user', $user_id);

        header('Location: ' . app_url('app/intime/personnes.php'));
        exit;
    }
}
?>

<section class="intime-shell">
  <form method="POST" action="" class="intime-card" data-disable-on-submit="true">
    <?= csrf_field() ?>
    <p class="mode-pill">Onboarding obligatoire</p>
    <h1>Regles de l espace intime</h1>
    <p>Une demande d interet n engage jamais une acceptation. Le consentement peut etre retire a tout moment.</p>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= e($erreur) ?></div>
    <?php endif; ?>

    <label class="consent-row"><input type="checkbox" name="consents[]" value="majorite"> Je confirme etre majeur et ne pas contourner la verification.</label>
    <label class="consent-row"><input type="checkbox" name="consents[]" value="communaute"> J accepte les regles de communaute et la moderation renforcee.</label>
    <label class="consent-row"><input type="checkbox" name="consents[]" value="confidentialite"> Je comprends les donnees necessaires au fonctionnement de cet espace.</label>
    <label class="consent-row"><input type="checkbox" name="consents[]" value="consentement"> Je respecte le consentement explicite, reversible et permanent.</label>
    <label class="consent-row"><input type="checkbox" name="consents[]" value="securite"> Je m engage a ne pas harceler, menacer, usurper ou partager du contenu non consenti.</label>

    <button type="submit" class="intime-btn">Valider et continuer</button>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
