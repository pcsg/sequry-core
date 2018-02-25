/**
 * Send password manager instruction to the user
 *
 * @module package/sequry/core/bin/controls/user/SendInstructions
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/sequry/core/bin/classes/Passwords
 * @require package/sequry/core/bin/controls/auth/Authenticate
 * @require package/sequry/core/bin/controls/user/Select
 * @require package/sequry/core/bin/controls/actors/EligibleActorSelect
 * @require text!package/sequry/core/bin/controls/user/SendInstructions.html
 * @require css!package/sequry/core/bin/controls/user/SendInstructions.css
 *
 * @event onLoaded
 * @event onSuccess
 */
define('package/sequry/core/bin/controls/user/SendInstructions', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',

    'Ajax',

    'Locale',

    'css!package/sequry/core/bin/controls/user/SendInstructions.css'

], function (QUIControl, QUIButton, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/user/SendInstructions',

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
                        self.$SendBtn.disable();

                        QUIAjax.post('package_sequry_core_ajax_actors_sendInstructions', function (result) {
                            if (!result) {
                                self.$SendBtn.enable();
                            }
                        }, {
                            'package': 'sequry/core',
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
                    var InfoElm = new Element('div', {
                        'class': 'pcsg-gpm-instructions-info'
                    }).inject(self.$Elm);

                    new Element('p', {
                        html: QUILocale.get(lg, 'control.user.sendinstructions.already.sent')
                    }).inject(InfoElm);

                    var Label = new Element('label', {
                        html: QUILocale.get(lg, 'control.user.sendinstructions.force.sent')
                    }).inject(InfoElm);

                    new Element('input', {
                        type: 'checkbox',
                        events: {
                            change: function(event) {
                                if (event.target.checked) {
                                    self.$SendBtn.enable();
                                } else {
                                    self.$SendBtn.disable();
                                }
                            }
                        }
                    }).inject(Label);

                    return;
                }

                self.$SendBtn.enable();
            }).delay(500);
            // @todo
        }
    });
});
