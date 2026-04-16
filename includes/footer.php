<footer class="footer">
  <a href="/Site_rencontre/RencontreIRL/mentions-legales.php" class="footer-text">Mentions légales</a>
  <span class="footer-text">Rencontre — Kindle Bloom</span>
</footer>

<div id="cookieBanner">
  <p class="cookie-text">
    Ce site utilise uniquement des cookies de session nécessaires à son fonctionnement (maintien de la connexion). Aucun cookie publicitaire ou de tracking n'est utilisé.
  </p>
  <button class="cookie-btn" onclick="acceptCookies()">J'ai compris</button>
</div>

<script>
function acceptCookies() {
  localStorage.setItem('cookies_accepted', '1');
  document.getElementById('cookieBanner').style.display = 'none';
}

if (!localStorage.getItem('cookies_accepted')) {
  document.getElementById('cookieBanner').style.display = 'flex';
}
</script>