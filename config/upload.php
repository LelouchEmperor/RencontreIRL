<?php
/**
 * Helper upload photo securise.
 * Retourne ['ok' => true, 'nom' => 'fichier.jpg']
 * ou       ['ok' => false, 'erreur' => 'message'].
 */
function valider_et_upload_photo(array $file, int $user_id, ?string $ancienne_photo = null): array
{
    $max_size = 2 * 1024 * 1024; // 2 Mo
    $mime_autorises = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'erreur' => 'Erreur lors de l\'upload. Reessaie.'];
    }

    if (($file['size'] ?? 0) > $max_size) {
        return ['ok' => false, 'erreur' => 'La photo ne doit pas depasser 2 Mo.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'erreur' => 'Upload invalide.'];
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'erreur' => 'Extension non autorisee. Utilise JPG, PNG ou WEBP.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, $mime_autorises)) {
        return ['ok' => false, 'erreur' => 'Format non autorise. Utilise JPG, PNG ou WEBP.'];
    }

    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['ok' => false, 'erreur' => 'Le fichier ne semble pas etre une image valide.'];
    }

    $ext = $mime_autorises[$mime];
    $nom_fichier = 'user_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dossier = $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/public/uploads/';
    $chemin = $dossier . $nom_fichier;

    if (!is_dir($dossier) && !mkdir($dossier, 0755, true)) {
        return ['ok' => false, 'erreur' => 'Dossier upload indisponible.'];
    }

    if (!move_uploaded_file($file['tmp_name'], $chemin)) {
        return ['ok' => false, 'erreur' => 'Impossible de sauvegarder la photo. Reessaie.'];
    }

    if ($ancienne_photo) {
        $ancienne_chemin = chemin_upload($ancienne_photo);
        if ($ancienne_chemin && file_exists($ancienne_chemin)) {
            unlink($ancienne_chemin);
        }
    }

    return ['ok' => true, 'nom' => $nom_fichier];
}
