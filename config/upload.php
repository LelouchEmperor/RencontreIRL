<?php
/**
 * Helper upload photo sécurisé
 * Retourne ['ok' => true, 'nom' => 'fichier.jpg']
 * ou       ['ok' => false, 'erreur' => 'message']
 */
function valider_et_upload_photo(array $file, int $user_id, ?string $ancienne_photo = null): array
{
    $max_size   = 2 * 1024 * 1024; // 2 Mo
    $mime_autorises = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // 1. Erreur d'upload PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'erreur' => 'Erreur lors de l\'upload. Réessaie.'];
    }

    // 2. Taille
    if ($file['size'] > $max_size) {
        return ['ok' => false, 'erreur' => 'La photo ne doit pas dépasser 2 Mo.'];
    }

    // 3. MIME réel via finfo (lit les vrais octets du fichier)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, $mime_autorises)) {
        return ['ok' => false, 'erreur' => 'Format non autorisé. Utilise JPG, PNG ou WEBP.'];
    }

    // 4. Double vérification : getimagesize() confirme que c'est une vraie image
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['ok' => false, 'erreur' => 'Le fichier ne semble pas être une image valide.'];
    }

    // 5. Nom de fichier aléatoire — jamais basé sur l'input user
    $ext         = $mime_autorises[$mime];
    $nom_fichier = 'user_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dossier     = $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/uploads/';
    $chemin      = $dossier . $nom_fichier;

    if (!move_uploaded_file($file['tmp_name'], $chemin)) {
        return ['ok' => false, 'erreur' => 'Impossible de sauvegarder la photo. Réessaie.'];
    }

    // 6. Supprimer l'ancienne photo si elle existe
    if ($ancienne_photo) {
        $ancienne_chemin = $dossier . $ancienne_photo;
        if (file_exists($ancienne_chemin)) {
            unlink($ancienne_chemin);
        }
    }

    return ['ok' => true, 'nom' => $nom_fichier];
}