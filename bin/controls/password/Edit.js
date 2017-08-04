/**
 * Control for editing a password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/Edit
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.css
 *
 * @event onLoaded
 * @event onAuthAbort - on user authentication abort
 * @event onClose (this) - if password is closed
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/Edit', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'package/pcsg/grouppasswordmanager/bin/Passwords',
    'package/pcsg/grouppasswordmanager/bin/Categories',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/SelectSlider',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select',


    'text!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.html'
    //'css!package/pcsg/grouppasswordmanager/bin/controls/password/Edit.css'

], function (QUI, QUIControl, QUILoader, QUILocale, Mustache, Authentication, Passwords,
             Categories, SecurityClassSelect, ActorSelect, PasswordContent,
             CategorySelect, CategorySelectPrivate, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Edit',

        Binds: [
            '$onInject',
            '$onDestroy',
            '$save',
            '$onPasswordDataLoaded',
            '$setPrivateCategories',
            '$onSecurityClassChange',
            '$onOwnerChange',
            '$showSetOwnerInformation'
        ],

        options: {
            passwordId: false  // password ID
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            this.$PasswordData          = null;
            this.$owner                 = false;
            this.$PassContent           = null;
            this.$securityClassId       = false;
            this.$CategorySelect        = null;
            this.$CategorySelectPrivate = null;
            this.$OwnerSelect           = null;
            this.$OwnerSelectElm        = null;
            this.$CurrentOwner          = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var lg_prefix = 'password.create.template.';

            this.$Elm   = this.parent();
            this.Loader = new QUILoader().inject(this.$Elm);

            this.$Elm.set({
                'class': 'pcsg-gpm-password-edit',
                html   : Mustache.render(template, {
                    title                  : QUILocale.get(lg, 'edit.template.title'),
                    basicData              : QUILocale.get(lg, lg_prefix + 'basicData'),
                    securityClass          : QUILocale.get(lg, lg_prefix + 'securityClass'),
                    passwordTitle          : QUILocale.get(lg, lg_prefix + 'passwordTitle'),
                    passwordDescription    : QUILocale.get(lg, lg_prefix + 'passwordDescription'),
                    passwordCategory       : QUILocale.get(lg, lg_prefix + 'passwordCategory'),
                    passwordCategoryPrivate: QUILocale.get(lg,
                        'controls.categories.panel.private.title'
                    ),
                    payload                : QUILocale.get(lg, lg_prefix + 'payload'),
                    passwordPayload        : QUILocale.get(lg, lg_prefix + 'passwordPayload'),
                    payloadWarning         : QUILocale.get(lg, lg_prefix + 'payloadWarning'),
                    extra                  : QUILocale.get(lg, lg_prefix + 'extra'),
                    passwordOwner          : QUILocale.get(lg, lg_prefix + 'passwordOwner')
                })
            });

            this.$CategorySelect = new CategorySelect().inject(
                this.$Elm.getElement(
                    '.pcsg-gpm-password-edit-category'
                )
            );

            this.$CategorySelectPrivate = new CategorySelectPrivate().inject(
                this.$Elm.getElement(
                    '.pcsg-gpm-password-edit-category-private'
                )
            );

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;
            var pwId = this.getAttribute('passwordId');

            Passwords.get(pwId).then(
                function (PasswordData) {
                    if (!PasswordData) {
                        return;
                    }

                    self.$PasswordData    = PasswordData;
                    self.$securityClassId = PasswordData.securityClassId;
                    self.$onPasswordDataLoaded();
                },
                function () {
                    self.fireEvent('close');
                }
            );
        },

        /**
         * Build further controls after password data has been loaded
         */
        $onPasswordDataLoaded: function () {
            var self = this;

            var SecurityClassElm = this.$Elm.getElement(
                'span.pcsg-gpm-security-classes'
            );

            this.$OwnerSelectElm = this.$Elm.getElement(
                'div.pcsg-gpm-password-owner'
            );

            this.$CurrentOwner = {
                id  : this.$PasswordData.ownerId,
                type: this.$PasswordData.ownerType == 1 ? 'user' : 'group'
            };

            this.$SecurityClassSelect = new SecurityClassSelect({
                initialValue: this.$securityClassId,
                events      : {
                    onLoaded: function () {
                        self.$insertData();
                        self.fireEvent('loaded');
                    },
                    onChange: this.$onSecurityClassChange
                }
            }).inject(
                SecurityClassElm
            );

            // if owner is group, show warning
            if (this.$PasswordData.ownerType == 2) {
                new Element('div', {
                    'class': 'pcsg-gpm-password-warning',
                    html   : QUILocale.get(lg, 'password.edit.securityclass.change.warning')
                }).inject(this.$OwnerSelectElm, 'top');
            }
        },

        /**
         * Insert Data to edit form
         */
        $insertData: function () {
            var self = this;

            if (!this.$PasswordData) {
                this.destroy();
            }

            // set security class
            this.$SecurityClassSelect.setValue(this.$PasswordData.securityClassId);

            // set category
            this.$CategorySelect.setValue(this.$PasswordData.categoryIds);
            this.$CategorySelectPrivate.setValue(this.$PasswordData.categoryIdsPrivate);

            // set form values
            this.$Elm.getElement('.pcsg-gpm-password-title').value       = self.$PasswordData.title;
            this.$Elm.getElement('.pcsg-gpm-password-description').value = self.$PasswordData.description;

            var PayloadElm = this.$Elm.getElement('.pcsg-gpm-password-payload');

            PayloadElm.set('html', '');

            this.$PassContent = new PasswordContent({
                type  : self.$PasswordData.dataType,
                events: {
                    onLoaded: function () {
                        self.$PassContent.setData(self.$PasswordData.payload);
                    }
                }
            }).inject(PayloadElm);
        },

        /**
         * Perform certain actions if the selected security class changes:
         *
         * - Check if the currently selected owner is eligible for security class
         *
         * @param {number} securityClassId - security class ID
         */
        $onSecurityClassChange: function (securityClassId) {
            var self = this;

            this.$OwnerSelectElm.set('html', '');

            this.$OwnerSelect = new ActorSelect({
                max            : 1,
                securityClassId: securityClassId,
                events         : {
                    onChange: this.$onOwnerChange
                }
            }).inject(this.$OwnerSelectElm);

            if (!this.$CurrentOwner) {
                this.$showSetOwnerInformation();
                return;
            }

            var ownerValue = this.$CurrentOwner.id;

            if (this.$CurrentOwner.type === 'user') {
                ownerValue = 'u' + ownerValue;
            } else {
                ownerValue = 'g' + ownerValue;
            }

            this.$OwnerSelect.addItem(ownerValue);

            Authentication.isActorEligibleForSecurityClass(
                this.$CurrentOwner.id,
                this.$CurrentOwner.type,
                securityClassId
            ).then(
                function (isEligible) {
                    if (isEligible) {
                        return;
                    }

                    if (self.$OwnerInfoElm) {
                        self.$OwnerInfoElm.destroy();
                    }

                    self.$OwnerInfoElm = new Element('div', {
                        'class': 'pcsg-gpm-password-warning',
                        styles : {
                            marginTop: 10
                        },
                        html   : QUILocale.get(lg, 'password.create.set.owner.not.eligible')
                    }).inject(self.$OwnerSelectElm);
                }
            );
        },

        /**
         * On owner change
         */
        $onOwnerChange: function () {
            var actors = this.$OwnerSelect.getActors();

            if (!actors.length) {
                this.$showSetOwnerInformation();
                this.$CurrentOwner = null;
                return;
            }

            this.$CurrentOwner = actors[0];

            if (this.$OwnerInfoElm) {
                this.$OwnerInfoElm.destroy();
            }
        },

        /**
         * Show info about owner
         */
        $showSetOwnerInformation: function () {
            if (this.$OwnerInfoElm) {
                this.$OwnerInfoElm.destroy();
            }

            this.$OwnerInfoElm = new Element('div', {
                'class': 'pcsg-gpm-password-hint',
                styles : {
                    marginTop: 10
                },
                html   : QUILocale.get(lg, 'password.create.set.owner.info')
            }).inject(this.$OwnerSelectElm);
        },

        /**
         * event: on destroy
         */
        $onDestroy: function () {
            this.$PasswordData = null;
        },

        /**
         * Edit the password
         *
         * @return {Promise}
         */
        submit: function () {
            var self = this;

            var PasswordData = {
                securityClassId   : this.$SecurityClassSelect.getValue(),
                title             : this.$Elm.getElement('.pcsg-gpm-password-title').value,
                description       : this.$Elm.getElement('.pcsg-gpm-password-description').value,
                payload           : this.$PassContent.getData(),
                dataType          : this.$PassContent.getPasswordType(),
                categoryIds       : this.$CategorySelect.getValue(),
                categoryIdsPrivate: this.$CategorySelectPrivate.getValue()
            };

            var actors = this.$OwnerSelect.getActors();

            if (!actors.length) {
                QUI.getMessageHandler(function (MH) {
                    MH.addAttention(
                        QUILocale.get(lg, 'password.create.submit.no.owner.assigned')
                    );
                });

                return Promise.reject();
            }

            PasswordData.owner = actors[0];

            return new Promise(function (resolve, reject) {
                Passwords.editPassword(
                    self.getAttribute('passwordId'),
                    PasswordData
                ).then(
                    function (PasswordData) {
                        if (!PasswordData) {
                            reject();
                            return;
                        }

                        self.$PasswordData = null;
                        //self.$insertData();

                        if (window.PasswordCategories) {
                            window.PasswordCategories.refreshCategories();
                        }

                        resolve();
                    },
                    reject
                );
            });
        }
    });
});
