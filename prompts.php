<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$erreur  = '';
$succes  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("DELETE FROM reponses_prompts WHERE user_id = ?")->execute([$user_id]);

    $nb_enregistres = 0;
    for ($i = 0; $i < 3; $i++) {
        $prompt_id = (int) ($_POST['prompt_id'][$i] ?? 0);
        $reponse   = trim($_POST['reponse'][$i] ?? '');

        if ($prompt_id && !empty($reponse)) {
            $stmt = $pdo->prepare("INSERT INTO reponses_prompts (user_id, prompt_id, reponse) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE reponse = VALUES(reponse)");
            $stmt->execute([$user_id, $prompt_id, $reponse]);
            $nb_enregistres++;
        }
    }
    $succes = 'Tes réponses ont été sauvegardées.';
}

$stmt = $pdo->prepare("SELECT * FROM prompts ORDER BY id ASC");
$stmt->execute();
$tous_prompts = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT rp.*, p.question 
    FROM reponses_prompts rp
    JOIN prompts p ON rp.prompt_id = p.id
    WHERE rp.user_id = ?
    ORDER BY rp.id ASC
    LIMIT 3
");
$stmt->execute([$user_id]);
$mes_reponses = $stmt->fetchAll();

while (count($mes_reponses) < 3) {
    $mes_reponses[] = ['prompt_id' => 0, 'reponse' => '', 'question' => ''];
}
?>

<section class="section" style="max-width: 680px; margin: 0 auto;">
  <div class="section-header">
    <h1 class="section-title">Mes questions</h1>
    <a href="profil.php" class="cta-btn-outline">← Retour au profil</a>
  </div>

  <p style="font-size: 14px; color: #7a5060; margin-bottom: 2rem; line-height: 1.7;">
    Choisis 3 questions et réponds-y pour que les autres te connaissent mieux.
  </p>

  <?php if ($erreur): ?>
    <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
  <?php endif; ?>

  <?php if ($succes): ?>
    <div class="alert alert-success"><?= htmlspecialchars($succes) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <?php for ($i = 0; $i < 3; $i++): ?>
      <div class="profil-section" style="margin-bottom: 1.5rem;">
        <h3 class="profil-section-title">Question <?= $i + 1 ?></h3>
        <div class="form-group">
          <label>Choisis une question</label>
          <select name="prompt_id[]" style="background: #fdf4f5; border: 0.5px solid #d4909a; border-radius: 8px; padding: 12px 14px; font-size: 14px; color: #1a0810; outline: none; width: 100%; font-family: inherit;">
            <option value="0">-- Sélectionne une question --</option>
            <?php foreach ($tous_prompts as $prompt): ?>
              <option value="<?= $prompt['id'] ?>"
                <?= ($mes_reponses[$i]['prompt_id'] == $prompt['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($prompt['question']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Ta réponse</label>
          <textarea name="reponse[]" rows="3"
                    placeholder="Réponds en quelques mots..."
                    style="resize: none;"><?= htmlspecialchars($mes_reponses[$i]['reponse']) ?></textarea>
        </div>
      </div>
    <?php endfor; ?>

    <button type="submit" class="submit-btn">Sauvegarder mes réponses</button>
  </form>
</section>

<?php require_once 'includes/footer.php'; ?>