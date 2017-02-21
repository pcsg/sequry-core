<?php

/**
 * Get a list of all passwords the currently logged in user has access to
 *
 * @param string $searchParams - search options [json]
 * @return array|false - false on error or password list otherwise
 */
function package_pcsg_grouppasswordmanager_ajax_passwords_getList($searchParams)
{
    try {
        $CryptoUser = \Pcsg\GroupPasswordManager\Security\Handler\CryptoActors::getCryptoUser();

        $searchParams = \QUI\Utils\Security\Orthos::clearArray(
            json_decode($searchParams, true)
        );

        $Grid      = new \QUI\Utils\Grid($searchParams);
        $passwords = $CryptoUser->getPasswordList($searchParams);

        return $Grid->parseResult(
            $passwords,
            $CryptoUser->getPasswordList($searchParams, true)
        );
    } catch (QUI\Exception $Exception) {
        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.ajax.passwords.getList.error'
            )
        );

        return false;
    } catch (\Exception $Exception) {
        QUI\System\Log::addError(
            'AJAX :: package_pcsg_grouppasswordmanager_ajax_passwords_delete -> '
            . $Exception->getMessage()
        );

        QUI::getMessagesHandler()->addError(
            QUI::getLocale()->get(
                'pcsg/grouppasswordmanager',
                'message.general.error'
            )
        );

        return false;
    }
}

\QUI::$Ajax->register(
    'package_pcsg_grouppasswordmanager_ajax_passwords_getList',
    array('searchParams'),
    'Permission::checkAdminUser'
);
