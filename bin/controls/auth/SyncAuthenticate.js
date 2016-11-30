/**
 * Control authentication for auth plugin synchronisation
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthenticate
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 *
 * @event onFinish
 * @event onAbort - on AuthPopup user close
 * @event onClose - on AuthPopup close
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthenticate', [

    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthenticate.css'

], function (AuthenticationControl, AuthHandler) {
    "use strict";

    var Authentication = new AuthHandler();

    return new Class({

        Extends: AuthenticationControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthenticate',

        Binds: [
            '$onLoaded',
            '$getPasswordId'
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
            var self = this;

            var syncAuthPluginId = this.getAttribute('authPluginId');

            this.$AuthPopup.Loader.show();

            Authentication.getAllowedSyncAuthPlugins(syncAuthPluginId).then(function (allowedAuthPluginIds) {
                for (var i = 0, len = self.$authPluginControls.length; i < len; i++) {
                    var authPluginId = self.$authPluginControls[i].getAttribute('authPluginId');

                    if (allowedAuthPluginIds.contains(authPluginId)) {
                        continue;
                    }

                    var AuthPluginElm = self.$authPluginControls[i].getElm();

                    AuthPluginElm.getParent().setStyle('display', 'none');
                }

                self.$AuthPopup.Loader.hide();
            });
        }
    });
});
