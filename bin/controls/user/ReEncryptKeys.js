/**
 * Re-encrypt all user keys
 *
 * @module package/sequry/core/bin/controls/user/ReEncryptKeys
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require qui/controls/buttons/Button
 * @require package/sequry/core/bin/Authentication
 * @require package/sequry/core/bin/Actors
 * @require Ajax
 * @require Locale
 */
define('package/sequry/core/bin/controls/user/ReEncryptKeys', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',
    'package/sequry/core/bin/Authentication',
    'package/sequry/core/bin/Actors',

    'Ajax',
    'Locale',

    'css!package/sequry/core/bin/controls/user/ReEncryptKeys.css'

], function (QUIControl, QUILoader, QUIButton, Authentication, Actors, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/user/ReEncryptKeys',

        Binds: [],

        initialize: function (options) {
            this.parent(options);

            this.$Elm   = null;
            this.Loader = new QUILoader();

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * event: onImport
         */
        $onInject: function () {
            var self = this;

            this.$Elm.addClass('pcsg-gpm-user-reencryptkeys');

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            this.$isReEncryptEnabled().then(function(enabled) {
                if (!enabled) {
                    self.$Elm.set(
                        'html',
                        '<p>' + QUILocale.get(lg, 'controls.user.ReEncryptKeys.info.not.enabled') + '</p>'
                    );

                    return;
                }

                self.$Elm.set(
                    'html',
                    '<p>' + QUILocale.get(lg, 'controls.user.ReEncryptKeys.info') + '</p>'
                );

                new QUIButton({
                    textimage: 'fa fa-key',
                    text     : QUILocale.get(lg, 'controls.user.ReEncryptKeys.btn.title'),
                    events   : {
                        onClick: function () {
                            self.Loader.show();

                            Authentication.authAll().then(function (AuthData) {
                                Actors.reEncryptAllKeys(AuthData).then(function () {
                                    self.Loader.hide();
                                });
                            }, function () {
                                self.Loader.hide();
                            });
                        }
                    }
                }).inject(self.$Elm);
            });
        },

        /**
         * Checks if re-encryption is allowed
         *
         * @return {Promise}
         */
        $isReEncryptEnabled: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_sequry_core_ajax_actors_isReEncryptAllEnabled',
                    resolve, {
                        'package': 'sequry/core',
                        onError  : reject
                    }
                );
            });
        }
    });
});
