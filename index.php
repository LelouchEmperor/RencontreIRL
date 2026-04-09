<?php require_once 'includes/header.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/sorties.php');
    exit;
}
?>

<section class="index-hero">
  <div class="hero-left">
    <div class="index-tag">Pour ceux qui arrivent quelque part</div>
    <h1 class="index-title">
      Tu viens d'arriver.<br>
      Tu veux faire des choses.<br>
      <span class="index-accent">Mais pas seul.</span>
    </h1>
    <p class="index-sub">Pas de swipe. Juste des gens qui veulent faire la même chose que toi — dans ta nouvelle ville.</p>
    <div class="index-btns">
      <a href="sorties.php" class="cta-btn">Voir les sorties</a>
      <a href="auth/inscription.php" class="cta-btn-outline">Créer un compte</a>
    </div>
  </div>

  <div class="hero-map-wrap">
    <svg width="100%" viewBox="0 0 500 400" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="250" cy="335" rx="240" ry="55" fill="#e8d5cc"/>
      <ellipse cx="250" cy="335" rx="240" ry="55" fill="none" stroke="#d4b0a8" stroke-width="1"/>
      <path d="M30 343 Q150 310 250 317 Q355 323 470 337" fill="none" stroke="#c4a098" stroke-width="3" stroke-linecap="round"/>
      <path d="M180 375 Q220 325 250 317 Q278 309 285 350" fill="none" stroke="#c4a098" stroke-width="2" stroke-linecap="round"/>
      <rect x="80" y="283" width="28" height="44" rx="3" fill="#d4b8b0"/>
      <rect x="114" y="271" width="20" height="56" rx="3" fill="#c8aca4"/>
      <rect x="340" y="280" width="26" height="47" rx="3" fill="#d4b8b0"/>
      <rect x="372" y="270" width="20" height="57" rx="3" fill="#c8aca4"/>
      <rect x="218" y="287" width="52" height="38" rx="4" fill="#c4a098"/>
      <polygon points="218,287 244,267 270,287" fill="#b08878"/>
      <rect x="232" y="299" width="14" height="26" rx="2" fill="#8b5a52"/>
      <path d="M244 230 C244 230 224 250 224 265 C224 277 233 285 244 285 C255 285 264 277 264 265 C264 250 244 230 244 230Z" fill="#8b1a2a" opacity="0.25"/>

      <g id="persoL">
        <circle cx="100" cy="255" r="11" fill="none" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="100" y1="266" x2="100" y2="297" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="100" y1="277" x2="82" y2="289" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="100" y1="277" x2="118" y2="285" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="100" y1="297" x2="87" y2="317" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="100" y1="297" x2="113" y2="317" stroke="#8b1a2a" stroke-width="2.5"/>
      </g>

      <g id="persoR">
        <circle cx="400" cy="255" r="11" fill="none" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="400" y1="266" x2="400" y2="297" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="400" y1="277" x2="382" y2="289" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="400" y1="277" x2="418" y2="285" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="400" y1="297" x2="387" y2="317" stroke="#8b1a2a" stroke-width="2.5"/>
        <line x1="400" y1="297" x2="413" y2="317" stroke="#8b1a2a" stroke-width="2.5"/>
      </g>

      <g id="b1" opacity="0">
        <rect x="30" y="190" width="148" height="34" rx="14" fill="white" stroke="#8b1a2a" stroke-width="1"/>
        <polygon points="90,224 83,236 97,224" fill="white" stroke="#8b1a2a" stroke-width="1"/>
        <text x="104" y="212" text-anchor="middle" fill="#8b1a2a" font-size="11" font-family="sans-serif">Je cherche un runner 🏃</text>
      </g>

      <g id="b2" opacity="0">
        <rect x="322" y="155" width="148" height="34" rx="14" fill="#fff0f2" stroke="#8b1a2a" stroke-width="1"/>
        <polygon points="400,189 393,201 407,189" fill="#fff0f2" stroke="#8b1a2a" stroke-width="1"/>
        <text x="396" y="177" text-anchor="middle" fill="#8b1a2a" font-size="11" font-family="sans-serif">Moi aussi ! T'es où ? 📍</text>
      </g>

      <g id="b3" opacity="0">
        <rect x="30" y="140" width="148" height="34" rx="14" fill="white" stroke="#8b1a2a" stroke-width="1"/>
        <polygon points="90,174 83,186 97,174" fill="white" stroke="#8b1a2a" stroke-width="1"/>
        <text x="104" y="162" text-anchor="middle" fill="#8b1a2a" font-size="11" font-family="sans-serif">Caen ! Samedi matin ? ☀️</text>
      </g>

      <g id="matchG" opacity="0">
        <circle cx="250" cy="170" r="32" fill="#8b1a2a"/>
        <text x="250" y="165" text-anchor="middle" fill="white" font-size="20">✓</text>
        <text x="250" y="182" text-anchor="middle" fill="white" font-size="10" font-family="sans-serif" font-weight="500">MATCH !</text>
      </g>
    </svg>
  </div>
