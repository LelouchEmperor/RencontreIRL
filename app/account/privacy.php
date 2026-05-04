<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$succes = '';

$stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch() ?: [
    'show_city_only' => 1,
    'hide_from_search' => 0,
    'intimate_photo_visibility' => 'verified_users_only',
    'allow_intime_notifications' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $show_city_only = isset($_POST['show_city_only']) ? 1 : 0;
    $hide_from_search = isset($_POST['hide_from_search']) ? 1 : 0;
    $allow_notifications = isset($_POST['allow_intime_notifications']) ? 1 : 0;
    $photo_visibility = $_POST['intimate_photo_visibility'] ?? 'verified_users_only';

    if (!in_array($photo_visibility, ['verified_users_only', 'matched_only'], true)) {
        $photo_visibility = 'verified_users_only';
    }

    $stmt = $pdo->prepare("
        INSERT INTO privacy_settings
            (user_id, show_city_only, hide_from_search, intimate_photo_visibility, allow_intime_notifications)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            show_city_only = VALUES(show_city_only),
            hide_from_search = VALUES(hide_from_search),
            intimate_photo_visibility = VALUES(intimate_photo_visibility),
            allow_intime_notifications = VALUES(allow_intime_notifications)
    ");
    $stmt->execute([$user_id, $show_city_only, $hide_from_search, $photo_visibility, $allow_notifications]);
    $succes = 'Parametres de confidentialite mis a jour.';

    $stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch() ?: $settings;
}
?>

<section class="section">
  <form method="POST" action="" class="auth-card" data-disable-on-submit="true">
    <?= csrf_field() ?>
    <a href="<?= e(app_url('app/pages/parametres.php')) ?>" class="back-link">Retour aux parametres</a>
    <h1 class="auth-title">Confidentialite</h1>

    <?php if ($succes): ?><div class="alert alert-success"><?= e($succes) ?></div><?php endif; ?>

    <label class="consent-row"><input type="checkbox" name="show_city_only" <?= !empty($settings['show_city_only']) ? 'checked' : '' ?>> Afficher uniquement ma ville ou zone approximative.</label>
    <label class="consent-row"><input type="checkbox" name="hide_from_search" <?= !empty($settings['hide_from_search']) ? 'checked' : '' ?>> Mode invisible : masquer mon profil des decouvertes publiques et intimes.</label>
    <label class="consent-row"><input type="checkbox" name="allow_intime_notifications" <?= !empty($settings['allow_intime_notifications']) ? 'checked' : '' ?>> Autoriser les notifications du mode intime.</label>

    <div class="form-group">
      <label for="intimate_photo_visibility">Visibilite des photos en mode intime</label>
      <select id="intimate_photo_visibility" name="intimate_photo_visibility">
        <option value="verified_users_only" <?= ($settings['intimate_photo_visibility'] ?? '') === 'verified_users_only' ? 'selected' : '' ?>>Utilisateurs verifies uniquement</option>
        <option value="matched_only" <?= ($settings['intimate_photo_visibility'] ?? '') === 'matched_only' ? 'selected' : '' ?>>Seulement apres accord mutuel</option>
      </select>
    </div>

    <button class="submit-btn" type="submit">Enregistrer</button>
  </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
