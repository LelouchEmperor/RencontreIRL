<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/geocode.php';
require_once __DIR__ . '/../../config/upload.php';
require_once __DIR__ . '/../services/interests.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$erreur  = '';
$succes  = '';
$interets_disponibles = interets_disponibles();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/deconnexion.php');
    exit;
}

$interets_user = interets_utilisateur($pdo, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $ville          = trim($_POST['ville'] ?? '');
    $bio            = trim($_POST['bio'] ?? '');
    $interets        = normaliser_interets($_POST['interets'] ?? []);
    $photo          = $user['photo'];

    if ($ville === '') {
        $erreur = 'La ville est obligatoire.';
    } else {
        if (!empty($_FILES['photo']['name'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ? AND moderation_status = 'pending'");
            $stmt->execute([$user_id]);
            $photo_en_attente = (int) $stmt->fetchColumn();

            if ($photo_en_attente > 0) {
                $erreur = 'Tu as deja une photo en attente de validation. Attends la reponse admin avant d\'en proposer une autre.';
            } else {
                $result = valider_et_upload_photo($_FILES['photo'], $user_id);

                if (!$result['ok']) {
                    $erreur = $result['erreur'];
                } else {
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(ordre), -1) + 1 FROM photos_profil WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $ordre_photo = (int) $stmt->fetchColumn();

                    $stmt = $pdo->prepare("INSERT INTO photos_profil (user_id, nom_fichier, ordre, moderation_status) VALUES (?, ?, ?, 'pending')");
                    $stmt->execute([$user_id, $result['nom'], $ordre_photo]);
                    $succes = 'Photo envoyee. Elle sera visible apres validation admin.';
                }
            }
        }

        if (!$erreur) {
            $coords = geocoder_ville($ville);
            $lat = $coords ? $coords['latitude'] : $user['latitude'];
            $lon = $coords ? $coords['longitude'] : $user['longitude'];

            $stmt = $pdo->prepare("
                UPDATE users
                SET ville = ?, bio = ?, latitude = ?, longitude = ?, photo = ?
                WHERE id = ?
            ");
            $stmt->execute([$ville, $bio, $lat, $lon, $photo, $user_id]);
            enregistrer_interets_utilisateur($pdo, $user_id, $interets);

            $interets_user = interets_utilisateur($pdo, $user_id);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $succes = $succes ?: 'Profil mis a jour avec succes.';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT s.*, COUNT(p.id) AS nb_participants
    FROM sorties s
    LEFT JOIN participations p ON s.id = p.sortie_id
    WHERE s.user_id = ?
    GROUP BY s.id
    ORDER BY s.date_sortie DESC
");
$stmt->execute([$user_id]);
$mes_sorties = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT s.*, u.prenom AS organisateur
    FROM participations p
    JOIN sorties s ON p.sortie_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE p.user_id = ?
    AND (u.account_status IS NULL OR u.account_status = '' OR u.account_status = 'active')
    AND (s.status IS NULL OR s.status = '' OR s.status = 'open')
    ORDER BY s.date_sortie DESC
");
$stmt->execute([$user_id]);
$sorties_rejointes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_profil WHERE user_id = ?");
$stmt->execute([$user_id]);
$nb_photos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT nom_fichier FROM photos_profil WHERE user_id = ? AND moderation_status = 'approved' ORDER BY ordre ASC LIMIT 1");
$stmt->execute([$user_id]);
$premiere_photo = $stmt->fetchColumn();
$photo_affichee = $user['photo'] ?: ($premiere_photo ?: null);

if ($photo_affichee) {
    $stmt = $pdo->prepare("SELECT id FROM photos_profil WHERE user_id = ? AND nom_fichier = ? AND moderation_status = 'approved'");
    $stmt->execute([$user_id, $photo_affichee]);

    if (!$stmt->fetch()) {
        $photo_affichee = $premiere_photo ?: null;
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reponses_prompts WHERE user_id = ?");
$stmt->execute([$user_id]);
$nb_reponses_prompts = (int) $stmt->fetchColumn();

$confiance_items = [
    'Email verifie' => !empty($user['email_verifie']),
    'Interets renseignes' => count($interets_user) >= 3,
    'Questions completees' => $nb_reponses_prompts >= 3,
    'Activite sur le site' => !empty($mes_sorties) || !empty($sorties_rejointes),
];
$confiance_score = (int) round((count(array_filter($confiance_items)) / count($confiance_items)) * 100);
?>

<section class="section">
  <div class="profil-wrap">

    <div class="profil-sidebar">
      <div onclick="document.getElementById('photo').click()" style="cursor: pointer; position: relative; display: inline-block; margin-bottom: 1rem;">
        <?php if (!empty($photo_affichee)): ?>
          <img
            src="/Site_rencontre/RencontreIRL/public/uploads/<?= e($photo_affichee) ?>"
            id="sidebarPreview"
            alt="Photo de profil"
            style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 0.5px solid #e8c8cc;"
          />
        <?php else: ?>
          <div class="profil-avatar" id="sidebarPreview">
            <?= strtoupper(substr($user['prenom'], 0, 1)) ?>
          </div>
        <?php endif; ?>

        <div style="position: absolute; bottom: 0; right: 0; width: 24px; height: 24px; background: #8b1a2a; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #fdf4f5;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
          </svg>
        </div>
      </div>

      <h2 class="profil-nom"><?= htmlspecialchars(trim($user['prenom'] . ' ' . ($user['nom'] ?? ''))) ?></h2>

      <p class="profil-ville">
        <?= htmlspecialchars($user['ville']) ?>
        <?php if (!empty($user['latitude'])): ?>
          <span style="color: #8b1a2a; font-size: 11px;">geolocalise</span>
        <?php else: ?>
          <span style="color: #c4a0a8; font-size: 11px;">non geolocalise</span>
        <?php endif; ?>
      </p>

      <?php if (!empty($user['date_naissance'])): ?>
        <p style="font-size: 13px; color: #a07080; margin-bottom: 0.5rem;">
          <?= (int) date('Y') - (int) date('Y', strtotime($user['date_naissance'])) ?> ans
        </p>
      <?php endif; ?>

      <?php if (!empty($user['bio'])): ?>
        <p class="profil-bio"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
      <?php endif; ?>

      <div class="profil-stats">
        <div class="stat">
          <span class="stat-nombre"><?= count($mes_sorties) ?></span>
          <span class="stat-label">Sorties creees</span>
        </div>
        <div class="stat">
          <span class="stat-nombre"><?= count($sorties_rejointes) ?></span>
          <span class="stat-label">Sorties rejointes</span>
        </div>
      </div>

      <div class="profile-completion-card">
        <div class="profile-completion-head">
          <strong>Confiance du compte</strong>
          <span><?= (int) $confiance_score ?>%</span>
        </div>
        <div class="profile-progress">
          <span style="width: <?= (int) $confiance_score ?>%;"></span>
        </div>
        <div class="profile-checklist">
          <?php foreach ($confiance_items as $label => $done): ?>
            <span class="profile-check <?= $done ? 'is-done' : '' ?>">
              <?= e($label) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="../actions/upload-photo.php" class="cta-btn-small" style="margin-top: 1rem;">
        Gerer mes photos
      </a>
      <a href="../services/prompts.php" class="cta-btn-small" style="margin-top: 0.5rem;">
        Completer mes questions
      </a>

      <?php
        $next_steps = [];
        if (count($interets_user) < 3) {
            $next_steps[] = ['label' => 'Choisis au moins 3 centres d\'interet', 'url' => '#interets'];
        }
        if ($nb_reponses_prompts < 3) {
            $next_steps[] = ['label' => 'Reponds a 3 questions de profil', 'url' => '../services/prompts.php'];
        }
        if (empty($mes_sorties) && empty($sorties_rejointes)) {
            $next_steps[] = ['label' => 'Rejoins ou propose une premiere sortie', 'url' => 'sorties.php'];
        }
      ?>
      <?php if (!empty($next_steps)): ?>
        <div class="onboarding-card">
          <strong>Prochaine etape</strong>
          <?php foreach (array_slice($next_steps, 0, 2) as $step): ?>
            <a href="<?= e($step['url']) ?>"><?= e($step['label']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="profil-content">
      <div class="profil-section">
        <h3 class="profil-section-title">Modifier mon profil</h3>

        <?php if ($erreur): ?>
          <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($succes): ?>
          <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
        <?php endif; ?>

        <form method="POST" data-disable-on-submit="true" action="" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="form-group">
            <label for="photo">Photo de profil</label>
            <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp" />
          </div>


          <div class="identity-readonly-card">
            <span>Identite legale</span>
            <strong>
              <?= !empty($user['nom']) ? e(trim($user['prenom'] . ' ' . $user['nom'])) : 'Non verifiee' ?>
            </strong>
            <?php if (!empty($user['date_naissance'])): ?>
              <small>Date de naissance verifiee : <?= e(date('d/m/Y', strtotime($user['date_naissance']))) ?></small>
            <?php endif; ?>
            <small>
              Le prenom, le nom et la date de naissance proviennent de la verification d'identite. Ils ne peuvent pas etre modifies depuis le profil.
            </small>
          </div>

          <div class="form-group">
            <label for="ville">Ville</label>
            <input
              type="text"
              id="ville"
              name="ville"
              value="<?= htmlspecialchars($user['ville']) ?>"
              required
            />
          </div>

          <div class="form-group">
            <label for="bio">Bio</label>
            <textarea
              id="bio"
              name="bio"
              rows="4"
              placeholder="Parle de toi en quelques mots..."
            ><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
          </div>

          <div class="form-group" id="interets">
            <label>Centres d'interet</label>
            <div class="interest-picker">
              <?php foreach ($interets_disponibles as $interet): ?>
                <label class="interest-chip">
                  <input
                    type="checkbox"
                    name="interets[]"
                    value="<?= e($interet) ?>"
                    <?= in_array($interet, $interets_user, true) ? 'checked' : '' ?>
                  />
                  <span><?= e($interet) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <button type="submit" class="submit-btn">Sauvegarder</button>

          <p class="auth-link" style="margin-top: 1rem;">
            <a href="../actions/modifier-mdp.php">Modifier mon mot de passe</a>
          </p>
        </form>
      </div>

      <?php if (!empty($mes_sorties)): ?>
        <div class="profil-section">
          <h3 class="profil-section-title">Mes sorties creees</h3>
          <div class="profil-sorties">
            <?php foreach ($mes_sorties as $sortie): ?>
              <?php $statut_sortie = sortie_statut_effectif($sortie); ?>
              <div class="profil-sortie-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                  <span class="sortie-activite"><?= htmlspecialchars($sortie['activite']) ?></span>
                  <span class="sortie-status sortie-status-<?= e($statut_sortie) ?>"><?= e(sortie_statut_label($statut_sortie)) ?></span>
                </div>

                <div class="sortie-titre" style="font-size: 15px;"><?= htmlspecialchars($sortie['titre']) ?></div>

                <div class="sortie-meta">
                  <?= htmlspecialchars($sortie['ville']) ?> -
                  <?= date('d/m/Y a H:i', strtotime($sortie['date_sortie'])) ?>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 0.75rem;">
                  <a href="../actions/modifier-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Modifier</a>
                  <?php if ($statut_sortie === 'open'): ?>
                    <a href="../actions/annuler-sortie.php?id=<?= (int) $sortie['id'] ?>" class="cta-btn-small">Annuler</a>
                  <?php endif; ?>
                  <a href="../actions/supprimer-sortie.php?id=<?= (int) $sortie['id'] ?>" style="font-size: 12px; color: #8b1a2a; border: 0.5px solid #d4909a; padding: 6px 14px; border-radius: 6px; text-decoration: none;">Supprimer</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($sorties_rejointes)): ?>
        <div class="profil-section">
          <h3 class="profil-section-title">Sorties que j'ai rejointes</h3>
          <div class="profil-sorties">
            <?php foreach ($sorties_rejointes as $sortie): ?>
              <div class="profil-sortie-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                  <span class="sortie-activite"><?= htmlspecialchars($sortie['activite']) ?></span>
                  <span style="font-size: 12px; color: #a07080;">par <?= htmlspecialchars($sortie['organisateur']) ?></span>
                </div>

                <div class="sortie-titre" style="font-size: 15px;"><?= htmlspecialchars($sortie['titre']) ?></div>

                <div class="sortie-meta">
                  <?= htmlspecialchars($sortie['ville']) ?> -
                  <?= date('d/m/Y a H:i', strtotime($sortie['date_sortie'])) ?>
                </div>

                <div style="margin-top: 0.75rem;">
                  <a href="../actions/quitter-sortie.php?id=<?= (int) $sortie['id'] ?>" style="font-size: 12px; color: #8b1a2a; border: 0.5px solid #d4909a; padding: 6px 14px; border-radius: 6px; text-decoration: none;">Quitter la sortie</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</section>

<script>
const photoInput = document.getElementById('photo');

if (photoInput) {
  photoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(ev) {
      const preview = document.getElementById('sidebarPreview');
      if (!preview) return;

      const img = document.createElement('img');
      img.id = 'sidebarPreview';
      img.src = ev.target.result;
      img.alt = 'Apercu photo';
      img.style.cssText = 'width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #8b1a2a;';

      preview.replaceWith(img);
    };
    reader.readAsDataURL(file);
  });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
