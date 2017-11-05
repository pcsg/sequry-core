/**
 * Recovery process for a single authentication plugin
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',

    'Locale',
    'Mustache',
    'Ajax',

    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery.css'

], function (QUI, QUIControl, QUILoader, QUIButton, QUILocale, Mustache,
             QUIAjax, Authentication, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery',

        Binds: [
            '$onInject',
            '$buildContent'
        ],

        options: {
            authPluginId: false // ID of the authentication plugin whose credentials should be recovered
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader      = new QUILoader();
            this.$AuthPlugin = {};
            this.$CheckBtn   = null;
        },

        /**
         * event : oninject
         */
        $onInject: function () {
            var self = this;

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            Authentication.getAuthPluginInfo(
                this.getAttribute('authPluginId')
            ).then(function (AuthPluginInfo) {
                self.Loader.hide();
                self.$AuthPlugin = AuthPluginInfo;

                self.$buildContent();
            });
        },

        /**
         * Build Recovery content
         */
        $buildContent: function () {
            var self     = this;
            var lgPrefix = 'controls.auth.recovery.template.';

            this.$Elm.set('html', Mustache.render(template, {
                title    : QUILocale.get(lg, lgPrefix + 'title', {
                    authPluginTitle: this.$AuthPlugin.title
                }),
                step1    : QUILocale.get(lg, lgPrefix + 'step1'),
                step1desc: QUILocale.get(lg, lgPrefix + 'step1desc'),
                step2    : QUILocale.get(lg, lgPrefix + 'step2'),
                step2desc: QUILocale.get(lg, lgPrefix + 'step2desc'),
                step3    : QUILocale.get(lg, lgPrefix + 'step3'),
                step3desc: QUILocale.get(lg, lgPrefix + 'step3desc')
            }));

            // step 1 (send recovery token)
            var Step1Content = this.$Elm.getElement(
                '.step-1 .pcsg-gpm-auth-recovery-step-content'
            );

            var TokenInput  = Step1Content.getElement('input');
            var ErrorMsgElm = new Element('span', {
                'class': 'pcsg-gpm-auth-recovery-error pcsg-gpm__hidden'
            }).inject(Step1Content, 'top');

            new QUIButton({
                text     : QUILocale.get(lg, 'controls.auth.recovery.btn.send_token'),
                textimage: 'fa fa-envelope',
                events   : {
                    onClick: function (Btn) {
                        Btn.disable();
                        Btn.setAttribute('textimage', 'fa fa-spin fa-spinner');

                        ErrorMsgElm.addClass('pcsg-gpm__hidden');

                        var FuncChangeBtn = function () {
                            Btn.enable();
                            Btn.setAttributes({
                                textimage: 'fa fa-mail',
                                text     : QUILocale.get(lg, 'controls.auth.recovery.btn.send_token_again')
                            });
                            Btn.setAttribute('textimage', 'fa fa-envelope');
                        };

                        Authentication.sendRecoveryToken(
                            self.getAttribute('authPluginId')
                        ).then(function (success) {
                            FuncChangeBtn();

                            if (success) {
                                TokenInput.disabled = false;
                                TokenInput.focus();
                            } else {
                                TokenInput.disabled = true;
                            }
                        }, function (e) {
                            // display error message
                            ErrorMsgElm.set('html', e.getMessage());
                            ErrorMsgElm.removeClass('pcsg-gpm__hidden');

                            FuncChangeBtn();
                            TokenInput.disabled = true;
                        });
                    }
                }
            }).inject(Step1Content, 'top');

            // step 2 (enter recovery code)
            var Step2Content = this.$Elm.getElement(
                '.step-2 .pcsg-gpm-auth-recovery-step-content'
            );

            var FuncOnCodeInput = function (event) {
                var Elm = event.target;

                if (Elm.value.length < 5) {
                    self.fireEvent('recoveryCodeUnready');
                    return;
                }

                var id = parseInt(Elm.getProperty('data-id'));

                if (id === 5) {
                    self.fireEvent('recoveryCodeReady');
                    return;
                }

                Step2Content.getElement('input[data-id="' + (id + 1) + '"]').focus();
            };

            for (var i = 0; i < 5; i++) {
                var InputElm = new Element('input', {
                    'class'  : 'pcsg-gpm-auth-recovery-code-input',
                    'data-id': i + 1,
                    type     : 'text',
                    maxlength: 5,
                    events   : {
                        input: FuncOnCodeInput
                    }
                }).inject(Step2Content);

                if (i === 0) {
                    InputElm.focus();
                }
            }

            // step 3 (check data and change authentication information)
            this.$CheckBtn = new QUIButton({
                text     : QUILocale.get(lg, 'controls.auth.recovery.btn.check_data'),
                textimage: 'fa fa-check-square-o',
                events   : {
                    onClick: function (Btn) {
                        Btn.disable();
                        Btn.setAttribute('textimage', 'fa fa-spin fa-spinner');

                        self.$validateRecoveryData(
                            self.$getRecoveryCode(),
                            TokenInput.value
                        ).then(function (isValid) {
                            if (isValid) {
                                Btn.setAttribute('textimage', 'fa fa-check');
                                self.$showAuthDataChangeControl();
                            }
                        });


                    }
                }
            }).inject(
                this.$Elm.getElement(
                    '.step-3 .pcsg-gpm-auth-recovery-step-content'
                )
            );
        },

        /**
         * Validate recovery token and code
         *
         * @param {String} code
         * @param {String} token
         * @returns {Promise} - returns bool (success)
         */
        $validateRecoveryData: function (code, token) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_pcsg_grouppasswordmanager_ajax_auth_recovery_validate', resolve, {
                        'package'   : 'pcsg/grouppasswordmanager',
                        authPluginId: self.getAttribute('authPluginId'),
                        code        : code,
                        token       : token,
                        onError     : reject
                    }
                )
            });
        },

        /**
         * Show control to change authentication data
         */
        $showAuthDataChangeControl: function () {
            var self = this;

            this.Loader.show();

            Authentication.getChangeAuthenticationControl(
                this.getAttribute('authPluginId')
            ).then(function(control) {
                require([control], function(ChangeAuthControl) {
                    new ChangeAuthControl({
                        events: {
                            onSubmit: function(authData) {
                                console.log(authData);
                            }
                        }
                    }).inject(
                        self.$Elm.getElement(
                            '.step-3 .pcsg-gpm-auth-recovery-step-content'
                        )
                    );

                    self.Loader.hide();
                });
            });
        },

        /**
         * Get recovery code
         *
         * @return {String} - the recovery code
         */
        $getRecoveryCode: function () {
            var inputs = this.$Elm.getElement('.pcsg-gpm-auth-recovery-code-input').getElements('input');
            var code   = '';

            for (var i = 0, len = inputs.length; i < len; i++) {
                code += inputs[i].value;
            }

            return code;
        }
    });
});
