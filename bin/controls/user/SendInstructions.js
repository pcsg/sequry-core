/**
 * Send password manager instruction to the user
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/user/SendInstructions
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @require package/pcsg/grouppasswordmanager/bin/controls/user/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/user/SendInstructions.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/user/SendInstructions.css
 *
 * @event onLoaded
 * @event onSuccess
 */
define('package/pcsg/grouppasswordmanager/bin/controls/user/SendInstructions', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',

    'Ajax',

    'Locale'

], function (QUIControl, QUIButton, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/user/SendInstructions',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$SendBtn = null;
            this.$User    = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.parent();

            var self = this;

            this.$SendBtn = new QUIButton({
                text     : QUILocale.get(lg, 'control.user.sendinstructions.btn.text'),
                textimage: 'fa fa-envelope',
                events   : {
                    onClick: function () {
                        QUIAjax.post('package_pcsg_grouppasswordmanager_ajax_actors_sendInstructions', function (result) {
                            if (result) {
                                self.$SendBtn.disable();
                            }
                        }, {
                            'package': 'pcsg/grouppasswordmanager',
                            userId   : self.$User.getId()
                        });
                    }
                }
            }).inject(this.$Elm);

            this.$SendBtn.disable();

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            (function () {
                self.$User = self.getAttribute('Panel').getUser();

                if (self.$User.getAttribute('pcsg.gpm.instructions.sent')) {
                    new Element('p', {
                        html: QUILocale.get(lg, 'control.user.sendinstructions.already.sent')
                    }).inject(self.$Elm);

                    return;
                }

                self.$SendBtn.enable();
            }).delay(500);
            // @todo
        }
    });
});
