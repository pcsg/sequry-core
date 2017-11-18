/**
 * Recovery process for a single authentication plugin
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded [this]
 * @event onFinish [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',

    'Locale',
    'Mustache',
    'Ajax',

    'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/CodePopup',
    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'text!package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery.css'

], function (QUI, QUIControl, QUILoader, QUIButton, QUILocale, Mustache,
             QUIAjax, CodePopup, Authentication, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery',

        Binds: [
            '$onInject',
            '$buildContent',
            '$checkStep3',
            '$showAuthDataChangeControl',
            '$changeAuthData'
        ],

        options: {
            authPluginId: false // ID of the authentication plugin whose credentials should be recovered
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader             = new QUILoader();
            this.$AuthPlugin        = {};
            this.$CheckBtn          = null;
            this.$SendTokenBtn      = null;
            this.$TokenInput        = null;
            this.$recoveryCodeId    = null;
            this.$ChangeAuthControl = null;
        },

        /**
         * event : oninject
         */
        $onInject: function () {
            var self = this;

            this.$Elm.addClass('pcsg-gpm-auth-recovery');

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            var authPluginId = this.getAttribute('authPluginId');

            Promise.all([
                Authentication.getAuthPluginInfo(authPluginId),
                Authentication.getRecoveryCodeId(authPluginId)
            ]).then(function (result) {
                self.Loader.hide();
                self.$AuthPlugin     = result[0];
                self.$recoveryCodeId = result[1];

                self.$buildContent();
                self.fireEvent('loaded', [self]);
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

            this.$TokenInput = Step1Content.getElement('input');
            this.$TokenInput.addEvent('change', this.$checkStep3);

            var ErrorMsgElm = new Element('span', {
                'class': 'pcsg-gpm-auth-recovery-error'
            }).inject(Step1Content, 'top');

            this.$SendTokenBtn = new QUIButton({
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
                                self.$TokenInput.disabled = false;
                                self.$TokenInput.focus();
                            } else {
                                self.$TokenInput.disabled = true;
                            }
                        }, function (e) {
                            // display error message
                            ErrorMsgElm.set('html', e.getMessage());
                            ErrorMsgElm.removeClass('pcsg-gpm__hidden');

                            FuncChangeBtn();
                            self.$TokenInput.disabled = true;
                        });
                    }
                }
            }).inject(Step1Content, 'top');

            // step 2 (enter recovery code)
            var Step2Content = this.$Elm.getElement(
                '.step-2 .pcsg-gpm-auth-recovery-step-content'
            );

            new Element('div', {
                'class': 'pcsg-gpm-password-hint',
                html   : QUILocale.get(lg, 'controls.auth.recovery.code_info', {
                    codeId: this.$recoveryCodeId
                })
            }).inject(Step2Content);

            var FuncOnCodeInput = function (event) {
                var Elm = event.target;

                if (Elm.value.length < 5) {
                    self.$checkStep3();
                    return;
                }

                var id = parseInt(Elm.getProperty('data-id'));

                if (id === 5) {
                    self.$checkStep3();
                    return;
                }

                Step2Content.getElement('input[data-id="' + (id + 1) + '"]').focus();
            };

            for (var i = 0; i < 5; i++) {
                new Element('input', {
                    'class'  : 'pcsg-gpm-auth-recovery-code-input',
                    'data-id': i + 1,
                    type     : 'text',
                    maxlength: 5,
                    events   : {
                        input: FuncOnCodeInput
                    }
                }).inject(Step2Content);
            }

            // step 3 (check data and change authentication information)
            this.$CheckBtn = new QUIButton({
                text     : QUILocale.get(lg, 'controls.auth.recovery.btn.check_data'),
                textimage: 'fa fa-check-square-o',
                disabled : true,
                events   : {
                    onClick: function (Btn) {
                        Btn.disable();
                        Btn.setAttribute('textimage', 'fa fa-spin fa-spinner');

                        self.$validateRecoveryData(
                            self.$getRecoveryCode(),
                            self.$TokenInput.value
                        ).then(function (isValid) {
                            if (isValid) {
                                Btn.destroy();
                                self.$showAuthDataChangeControl();
                            }
                        }, function (e) {
                            Btn.enable();
                            Btn.setAttribute('textimage', 'fa fa-check-square-o');

                            // @todo Exception message anzeigen
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
         * Check all requirements for step 3
         */
        $checkStep3: function () {
            this.$CheckBtn.disable();

            // check step 1
            if (this.$TokenInput.value.trim() === '') {
                return;
            }

            // check step 2
            if (this.$getRecoveryCode().length < 25) {
                return;
            }

            this.$CheckBtn.enable();
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

            // disable token and recovery code inputs
            this.$TokenInput.disabled = true;
            this.$SendTokenBtn.disable();
            this.$Elm.getElements('.pcsg-gpm-auth-recovery-code-input').disabled = true;

            // submit Func
            var FuncAuthDataSubmit = function () {
                self.$ChangeAuthControl.disable();
                SubmitBtn.disable();

                self.Loader.show();

                self.$changeAuthData(self.$ChangeAuthControl.getAuthData()).then(function (NewRecoveryCode) {
                    self.Loader.hide();

                    if (!NewRecoveryCode) {
                        self.$ChangeAuthControl.enable();
                        SubmitBtn.enable();
                        return;
                    }

                    new CodePopup({
                        RecoveryCodeData: NewRecoveryCode,
                        events: {
                            onClose: function() {
                                self.fireEvent('finish', [self]);
                            }
                        }
                    }).open();
                }, function () {
                    self.Loader.hide();
                    self.$ChangeAuthControl.enable();
                    SubmitBtn.enable();
                });
            };

            // submit btn
            var SubmitElm = this.$Elm.getElement('.pcsg-gpm-auth-recovery-submit');

            SubmitElm.getParent('tr').removeClass('pcsg-gpm__hidden');

            var SubmitBtn = new QUIButton({
                text     : QUILocale.get(lg, 'controls.auth.recovery.btn.submit'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: FuncAuthDataSubmit
                }
            }).inject(SubmitElm);

            // load ChangeAuth control of the Authentication plugin
            this.Loader.show();

            Authentication.getChangeAuthenticationControl(
                this.getAttribute('authPluginId')
            ).then(function (control) {
                require([control], function (ChangeAuthControl) {
                    self.$ChangeAuthControl = new ChangeAuthControl({
                        events: {
                            onSubmit: FuncAuthDataSubmit
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
         * Change authentication information for the current Authentication plugin
         *
         * @param {String} newAuthData
         * @return {Promise}
         */
        $changeAuthData: function (newAuthData) {
            var self = this;


            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_pcsg_grouppasswordmanager_ajax_auth_recovery_changeAuthData', resolve, {
                    'package'   : 'pcsg/grouppasswordmanager',
                    authPluginId: self.getAttribute('authPluginId'),
                    newAuthData : newAuthData,
                    onError     : reject
                })
            });
        },

        /**
         * Get recovery code
         *
         * @return {String} - the recovery code
         */
        $getRecoveryCode: function () {
            var inputs = this.$Elm.getElements('.pcsg-gpm-auth-recovery-code-input');
            var code   = '';

            for (var i = 0, len = inputs.length; i < len; i++) {
                code += inputs[i].value;
            }

            return code;
        }
    });
});
