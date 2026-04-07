<?php
session_start();
session_destroy();
header('Location: /rencontre_site_web/index.php');
exit;   