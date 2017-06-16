/**
 * Control for editing a password manager groupd
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

    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',

    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Actors',
    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit.css'

], function (QUIControl, QUIButton, QUIConfirm, QUILocale, Mustache, Actors,
             Authentication, QUIAjax, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit',

        Binds: [
            '$onInject',
            '$addSecurityClass',
            '$removeSecurityClass'
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
            this.$SecurityClasses        = {};
            this.$Group                  = {};
            this.$groupId                = false;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self      = this,
                lg_prefix = 'actors.groups.edit.template.';

            this.$groupId = this.$Elm.getParent('form').get('name').split('-')[2];

            Promise.all([
                Actors.getActor(this.$groupId, 'group'),
                Authentication.getSecurityClasses()
            ]).then(function (result) {
                var Group           = result[0];
                var SecurityClasses = result[1];

                self.$Group           = Group;
                self.$SecurityClasses = SecurityClasses;

                self.$Elm.set({
                    'class': 'pcsg-gpm-group-edit',
                    html   : Mustache.render(template, {
                        basicData      : QUILocale.get(lg, lg_prefix + 'basicData'),
                        securityclasses: QUILocale.get(lg, lg_prefix + 'securityclasses')
                    })
                });

                var SecurityClassesElm = self.$Elm.getElement('.pcsg-gpm-group-edit-securityclasses');

                if (!Group.sessionUserInGroup) {
                    self.$canEditGroup = false;

                    self.$NoEditWarnElm = new Element('div', {
                        'class': 'pcsg-gpm-password-error',
                        html   : '<span>' +
                        QUILocale.get(lg, 'actors.groupedit.not.in.group') +
                        '</span>'
                    }).inject(SecurityClassesElm);

                    self.fireEvent('loaded');
                    return;
                }

                if (!Group.securityClassIds.length) {
                    new Element('div', {
                        'class': 'pcsg-gpm-password-warning',
                        html   : '<span>' +
                        QUILocale.get(lg, 'actors.groupedit.no.securityclass') +
                        '</span>'
                    }).inject(
                        SecurityClassesElm,
                        'top'
                    );
                }

                var FuncOnSwitchBtnClick = function (Btn) {
                    switch (Btn.getAttribute('action')) {
                        case 'add':
                            self.$addSecurityClass(
                                Btn.getAttribute('securityClassId')
                            ).then(function (success) {
                                if (!success) {
                                    return;
                                }

                                Btn.setAttributes({
                                    text     : QUILocale.get(lg, 'actors.groupedit.securityclass.btn.remove'),
                                    textimage: 'fa fa-minus-square',
                                    action   : 'remove'
                                });

                                if (!Btn.getAttribute('canRemove')) {
                                    Btn.disable();
                                }
                            });
                            break;

                        case 'remove':
                            self.$removeSecurityClass(
                                Btn.getAttribute('securityClassId')
                            ).then(function (success) {
                                if (!success) {
                                    return;
                                }

                                Btn.setAttributes({
                                    text     : QUILocale.get(lg, 'actors.groupedit.securityclass.btn.add'),
                                    textimage: 'fa fa-plus-square',
                                    action   : 'add'
                                });
                            });
                            break;
                    }
                };

                for (var securityClassId in SecurityClasses) {
                    if (!SecurityClasses.hasOwnProperty(securityClassId)) {
                        continue;
                    }

                    var SecurityClass = SecurityClasses[securityClassId];

                    var SecClassElm = new Element('div', {
                        'class': 'pcsg-gpm-actors-groupedit-securityclass',
                        html   : '<div class="pcsg-gpm-actors-groupedit-securityclass-info">' +
                        '<span class="pcsg-gpm-actors-groupedit-securityclass-title">' +
                        SecurityClass.title +
                        '</span>' +
                        '<span class="pcsg-gpm-actors-groupedit-securityclass-description">' +
                        SecurityClass.description +
                        '</span>' +
                        '</div>' +
                        '<div class="pcsg-gpm-actors-groupedit-securityclass-btn"></div>'
                    }).inject(SecurityClassesElm);

                    var btnText, btnIcon, btnAction;
                    var disableBtn = false;

                    if (Group.securityClassIds.contains(securityClassId)) {
                        btnText   = QUILocale.get(lg, 'actors.groupedit.securityclass.btn.remove');
                        btnIcon   = 'fa fa-minus-square';
                        btnAction = 'remove';
                    } else {
                        btnText    = QUILocale.get(lg, 'actors.groupedit.securityclass.btn.add');
                        btnIcon    = 'fa fa-plus-square';
                        btnAction  = 'add';
                        //disableBtn = true;
                    }

                    var SwitchBtn = new QUIButton({
                        text           : btnText,
                        textimage      : btnIcon,
                        action         : btnAction,
                        securityClassId: securityClassId,
                        canRemove      : USER.isSU,
                        events         : {
                            onClick: FuncOnSwitchBtnClick
                        }
                    }).inject(
                        SecClassElm.getElement('.pcsg-gpm-actors-groupedit-securityclass-btn')
                    );

                    if (disableBtn) {
                        SwitchBtn.disable();
                    }
                }

                self.fireEvent('loaded');
                //self.$currentSecurityClassId = Actor.securityClassId;
                //self.$SecurityClassSelect.setValue(Actor.securityClassId);
            });
        },

        /**
         * Add security class to group
         *
         * @param {number} securityClassId
         * @return {Promise}
         */
        $addSecurityClass: function (securityClassId) {
            return Actors.addGroupSecurityClass(
                this.$groupId,
                securityClassId
            );
        },

        /**
         * Remove security class from group (SU only!)
         *
         * @param {number} securityClassId
         * @return {Promise}
         */
        $removeSecurityClass: function (securityClassId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                var Confirm = new QUIConfirm({
                    icon       : 'fa fa-exclamation-triangle',
                    texticon   : 'fa fa-exclamation-triangle',
                    title      : QUILocale.get(lg, 'actors.groupedit.remove.securityclass.title'),
                    information: QUILocale.get(lg, 'actors.groupedit.remove.securityclass.information', {
                        securityClassId   : securityClassId,
                        securityClassTitle: self.$SecurityClasses[securityClassId].title,
                        groupId           : self.$Group.id,
                        groupName         : self.$Group.name
                    }),
                    events     : {
                        onSubmit: function () {
                            Confirm.Loader.show();

                            Actors.removeGroupSecurityClass(
                                self.$groupId,
                                securityClassId
                            ).then(function(success) {
                                Confirm.close();
                                resolve(success);
                            });
                        }
                    }
                });

                Confirm.open();
            });
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
                    this.$groupId,
                    this.$SecurityClassSelect.getValue()
                ).then(function () {
                    self.fireEvent('success');
                });

                return;
            }

            Authentication.securityClassAuth(
                this.$currentSecurityClassId
            ).then(function(AuthData) {
                Actors.setGroupSecurityClass(
                    self.$groupId,
                    self.$SecurityClassSelect.getValue(),
                    AuthData
                ).then(function () {
                    self.fireEvent('success');
                });
            });
        }
    });
});
