<?php

use Pcsg\GroupPasswordManager\PasswordLink;
use QUI\Utils\Security\Orthos;
use Pcsg\GroupPasswordManager\PasswordTypes\Handler as PasswordTypesHandler;

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

$error       = false;
$payloadHtml = false;
$data        = array();

try {
    $PasswordLink = new PasswordLink((int)$_REQUEST['id']);

    $Password = $PasswordLink->getPassword($_REQUEST['hash']);
    $data     = $Password->getViewData();

    foreach ($data as $k => $v) {
        if (is_string($v)) {
            $data[$k] = Orthos::escapeHTML($v);
        }
    }

    $TypeClass   = PasswordTypesHandler::getPasswordTypeClass($Password->getDataType());
    $payloadHtml = $TypeClass->getViewHtml($data['payload']);

    $Engine->assign(array(
        'title'       => $data['title'],
        'description' => $data['description'],
        'payloadHtml' => $payloadHtml
    ));
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    $error = true;
}

$Engine->assign(array(
    'error' => $error,
));

