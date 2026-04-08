<?php
session_start();
session_destroy();
header('Location: /Site_rencontre/RencontreIRL/index.php');
exit;   