<?php

use Pcsg\GroupPasswordManager\PasswordLink;
use QUI\Utils\Security\Orthos;
use Pcsg\GroupPasswordManager\PasswordTypes\Handler as PasswordTypesHandler;
use Pcsg\GroupPasswordManager\Security\Exception\InvalidKeyException;

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

$error              = false;
$payloadHtml        = false;
$password           = false;
$passwordProtected  = false;
$invalidPasswordMsg = false;
$data               = array();
$Password           = false;

if (!empty($_POST['password'])) {
    $password = $_POST['password'];
}

try {
    $PasswordLink = new PasswordLink((int)$_REQUEST['id']);

    if ($PasswordLink->isPasswordProtected()) {
        $passwordProtected = true;

        if ($password) {
            $Password = $PasswordLink->getPassword($_REQUEST['hash'], $password);
            $passwordProtected = false;
        }
    } else {
        $Password = $PasswordLink->getPassword($_REQUEST['hash']);
    }

    if ($Password) {
        $data = $Password->getViewData();

        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = Orthos::escapeHTML($v);
            }
        }

        $TypeClass   = PasswordTypesHandler::getPasswordTypeClass($Password->getDataType());
        $payloadHtml = $TypeClass->getViewHtml($data['payload']);

        $Engine->assign(array(
            'title'       => $data['title'],
            'payloadHtml' => $payloadHtml
        ));
    }
} catch (InvalidKeyException $Exception) {
    $invalidPasswordMsg = QUI::getLocale()->get(
        'pcsg/grouppasswordmanager',
        'message.sitetypes.passwordlink.wrong_password'
    );
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    $error = true;
}

$Engine->assign(array(
    'message'            => $PasswordLink->getContentMessage(),
    'error'              => $error,
    'passwordProtected'  => $passwordProtected,
    'invalidPasswordMsg' => $invalidPasswordMsg
));
