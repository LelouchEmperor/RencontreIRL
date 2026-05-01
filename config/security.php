<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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
