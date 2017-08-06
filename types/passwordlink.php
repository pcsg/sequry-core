<?php

use Pcsg\GroupPasswordManager\PasswordLink;

function send400()
{
    $Respone = QUI::getGlobalResponse();
    $Respone->setStatusCode(400);
    $Respone->send();

    exit;
}

if (empty($_REQUEST['id'])
    || empty($_REQUEST['hash'])
) {
    send400();
}

$error = false;
$data  = array();

try {
    $PasswordLink = new PasswordLink((int)$_REQUEST['id']);
    $data         = $PasswordLink->getPasswordData($_REQUEST['hash']);
} catch (\Exception $Exception) {
    $error = true;
}

$Engine->assign(array(
    'error' => $error,
    'data'  => $data
));

