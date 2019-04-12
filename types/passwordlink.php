<?php

use Sequry\Core\PasswordLink;
use QUI\Utils\Security\Orthos;
use Sequry\Core\Security\Exception\InvalidKeyException;

$error              = false;
$payloadHtml        = false;
$password           = false;
$passwordProtected  = false;
$invalidPasswordMsg = false;
$data               = [];
$Password           = false;

if (empty($_REQUEST['id']) || empty($_REQUEST['hash'])) {
    $error = true;
} else {
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
    }

    try {
        $PasswordLink = new PasswordLink((int)$_REQUEST['id']);

        if ($PasswordLink->isPasswordProtected()) {
            $passwordProtected = true;

            if ($password) {
                $Password          = $PasswordLink->getPassword($_REQUEST['hash'], $password);
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

            $Engine->assign([
                'type'        => $Password->getDataType(),
                'payLoadData' => json_encode($data['payload']),
                'title'       => htmlspecialchars($PasswordLink->getContentTitle()),
                'message'     => htmlspecialchars($PasswordLink->getContentMessage())
            ]);
        }
    } catch (InvalidKeyException $Exception) {
        $invalidPasswordMsg = QUI::getLocale()->get(
            'sequry/core',
            'message.sitetypes.passwordlink.wrong_password'
        );
    } catch (\Exception $Exception) {
        QUI\System\Log::writeException($Exception);
        $error = true;
    }
}

if ($error) {
    $Respone = QUI::getGlobalResponse();
    $Respone->setStatusCode(404);
}

$Engine->assign([
    'error'              => $error,
    'passwordProtected'  => $passwordProtected,
    'invalidPasswordMsg' => $invalidPasswordMsg
]);
