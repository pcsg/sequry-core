/**
 * Control for editing a security class
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit.css
 *
 * @event onLoaded
 * @event onSuccess
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/classes/Actors',
    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit.css'

], function (QUI, QUIControl, QUILocale, Mustache, ActorHandler, AuthenticationHandler,
             SecurityClassSelect, AuthenticationControl, QUIAjax, template) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthenticationHandler(),
        Actors         = new ActorHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit',

        Binds: [
            '$onInject'
        ],

        options: {
            groupId: false // id of group
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$currentSecurityClassId = false;
            this.$canEditGroup           = true;
            this.$NoEditWarnElm          = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self      = this,
                lg_prefix = 'actors.groups.edit.template.';

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-group-edit',
                html   : Mustache.render(template, {
                    title        : QUILocale.get(lg, lg_prefix + 'title'),
                    basicData    : QUILocale.get(lg, lg_prefix + 'basicData'),
                    securityclass: QUILocale.get(lg, lg_prefix + 'securityclass')
                })
            });

            this.$SecurityClassSelect = new SecurityClassSelect().inject(
                this.$Elm.getElement('.pcsg-gpm-group-edit-securityclass')
            );

            Actors.getActor(
                this.getAttribute('groupId'),
                'group'
            ).then(function (Actor) {
                self.fireEvent('loaded');

                if (!Actor.sessionUserInGroup) {
                    self.$SecurityClassSelect.disable();
                    self.$canEditGroup = false;

                    self.$NoEditWarnElm = new Element('div', {
                        'class': 'pcsg-gpm-group-edit-error',
                        html   : '<span>' +
                        QUILocale.get(lg, 'controls.groupedit.not.in.group') +
                        '</span>'
                    }).inject(
                        self.$Elm.getElement('.pcsg-gpm-group-edit-securityclass'),
                        'top'
                    );
                }

                if (!Actor.securityClassId) {
                    new Element('div', {
                        'class': 'pcsg-gpm-group-edit-warning',
                        html   : '<span>' +
                        QUILocale.get(lg, 'controls.groupedit.no.securityclass') +
                        '</span>'
                    }).inject(
                        self.$Elm.getElement('.pcsg-gpm-group-edit-securityclass'),
                        'top'
                    );

                    return;
                }

                self.$currentSecurityClassId = Actor.securityClassId;
                self.$SecurityClassSelect.setValue(Actor.securityClassId);
            });

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            // @todo
        },

        /**
         * Edit group
         *
         * @returns {void}
         */
        submit: function () {
            var self = this;

            if (!this.$canEditGroup) {
                this.$NoEditWarnElm.highlight('#F2D4CE', '#F22633');
                return;
            }

            if (!this.$currentSecurityClassId) {
                Actors.setGroupSecurityClass(
                    this.getAttribute('groupId'),
                    this.$SecurityClassSelect.getValue()
                ).then(function () {
                    self.fireEvent('finish');
                });

                return;
            }

            var AuthControl = new AuthenticationControl({
                securityClassId: this.$currentSecurityClassId,
                events         : {
                    onSubmit: function (AuthData) {
                        Actors.setGroupSecurityClass(
                            self.getAttribute('groupId'),
                            self.$SecurityClassSelect.getValue(),
                            AuthData
                        ).then(function () {
                            self.fireEvent('finish');
                            AuthData = null;
                            AuthControl.destroy();
                        });
                    }
                }
            });

            AuthControl.open();
        }
    });
});
