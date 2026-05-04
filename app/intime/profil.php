<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/intime-access.php';
require_once __DIR__ . '/../services/intime-matching.php';

$acces = exiger_acces_intime($pdo);
$user_id = (int) $acces['user_id'];
$erreur = '';
$succes = '';
$intentions_disponibles = intime_intentions_disponibles();
$premieres_etapes = intime_premieres_etapes();

$stmt = $pdo->prepare("SELECT * FROM user_profiles_intime WHERE user_id = ?");
$stmt->execute([$user_id]);
$profil = $stmt->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $pseudo = trim($_POST['pseudo'] ?? '');
    $age_range = trim($_POST['age_range'] ?? '');
    $zone = trim($_POST['zone_approximative'] ?? '');
    $preferences = trim($_POST['rencontre_preferences'] ?? '');
    $intentions = intime_normaliser_liste($_POST['intentions'] ?? [], $intentions_disponibles);
    $dispos = trim($_POST['disponibilites'] ?? '');
    $limites = trim($_POST['limites_attentes'] ?? '');
    $bio = trim($_POST['bio_courte'] ?? '');
    $visibility = $_POST['photo_visibility'] ?? 'verified_users_only';
    $preferred_first_step = $_POST['preferred_first_step'] ?? 'social_first';

    if ($pseudo === '' || strlen($pseudo) > 80) {
        $erreur = 'Choisis un pseudo entre 1 et 80 caracteres.';
    } elseif (!in_array($visibility, ['verified_users_only', 'matched_only'], true)) {
        $erreur = 'Visibilite invalide.';
    } elseif (!array_key_exists($preferred_first_step, $premieres_etapes)) {
        $erreur = 'Rythme de contact invalide.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles_intime
                (user_id, pseudo, age_range, zone_approximative, rencontre_preferences, intentions, preferred_first_step, disponibilites, limites_attentes, bio_courte, photo_visibility)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pseudo = VALUES(pseudo),
                age_range = VALUES(age_range),
                zone_approximative = VALUES(zone_approximative),
                rencontre_preferences = VALUES(rencontre_preferences),
                intentions = VALUES(intentions),
                preferred_first_step = VALUES(preferred_first_step),
                disponibilites = VALUES(disponibilites),
                limites_attentes = VALUES(limites_attentes),
                bio_courte = VALUES(bio_courte),
                photo_visibility = VALUES(photo_visibility)
        ");
        $stmt->execute([
            $user_id,
            $pseudo,
            $age_range ?: null,
            $zone ?: null,
            $preferences ?: null,
            implode(',', $intentions) ?: null,
            $preferred_first_step,
            $dispos ?: null,
            $limites ?: null,
            $bio ?: null,
            $visibility,
        ]);
        journaliser_audit($pdo, $user_id, 'intime_profile_saved', 'user', $user_id);
        $succes = 'Profil intime enregistre.';

        $stmt = $pdo->prepare("SELECT * FROM user_profiles_intime WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $profil = $stmt->fetch() ?: [];
    }
}

$intentions_selectionnees = intime_liste_depuis_chaine($profil['intentions'] ?? '');
?>

<section class="intime-shell">
  <form method="POST" action="" class="intime-card intime-form" data-disable-on-submit="true">
    <?= csrf_field() ?>
    <div class="section-header">
      <div>
        <a href="personnes.php" class="back-link">Decouverte intime</a>
        <h1>Profil intime</h1>
      </div>
      <span class="verified-badge">18+ verifie</span>
    </div>

    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
    <?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

    <div class="form-group">
      <label for="pseudo">Pseudo</label>
      <input id="pseudo" name="pseudo" value="<?= e($profil['pseudo'] ?? '') ?>" required>
    </div>

    <div class="form-grid-2">
      <div class="form-group">
        <label for="age_range">Age affiche</label>
        <select id="age_range" name="age_range">
          <?php foreach (['', '18-24', '25-34', '35-44', '45-54', '55+'] as $option): ?>
            <option value="<?= e($option) ?>" <?= (($profil['age_range'] ?? '') === $option) ? 'selected' : '' ?>><?= $option ? e($option) : 'Ne pas afficher' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="zone_approximative">Zone approximative</label>
        <input id="zone_approximative" name="zone_approximative" value="<?= e($profil['zone_approximative'] ?? '') ?>" placeholder="Ville ou secteur, jamais adresse exacte">
      </div>
    </div>

    <div class="form-group">
      <label for="rencontre_preferences">Ce que tu recherches</label>
      <input id="rencontre_preferences" name="rencontre_preferences" value="<?= e($profil['rencontre_preferences'] ?? '') ?>" placeholder="Ex : rencontre calme, discussion avant tout, relation suivie, feeling...">
    </div>
    <div class="form-group">
      <label>Intentions claires</label>
      <div class="interest-picker">
        <?php foreach ($intentions_disponibles as $intention): ?>
          <label class="interest-chip">
            <input
              type="checkbox"
              name="intentions[]"
              value="<?= e($intention) ?>"
              <?= in_array($intention, $intentions_selectionnees, true) ? 'checked' : '' ?>
            />
            <span><?= e($intention) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-group">
      <label for="preferred_first_step">Premiere etape preferee</label>
      <select id="preferred_first_step" name="preferred_first_step">
        <?php foreach ($premieres_etapes as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= (($profil['preferred_first_step'] ?? 'social_first') === $value) ? 'selected' : '' ?>>
            <?= e($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="disponibilites">Disponibilites</label>
      <input id="disponibilites" name="disponibilites" value="<?= e($profil['disponibilites'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="limites_attentes">Limites et attentes</label>
      <textarea id="limites_attentes" name="limites_attentes" rows="4"><?= e($profil['limites_attentes'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label for="bio_courte">Bio courte</label>
      <textarea id="bio_courte" name="bio_courte" rows="3"><?= e($profil['bio_courte'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label for="photo_visibility">Visibilite photo</label>
      <select id="photo_visibility" name="photo_visibility">
        <option value="verified_users_only" <?= (($profil['photo_visibility'] ?? '') === 'verified_users_only') ? 'selected' : '' ?>>Utilisateurs verifies uniquement</option>
        <option value="matched_only" <?= (($profil['photo_visibility'] ?? '') === 'matched_only') ? 'selected' : '' ?>>Seulement apres accord mutuel</option>
      </select>
    </div>

    <button class="intime-btn" type="submit">Enregistrer</button>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
