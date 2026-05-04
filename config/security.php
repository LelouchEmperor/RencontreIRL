<?php

function appliquer_headers_securite(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    header('Cross-Origin-Opener-Policy: same-origin');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function durcir_configuration_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
}

function demarrer_session_securisee(): void
{
    appliquer_headers_securite();

    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    durcir_configuration_session();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function session_expiree_par_inactivite(int $delai_secondes = 3600): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $derniere_activite = (int) ($_SESSION['derniere_activite'] ?? time());

    if (time() - $derniere_activite > $delai_secondes) {
        return true;
    }

    $_SESSION['derniere_activite'] = time();
    return false;
}

function detruire_session_courante(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    session_destroy();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function erreur_mot_de_passe(?string $mot_de_passe, array $contexte = []): ?string
{
    $mot_de_passe = (string) $mot_de_passe;

    if (strlen($mot_de_passe) < 10) {
        return 'Le mot de passe doit faire au moins 10 caracteres.';
    }

    if (!preg_match('/[a-z]/', $mot_de_passe)) {
        return 'Le mot de passe doit contenir au moins une minuscule.';
    }

    if (!preg_match('/[A-Z]/', $mot_de_passe)) {
        return 'Le mot de passe doit contenir au moins une majuscule.';
    }

    if (!preg_match('/\d/', $mot_de_passe)) {
        return 'Le mot de passe doit contenir au moins un chiffre.';
    }

    foreach ($contexte as $valeur) {
        $valeur = trim((string) $valeur);
        $valeur_longueur = function_exists('mb_strlen') ? mb_strlen($valeur, 'UTF-8') : strlen($valeur);
        $mot_de_passe_normalise = function_exists('mb_strtolower') ? mb_strtolower($mot_de_passe, 'UTF-8') : strtolower($mot_de_passe);
        $valeur_normalisee = function_exists('mb_strtolower') ? mb_strtolower($valeur, 'UTF-8') : strtolower($valeur);

        if ($valeur !== '' && $valeur_longueur >= 4 && $mot_de_passe_normalise === $valeur_normalisee) {
            return 'Le mot de passe ne doit pas etre identique a ton email, prenom ou ville.';
        }
    }

    return null;
}

function chemin_upload(string $nom_fichier): ?string
{
    $nom_fichier = basename($nom_fichier);

    if (!preg_match('/\A[a-zA-Z0-9_.-]+\z/', $nom_fichier)) {
        return null;
    }

    return $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/public/uploads/' . $nom_fichier;
}

function app_url(string $chemin = ''): string
{
    return '/Site_rencontre/RencontreIRL/' . ltrim($chemin, '/');
}

function texte_court(?string $texte, int $longueur = 120): string
{
    $texte = trim((string) preg_replace('/\s+/', ' ', $texte ?? ''));

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($texte, 0, $longueur, '...', 'UTF-8');
    }

    return strlen($texte) > $longueur ? substr($texte, 0, max(0, $longueur - 3)) . '...' : $texte;
}

function lien_interne(?string $lien, string $fallback = 'app/pages/sorties.php'): string
{
    $lien = trim((string) $lien);

    if ($lien === '' || preg_match('/\A(?:https?:)?\/\//i', $lien)) {
        return app_url($fallback);
    }

    if (str_starts_with($lien, '/Site_rencontre/RencontreIRL/')) {
        return $lien;
    }

    if (!str_contains($lien, '/') && str_ends_with($lien, '.php')) {
        return app_url('app/pages/' . $lien);
    }

    return app_url($lien);
}

function account_status_label(?string $status): string
{
    return match ($status) {
        null, '', 'active' => 'Actif',
        'suspended' => 'Suspendu',
        'banned' => 'Banni',
        default => 'Inconnu',
    };
}

function sortie_statut_effectif(array $sortie): string
{
    $status = $sortie['status'] ?? 'open';

    if ($status === 'cancelled') {
        return 'cancelled';
    }

    if (!empty($sortie['date_sortie']) && strtotime((string) $sortie['date_sortie']) < time()) {
        return 'finished';
    }

    if (isset($sortie['places_restantes']) && (int) $sortie['places_restantes'] <= 0) {
        return 'full';
    }

    return 'open';
}

function sortie_statut_label(string $status): string
{
    return match ($status) {
        'open' => 'Ouverte',
        'full' => 'Complete',
        'cancelled' => 'Annulee',
        'finished' => 'Terminee',
        default => 'Inconnu',
    };
}
