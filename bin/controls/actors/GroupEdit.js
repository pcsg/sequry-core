/**
 * Settings for a Sequry Group
 *
 * @module package/sequry/core/bin/controls/actors/GroupEdit
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded
 * @event onSuccess
 */
define('package/sequry/core/bin/controls/actors/GroupEdit', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',

    'Locale',
    'Mustache',

    'package/sequry/core/bin/Actors',
    'package/sequry/core/bin/controls/actors/Select',
    'package/sequry/core/bin/Authentication',

    'text!package/sequry/core/bin/controls/actors/GroupEdit.html',
    'css!package/sequry/core/bin/controls/actors/GroupEdit.css'

], function (QUIControl, QUIButton, QUIConfirm, QUILocale, Mustache, Actors, ActorSelect,
             Authentication, template) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/actors/GroupEdit',

        Binds: [
            '$onInject',
            '$addSecurityClass',
            '$removeSecurityClass',
            '$buildGroupAdminSelect',
            '$showWarning',
            '$removeWarning'
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
            this.$GroupAdminSelect       = null;
            this.$activeSecurityClassIds = [];
            this.$noEventItemChange      = false;
            this.$securityClassBtns      = [];
            this.$WarningElm             = null;
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
                        headerSecurityClasses: QUILocale.get(lg, lg_prefix + 'headerSecurityClasses'),
                        securityclasses      : QUILocale.get(lg, lg_prefix + 'securityclasses'),
                        headerGroupAdmins    : QUILocale.get(lg, lg_prefix + 'headerGroupAdmins'),
                        groupAdminDesc       : QUILocale.get(lg, lg_prefix + 'groupAdminDesc')
                    })
                });

                var SecurityClassesElm = self.$Elm.getElement('.pcsg-gpm-group-edit-securityclasses');

                if (!Object.getLength(SecurityClasses)) {
                    self.$showWarning(QUILocale.get(lg, 'actors.groupedit.no_securityclasses'));
                } else if (!Group.securityClassIds.length) {
                    self.$showWarning(QUILocale.get(lg, 'actors.groupedit.no_group_securityclass'));
                }

                var FuncOnSwitchBtnClick = function (Btn) {
                    var securityClassId = Btn.getAttribute('securityClassId');

                    switch (Btn.getAttribute('action')) {
                        case 'add':
                            self.$addSecurityClass(securityClassId).then(function (success) {
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

                                self.$activeSecurityClassIds.push(securityClassId);
                                self.$buildGroupAdminSelect();
                            });
                            break;

                        case 'remove':
                            self.$removeSecurityClass(securityClassId).then(function (success) {
                                if (!success) {
                                    return;
                                }

                                Btn.setAttributes({
                                    text     : QUILocale.get(lg, 'actors.groupedit.securityclass.btn.add'),
                                    textimage: 'fa fa-plus-square',
                                    action   : 'add'
                                });

                                self.$activeSecurityClassIds.erase(securityClassId);
                                self.$buildGroupAdminSelect();
                            });
                            break;
                    }
                };

                // SecurityClasses
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

                    var btnText, btnIcon, btnAction, btnAlt;
                    var groupEligible = self.$Group.securityClassEligibility[securityClassId];

                    if (Group.securityClassIds.contains(securityClassId)) {
                        btnText   = QUILocale.get(lg, 'actors.groupedit.securityclass.btn.remove');
                        btnIcon   = 'fa fa-minus-square';
                        btnAction = 'remove';
                        btnAlt    = QUILocale.get(lg, 'actors.groupedit.securityclass.btn.remove_alt');

                        self.$activeSecurityClassIds.push(securityClassId);
                    } else {
                        btnText   = QUILocale.get(lg, 'actors.groupedit.securityclass.btn.add');
                        btnIcon   = 'fa fa-plus-square';
                        btnAction = 'add';

                        if (groupEligible) {
                            btnAlt = QUILocale.get(lg, 'actors.groupedit.securityclass.btn.add_alt');
                        } else {
                            btnAlt = QUILocale.get(lg, 'actors.groupedit.securityclass.btn.add_alt_not_eligible');
                        }
                    }

                    var SwitchBtn = new QUIButton({
                        text           : btnText,
                        textimage      : btnIcon,
                        alt            : btnAlt,
                        title          : btnAlt,
                        action         : btnAction,
                        securityClassId: securityClassId,
                        canRemove      : USER.isSU,
                        groupEligible  : groupEligible,
                        events         : {
                            onClick: FuncOnSwitchBtnClick
                        }
                    }).inject(
                        SecClassElm.getElement('.pcsg-gpm-actors-groupedit-securityclass-btn')
                    );

                    if (!groupEligible) {
                        SwitchBtn.disable();
                    }

                    self.$securityClassBtns.push(SwitchBtn);
                }

                // group admins
                self.$buildGroupAdminSelect();

                self.fireEvent('loaded');
            });
        },

        /**
         * Show warning text
         *
         * @param {String} msg
         */
        $showWarning: function (msg) {
            this.$removeWarning();

            this.$WarningElm = new Element('div', {
                'class': 'pcsg-gpm-password-warning',
                html   : '<span>' + msg + '</span>'
            }).inject(
                this.$Elm.getElement('.pcsg-gpm-group-edit-securityclasses'),
                'top'
            );
        },

        /**
         * Hide current warning text
         */
        $removeWarning: function () {
            if (this.$WarningElm) {
                this.$WarningElm.destroy();
            }
        },

        /**
         * Build ActorSelect to select Group administration Users
         */
        $buildGroupAdminSelect: function () {
            var self           = this;
            var filterActorIds = [];

            if (this.$GroupAdminSelect) {
                this.$GroupAdminSelect.destroy();
            }

            this.$GroupAdminSelect = new ActorSelect({
                popupInfo        : QUILocale.get(lg,
                    'controls.actors.groupselect.adminselect.info', {
                        groupName: this.$Group.name,
                        groupId  : this.$Group.id
                    }
                ),
                securityClassIds : this.$activeSecurityClassIds,
                actorType        : 'users',  // "users", "groups", "all"
                showEligibleOnly : true,  // show eligible only or all
                selectedActorType: 'users' // pre-selected actor type
            }).inject(
                this.$Elm.getElement('.pcsg-gpm-group-edit-groupamdmins')
            );

            var enableSecurityClassBtns = function () {
                self.$securityClassBtns.each(function (Btn) {
                    if (Btn.getAttribute('groupEligible')) {
                        Btn.enable();
                    }
                });
            };

            var disableSecurityClassBtns = function () {
                self.$securityClassBtns.each(function (Btn) {
                    Btn.disable();
                });
            };

            this.$GroupAdminSelect.addEvents({
                onAddItem   : function (Control, userId, Item) {
                    if (self.$noEventItemChange) {
                        console.log("set false");
                        self.$noEventItemChange = false;
                        return;
                    }

                    var realUserId = parseInt(userId.substring(1));

                    if (self.$Group.groupAdminUserIds.contains(realUserId)) {
                        return;
                    }

                    self.$GroupAdminSelect.Loader.show();

                    Actors.addGroupAdminUser(self.$Group.id, realUserId).then(function (success) {
                        self.$GroupAdminSelect.Loader.hide();

                        if (!success) {
                            self.$noEventItemChange = true;
                            Item.destroy();
                        } else {
                            self.$Group.groupAdminUserIds.push(realUserId);

                            filterActorIds.push(userId);
                            self.$GroupAdminSelect.setAttribute('filterActorIds', filterActorIds);
                        }

                        if (!self.$Group.groupAdminUserIds.length) {
                            disableSecurityClassBtns();
                            self.$showWarning(QUILocale.get(lg, 'actors.groupedit.no_group_admins'));
                        } else {
                            enableSecurityClassBtns();
                            self.$removeWarning();
                        }
                    });
                },
                onRemoveItem: function (userId) {
                    if (self.$noEventItemChange) {
                        self.$noEventItemChange = false;
                        return;
                    }

                    var realUserId = parseInt(userId.substring(1));

                    if (!self.$Group.groupAdminUserIds.contains(realUserId)) {
                        return;
                    }

                    self.$GroupAdminSelect.Loader.show();

                    Actors.removeGroupAdminUser(self.$Group.id, realUserId).then(function (success) {
                        self.$GroupAdminSelect.Loader.hide();

                        if (!success) {
                            self.$noEventItemChange = true;
                            self.$GroupAdminSelect.addItem(userId);
                        } else {
                            self.$Group.groupAdminUserIds.erase(realUserId);

                            filterActorIds.erase(userId);
                            self.$GroupAdminSelect.setAttribute('filterActorIds', filterActorIds);
                        }

                        if (!self.$Group.groupAdminUserIds.length) {
                            disableSecurityClassBtns();
                            self.$showWarning(QUILocale.get(lg, 'actors.groupedit.no_group_admins'));
                        } else {
                            enableSecurityClassBtns();
                            self.$removeWarning();
                        }
                    });
                }
            });

            if (!this.$Group.groupAdminUserIds.length) {
                disableSecurityClassBtns();
                this.$showWarning(QUILocale.get(lg, 'actors.groupedit.no_group_admins'));
            }

            for (var i = 0, len = this.$Group.groupAdminUserIds.length; i < len; i++) {
                this.$noEventItemChange = true;
                this.$GroupAdminSelect.addItem('u' + this.$Group.groupAdminUserIds[i]);
                filterActorIds.push('u' + this.$Group.groupAdminUserIds[i]);
            }

            this.$GroupAdminSelect.setAttribute('filterActorIds', filterActorIds);
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

            return new Promise(function (resolve) {
                var Confirm = new QUIConfirm({
                    icon       : 'fa fa-exclamation-triangle',
                    texticon   : 'fa fa-exclamation-triangle',
                    text       : QUILocale.get(lg, 'actors.groupedit.remove.securityclass.title'),
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
                            ).then(function (success) {
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
            ).then(function (AuthData) {
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
