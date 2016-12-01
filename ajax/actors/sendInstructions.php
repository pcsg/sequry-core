<?php

/**
 * Send instructions for usage of password management
 *
 * @param integer $userId - ID of user
 * @return bool - success
 */
function package_pcsg_grouppasswordmanager_ajax_actors_sendInstructions($userId)
{
    $User = QUI::getUsers()->get($userId);

    $emailAddress = $User->getAttribute('email');

    if (empty($emailAddress)) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'exception.actors.sendinstructions.no.email.address',
                array(
                    'userId'   => $User->getId(),
                    'userName' => $User->getUsername()
                )
            )
        );

        return false;
    }

    $Mailer = new \QUI\Mail\Mailer();

    $Mailer->setFrom('info@pcsg.de');
    $Mailer->setFromName('QUIQQER PasswordManager');

    $Mailer->setHTML('<p></p>');

    $Mailer->setBody(
        QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'actors.sendinstructions.instructions',
            array(
                'userName' => $User->getUsername(),
                'url'      => $_SERVER['HTTP_ORIGIN'] . '/admin/'
            )
        )
    );

    $Mailer->setSubject(
        QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'actors.sendinstructions.subject'
        )
    );

    $Mailer->addRecipient($emailAddress);

    try {
        $Mailer->send();
    } catch (\Exception $Exception) {
        QUI\System\Log::addError($Exception->getMessage());

        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'error.actors.sendinstructions'
            )
        );

        return false;
    }

    QUI::getMessagesHandler()->addSuccess(
        QUI::getLocale()->get(
            'pcsg/grouppasswordmanager',
            'success.actors.sendinstructions'
        )
    );

    $User->setAttribute('pcsg.gpm.instructions.sent', true);
    $User->save(QUI::getUsers()->getSystemUser());

    return true;
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_actors_sendInstructions',
    array('userId'),
    'Permission::checkAdminUser'
);
