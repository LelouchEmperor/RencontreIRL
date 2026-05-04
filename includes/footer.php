  </main>

<footer class="footer">
  <a href="/Site_rencontre/RencontreIRL/app/pages/mentions-legales.php" class="footer-text">Mentions légales</a>
  <a href="/Site_rencontre/RencontreIRL/app/legal/privacy.php" class="footer-text">Confidentialite</a>
  <a href="/Site_rencontre/RencontreIRL/app/legal/safety.php" class="footer-text">Securite</a>
  <span class="footer-text">Rencontre — Kindle Bloom</span>
</footer>

<div id="cookieBanner" class="cookie-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="cookieTitle">
  <div class="cookie-modal">
    <h2 id="cookieTitle">Cookies necessaires</h2>
    <p class="cookie-text">
      Ce site utilise uniquement des cookies de session necessaires a son fonctionnement, notamment pour maintenir ta connexion. Aucun cookie publicitaire, statistique ou de tracking n'est utilise.
    </p>
    <button class="cookie-btn" onclick="acceptCookies()">J'ai compris</button>
  </div>
</div>

<script>
function acceptCookies() {
  localStorage.setItem('cookies_accepted', '1');
  document.getElementById('cookieBanner').style.display = 'none';
}

if (!localStorage.getItem('cookies_accepted')) {
  document.getElementById('cookieBanner').style.display = 'grid';
}
</script>
</body>
</html>
