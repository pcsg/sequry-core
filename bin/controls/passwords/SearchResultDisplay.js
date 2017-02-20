/**
 * Display a password result for QUIQQER Desktop Search
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwords/SearchResultDisplay
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwords/SearchResultDisplay', [

], function () {
    "use strict";

    return new Class({

        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwords/SearchResultDisplay',

        initialize: function (options) {
            if (!options.passwordId) {
                return;
            }

            if (window.PasswordList) {
                window.PasswordList.viewPassword(options.passwordId);
                return;
            }

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel',
                'utils/Panels'
            ], function(PasswordManager, PanelUtils) {
                PanelUtils.openPanelInTasks(new PasswordManager()).then(function(Panel) {
                    Panel.open();

                    var waitForPanel = setInterval(function() {
                        if (window.PasswordList) {
                            window.PasswordList.viewPassword(options.passwordId);
                            clearInterval(waitForPanel);
                        }
                    }, 200);
                });
            });
        }
    });
});
