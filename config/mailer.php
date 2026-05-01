<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function envoyerEmail($destinataire, $sujet, $corps) {
    $mail = new PHPMailer(true);

    $host = getenv('SMTP_HOST') ?: '';
    $username = getenv('SMTP_USERNAME') ?: '';
    $password = getenv('SMTP_PASSWORD') ?: '';
    $port = (int) (getenv('SMTP_PORT') ?: 587);
    $secure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS;
    $from = getenv('SMTP_FROM') ?: 'noreply@rencontreirl.fr';
    $fromName = getenv('SMTP_FROM_NAME') ?: 'Rencontre IRL';

    if ($host === '' || $username === '' || $password === '') {
        return false;
    }

    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = $secure;
        $mail->Port       = $port;

        $mail->setFrom($from, $fromName);
        $mail->addAddress($destinataire);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $sujet;
        $mail->Body    = $corps;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
