<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/vendor/autoload.php';

function envoyerEmail($destinataire, $sujet, $corps) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'a883fb21311d5a';
        $mail->Password   = '3d21716d8abb4f';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 2525;

        $mail->setFrom('noreply@rencontreirl.fr', 'Rencontre IRL');
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