</section>

<div class="divider-light"></div>

<section class="index-story">
  <div class="index-story-inner">
    <div class="story-step">
      <span class="story-num">01 — Arrive</span>
      <h3 class="story-title">Tu débarques dans une nouvelle ville</h3>
      <p class="story-desc">Nouveau boulot, nouvelle vie. Tout est à construire — y compris les gens avec qui partager tout ça.</p>
    </div>
    <div class="story-step">
      <span class="story-num">02 — Rejoins</span>
      <h3 class="story-title">Tu trouves une sortie qui te ressemble</h3>
      <p class="story-desc">Quelqu'un propose une rando le week-end. Un autre cherche un partenaire de tennis. Tu choisis, tu rejoins.</p>
    </div>
    <div class="story-step">
      <span class="story-num">03 — Rencontre</span>
      <h3 class="story-title">Vous vous retrouvez pour de vrai</h3>
      <p class="story-desc">Un message, une confirmation, et c'est parti. La rencontre se fait autour de ce que vous aimez vraiment.</p>
    </div>
  </div>
</section>

<div class="divider-light"></div>

<section class="index-activites">
  <div class="index-activites-inner">
    <div class="act-label">Des activités pour tous les goûts</div>
    <div class="act-cards">
      <div class="act-card">Running</div>
      <div class="act-card">Randonnée</div>
      <div class="act-card">Cuisine</div>
      <div class="act-card">Escalade</div>
      <div class="act-card">Yoga</div>
      <div class="act-card">Jeux de société</div>
    </div>
    <p class="act-more">Et bien d'autres — propose la tienne.</p>
  </div>
</section>

<section class="index-cta">
  <h2 class="index-cta-title">Prêt à rencontrer quelqu'un ?</h2>
  <p class="index-cta-sub">Rejoins les sorties près de chez toi ou propose la tienne.</p>
  <div class="index-cta-btns">
    <a href="auth/inscription.php" class="cta-btn-light">Créer un compte</a>
    <a href="sorties.php" class="cta-btn-outline-light">Voir les sorties</a>
  </div>
</section>

<script>
const b1 = document.getElementById('b1');
const b2 = document.getElementById('b2');
const b3 = document.getElementById('b3');
const matchG = document.getElementById('matchG');
const persoL = document.getElementById('persoL');
const persoR = document.getElementById('persoR');

function fadeIn(el) { el.setAttribute('opacity', '1'); }
function fadeOut(el) { el.setAttribute('opacity', '0'); }

function moveToCenter() {
  let step = 0;
  const interval = setInterval(() => {
    step++;
    const p = step / 35;
    const e = p < 0.5 ? 2*p*p : -1+(4-2*p)*p;
    persoL.setAttribute('transform', `translate(${e * 80}, 0)`);
    persoR.setAttribute('transform', `translate(${-e * 80}, 0)`);
    if (step >= 35) clearInterval(interval);
  }, 20);
}

function resetPersons() {
  persoL.setAttribute('transform', 'translate(0,0)');
  persoR.setAttribute('transform', 'translate(0,0)');
}

function runSequence() {
  resetPersons();
  fadeOut(b1); fadeOut(b2); fadeOut(b3); fadeOut(matchG);
  setTimeout(() => fadeIn(b1), 500);
  setTimeout(() => fadeIn(b2), 1800);
  setTimeout(() => fadeIn(b3), 3100);
  setTimeout(() => {
    fadeOut(b1); fadeOut(b2); fadeOut(b3);
    fadeIn(matchG);
  }, 4400);
  setTimeout(() => {
    fadeOut(matchG);
    moveToCenter();
  }, 5800);
  setTimeout(() => { setTimeout(runSequence, 800); }, 7000);
}

setTimeout(runSequence, 600);
</script>

<?php require_once 'includes/footer.php'; ?>