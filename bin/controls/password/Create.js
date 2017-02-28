/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/Create
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/Create.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/Create.css
 *
 * @event onLoaded
 * @event onFinish
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/Create', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Content',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select',


    'text!package/pcsg/grouppasswordmanager/bin/controls/password/Create.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/password/Create.css'

], function (QUI, QUIControl, QUILocale, Mustache, PasswordHandler,
             SecurityClassSelect, ActorSelect, PasswordTypes, CategorySelect,
             CategorySelectPrivate, template) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager',
        Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Create',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$PasswordData          = null;
            this.$CategorySelect        = null;
            this.$CategorySelectPrivate = null;
            this.$owner                 = false;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'password.create.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-create',
                html   : Mustache.render(template, {
                    title                  : QUILocale.get(lg, lg_prefix + 'title'),
                    basicData              : QUILocale.get(lg, lg_prefix + 'basicData'),
                    securityClass          : QUILocale.get(lg, lg_prefix + 'securityClass'),
                    passwordTitle          : QUILocale.get(lg, lg_prefix + 'passwordTitle'),
                    passwordCategory       : QUILocale.get(lg, lg_prefix + 'passwordCategory'),
                    passwordCategoryPrivate: QUILocale.get(lg, lg_prefix + 'passwordCategoryPrivate'),
                    passwordDescription    : QUILocale.get(lg, lg_prefix + 'passwordDescription'),
                    payload                : QUILocale.get(lg, lg_prefix + 'payload'),
                    passwordPayload        : QUILocale.get(lg, lg_prefix + 'passwordPayload'),
                    payloadWarning         : QUILocale.get(lg, lg_prefix + 'payloadWarning'),
                    extra                  : QUILocale.get(lg, lg_prefix + 'extra'),
                    passwordOwner          : QUILocale.get(lg, lg_prefix + 'passwordOwner'),
                })
            });

            // insert security class select
            var SecurityClassElm = this.$Elm.getElement(
                'span.pcsg-gpm-security-classes'
            );

            var OwnerSelectElm = this.$Elm.getElement(
                'span.pcsg-gpm-password-owner'
            );

            this.$SecurityClassSelect = new SecurityClassSelect({
                events: {
                    onLoaded: function () {
                        self.fireEvent('loaded');
                    },
                    onChange: function (value) {
                        OwnerSelectElm.set('html', '');

                        self.$OwnerSelect = new ActorSelect({
                            max            : 1,
                            securityClassId: value
                        }).inject(OwnerSelectElm);

                        self.$OwnerSelect.addItem('u' + USER.id);
                    }
                }
            }).inject(
                SecurityClassElm
            );

            // password types
            this.$PasswordTypes = new PasswordTypes({
                mode: 'edit'
            }).inject(
                this.$Elm.getElement(
                    'div.pcsg-gpm-password-payload'
                )
            );

            this.$CategorySelect = new CategorySelect().inject(
                this.$Elm.getElement(
                    '.pcsg-gpm-password-create-category'
                )
            );

            this.$CategorySelectPrivate = new CategorySelectPrivate().inject(
                this.$Elm.getElement(
                    '.pcsg-gpm-password-create-category-private'
                )
            );

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            this.$Elm.getElement('input.pcsg-gpm-password-title').focus();
        },

        /**
         * Create the field
         */
        submit: function () {
            var self = this;

            this.$PasswordData = {
                securityClassId  : this.$SecurityClassSelect.getValue(),
                title            : this.$Elm.getElement('input.pcsg-gpm-password-title').value,
                description      : this.$Elm.getElement('input.pcsg-gpm-password-description').value,
                dataType         : this.$PasswordTypes.getPasswordType(),
                payload          : this.$PasswordTypes.getData(),
                categoryIds      : this.$CategorySelect.getValue(),
                categoryIdPrivate: this.$CategorySelectPrivate.getValue()
            };

            var actors = this.$OwnerSelect.getActors();

            if (!actors.length) {
                QUI.getMessageHandler(function (MH) {
                    MH.addAttention(
                        QUILocale.get(lg, 'password.create.submit.no.owner.assigned')
                    )
                });

                return;
            }

            this.$PasswordData.owner = actors[0];

            Passwords.createPassword(
                self.$PasswordData
            ).then(
                function () {
                    self.$PasswordData = null;
                    self.fireEvent('finish');
                },
                function () {
                    // @todo
                }
            );
        }
    });
});
