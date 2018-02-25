/**
 * Control authentication for auth plugin synchronisation
 *
 * @module package/sequry/core/bin/controls/auth/SyncAuthenticate
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require package/sequry/core/bin/controls/auth/Authenticate
 * @require package/sequry/core/bin/Authentication
 * @require css!package/sequry/core/bin/controls/auth/SyncAuthenticate.css
 *
 * @event onFinish
 * @event onAbort - on AuthPopup user close
 * @event onClose - on AuthPopup close
 */
define('package/sequry/core/bin/controls/auth/SyncAuthenticate', [

    'package/sequry/core/bin/controls/auth/Authenticate',

    'css!package/sequry/core/bin/controls/auth/SyncAuthenticate.css'

], function (AuthenticationControl) {
    "use strict";

    return new Class({

        Extends: AuthenticationControl,
        Type   : 'package/sequry/core/bin/controls/auth/SyncAuthenticate',

        Binds: [
            '$onLoaded'
        ],

        options: {
            'authPluginId': false // id authentication plugin that is to be synced
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onLoaded: this.$onLoaded
            });
        },

        /**
         * Disable sync auth plugin
         */
        $onLoaded: function () {
            var syncAuthPluginId = this.getAttribute('authPluginId');

            for (var i = 0, len = this.$authPluginControls.length; i < len; i++) {
                var authPluginId = this.$authPluginControls[i].getAttribute('authPluginId');

                if (authPluginId !== syncAuthPluginId) {
                    continue;
                }

                var AuthPluginElm = this.$authPluginControls[i].getElm();
                AuthPluginElm.getParent().setStyle('display', 'none');
            }
        }
    });
});
