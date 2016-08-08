<?php

define('QUIQQER_SYSTEM', true);

require dirname(dirname(dirname(dirname(__FILE__)))) . '/header.php';

if (!isset($_REQUEST['code'])
    || empty($_REQUEST['code'])
) {
    exit;
}

$code         = preg_split("//u", $_REQUEST['code'], -1, PREG_SPLIT_NO_EMPTY);
$codeReadable = '';
$i            = 0;

foreach ($code as $char) {
    if ($i % 5 === 0 && $i > 0) {
        $codeReadable .= '-';
    }

    $codeReadable .= $char;
    $i++;
}

if (!isset($_REQUEST['authPluginId'])
    || empty($_REQUEST['authPluginId'])
) {
    exit;
}

if (!isset($_REQUEST['lang'])
    || empty($_REQUEST['lang'])
) {
    exit;
}

$lang  = $_REQUEST['lang'];
$langs = QUI::availableLanguages();

if (!in_array($lang, $langs)) {
    $lang = 'en';
}

try {
    $AuthPlugin = \Pcsg\GroupPasswordManager\Security\Handler\Authentication::getAuthPlugin(
        (int)$_REQUEST['authPluginId']
    );
} catch (\Exception $Exception) {
    exit;
}

$L = QUI::getLocale();
$L->setCurrent($lang);

$lg = 'pcsg/grouppasswordmanager';

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>QUIQQER Password Manager</title>

<script type="text/javascript">
    window.onload = function() {
        setTimeout(function() {
            if (!document.execCommand(\'print\', false, null)) {
                window.print();
            }
        }, 200);
    }
</script>
<style>
    .data-table {
        float: left;
        position: relative;
        width: 100%;
    }
    
    .data-table .field-container-item {
        width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .pcsg-gpm-recoverycode-code {
        float: left;
        font-size: 30px;
        text-align: center;
        width: 100%;
    }
    
    .warning {
        border: 2px solid red;
        color: #F11010;
        float: left;
        margin: 20px 5% 20px 5%;
        padding: 20px;
        text-align: center;
        width: 90%;
    }
</style>

<link rel="stylesheet" href="/bin/css/style.css">


</head>
<body>
<h1>' . $L->get($lg, 'auth.recoverycodewindow.print.title') . '</h1>
<p class="info">
' . $L->get($lg, 'auth.recoverycodewindow.print.info') . '
</p>

<div class="warning">
    <h1>
    ' . $L->get($lg, 'auth.recoverycodewindow.print.warning.title') . '
    </h1>
    <p>
    ' . $L->get($lg, 'auth.recoverycodewindow.print.warning') . '
    </p>
</div>

<table class="data-table data-table-flexbox product-data">
    <tbody>
    <tr>
        <td>
            <label class="field-container">
                <span class="field-container-item" title="' . $L->get($lg, 'auth.recoverycodewindow.username') . '">
                    ' . $L->get($lg, 'auth.recoverycodewindow.username') . '
                </span>
                <span class="field-container-field">
                    ' . QUI::getUserBySession()->getUsername() . '
                </span>
            </label>
        </td>
    </tr>
    <tr>
        <td>
            <label class="field-container">
                <span class="field-container-item" title="' . $L->get($lg, 'auth.auth.recoverycodewindow.authplugin') . '">
                    ' . $L->get($lg, 'auth.auth.recoverycodewindow.authplugin') . '
                </span>
                <span class="field-container-field">
                    ' . $AuthPlugin->getAttribute('title') . ' (ID:' . $_REQUEST['authPluginId'] . ')
                </span>
            </label>
        </td>
    </tr>
    <tr>
        <td>
            <label class="field-container">
                <span class="field-container-item" title="' . $L->get($lg, 'auth.recoverycodewindow.date') . '">
                    ' . $L->get($lg, 'auth.recoverycodewindow.date') . '
                </span>
                <span class="field-container-field">
                    ' . date('d.m.Y') . '
                </span>
            </label>
        </td>
    </tr>
    <tr>
        <td>
            <span class="field-container-field pcsg-gpm-recoverycode-code">
                ' . $codeReadable . '
            </span>
        </td>
    </tr>
    </tbody>
</table>
</body>
</html>';

echo $html;

exit;
