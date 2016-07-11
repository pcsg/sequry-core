/**
 * Password Panel
 *
 * Edit and Share password objects
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/Panel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @require qui/controls/buttons/Select
 * @require qui/controls/buttons/Switch
 * @require qui/controls/windows/Confirm
 * @require qui/controls/windows/Popup
 * @require qui/controls/buttons/Seperator
 * @require controls/projects/project/media/Input
 * @require package/pcsg/grouppasswordmanager/bin/controls/password/MachineTypeSelect
 * @require package/pcsg/grouppasswordmanager/bin/controls/password/Translator
 * @require Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/Panel.css
 *
 * @event onEditMachine [machineId] - fires after machine attributes are saved successfully
 * @event onDeleteMachine [machineId] - fires when this machine is deleted successfully
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Select',
    'qui/controls/buttons/Switch',
    'qui/controls/windows/Confirm',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Seperator',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

    'Ajax',
    'Locale'

    //'css!package/pcsg/grouppasswordmanager/bin/controls/password/Panel.css'

], function (QUI, QUIPanel, QUIButton, QUISelect, QUISwitch, QUIConfirm,
             QUIPopup, QUIButtonSeparator, PasswordHandler, AuthenticationControl,
             Ajax, QUILocale) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager';
    var Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/Panel',

        Binds: [
            '$onCreate',
            'refresh',
            'showEdit',
            'showShare',
            '$onDestroy',
            'save'
        ],

        options: {
            icon      : 'fa fa-unlock',
            passwordId: false // ID of password object
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onCreate : this.$onCreate,
                onDestroy: this.$onDestroy
            });

            this.$PasswordData  = null;
            this.$AuthData      = null;
            this.$ActiveControl = null;
        },

        /**
         * event on DOMElement creation
         */
        $onCreate: function () {
            var self = this;

            this.$Content = this.getContent();

            // password edit
            this.$EditBtn = new QUIButton({
                icon  : 'fa fa-edit',
                text  : QUILocale.get(lg, 'gpm.password.panel.btn.edit'),
                events: {
                    onClick: self.showEdit
                }
            });

            // settings btn
            this.addCategory(this.$EditBtn);

            // password share
            this.addCategory(new QUIButton({
                icon  : 'fa fa-share-alt',
                text  : QUILocale.get(lg, 'gpm.password.panel.btn.share'),
                events: {
                    onClick: self.showShare
                }
            }));

            // Save Button
            this.$SaveBtn = new QUIButton({
                textimage: 'fa fa-save',
                text     : QUILocale.get(lg, 'gpm.password.btn.save.text'),
                title    : QUILocale.get(lg, 'gpm.password.btn.save'),
                alt      : QUILocale.get(lg, 'gpm.password.btn.save'),
                events   : {
                    onClick: function () {
                        if (self.$ActiveControl) {
                            self.$ActiveControl.save();
                        }
                    }
                }
            });

            this.addButton(this.$SaveBtn);

            this.$SaveBtn.disable();

            Passwords.getSecurityClassId(
                this.getAttribute('passwordId')
            ).then(
                function (securityClassId) {
                    var AuthControl = new AuthenticationControl({
                        securityClassId: securityClassId,
                        events         : {
                            onSubmit: function (AuthData) {
                                self.$AuthData = AuthData;
                                self.$SaveBtn.enable();
                                self.$EditBtn.click();
                                AuthControl.destroy();
                            },
                            onClose : function () {
                                self.destroy();
                            },
                            onAbort : function () {
                                self.destroy();
                            }
                        }
                    });

                    AuthControl.open();
                },
                function () {
                    self.destroy();
                }
            );

            //// refresh btn
            //this.addButton(
            //    new QUIButton({
            //        textimage: 'icon-refresh',
            //        text     : QUILocale.get(lg, 'gpm.password.btn.discard.text'),
            //        title    : QUILocale.get(lg, 'gpm.password.btn.discard'),
            //        alt      : QUILocale.get(lg, 'gpm.password.btn.discard'),
            //        events   : {
            //            onClick: self.refresh
            //        }
            //    })
            //);
            //
            //// Delete Button
            //this.addButton(
            //    new QUIButton({
            //        icon  : 'icon-trash',
            //        //text     : QUILocale.get(lg, 'gpm.password.btn.delete.text'),
            //        title : QUILocale.get(lg, 'gpm.password.btn.delete'),
            //        alt   : QUILocale.get(lg, 'gpm.password.btn.delete'),
            //        styles: {
            //            float: 'right'
            //        },
            //        events: {
            //            onClick: self.showDeleteConfirm
            //        }
            //    })
            //);
        },

        $onDestroy: function () {
            if (this.$PasswordData) {
                this.$PasswordData = null;
            }

            if (this.$AuthData) {
                this.$AuthData = null;
            }
        },

        /**
         * Loads password data from database
         */
        refresh: function () {
            // @todo
        },

        /**
         * Show password edit
         */
        showEdit: function () {
            var self = this;

            this.Loader.show();
            this.setContent('');

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/password/Edit'
            ], function (PasswordEdit) {
                self.$PasswordEdit = new PasswordEdit({
                    passwordId : self.getAttribute('passwordId'),
                    AuthData   : self.$AuthData
                });

                self.$PasswordEdit.addEvents({
                    onLoaded: function () {
                        self.Loader.hide();
                        self.$SaveBtn.enable();
                        self.$ActiveControl = self.$PasswordEdit;
                    }
                });

                self.$PasswordEdit.inject(self.getContent());
            });
        },

        /**
         * Show password share
         */
        showShare: function () {
            var self = this;

            this.Loader.show();
            this.setContent('');

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/password/Share'
            ], function (PasswordShare) {
                self.$PasswordShare = new PasswordShare({
                    passwordId : self.getAttribute('passwordId'),
                    AuthData   : self.$AuthData,
                    ParentPanel: self
                });

                self.$PasswordShare.addEvents({
                    onLoaded: function () {
                        self.Loader.hide();
                        self.$SaveBtn.enable();
                        self.$ActiveControl = self.$PasswordShare;
                    }
                });

                self.$PasswordShare.inject(self.getContent());
            });
        },

        /**
         * Changes Panel icon and title to indicate that an unsaved change has been made
         */
        $showChangeInfo: function () {
            this.setAttribute('icon', 'icon-warning-sign');
            this.setAttribute(
                'title',
                this.$title + " " + QUILocale.get(lg, 'gpm.password.title.changes')
            );

            this.$refresh();
        },

        /**
         * Hides the change info in Panel header
         */
        $hideChangeInfo: function () {
            this.setAttribute('icon', 'icon-cog');
            this.setAttribute('title', this.$title);
            this.$refresh();
        },

        /**
         * Shows info layer, is panel is opened by another user
         */
        $showEditInfo: function () {
            var self   = this,
                userId = this.$Machine.is_edited_by;

            if (!this.$checkEdit) {
                return;
            }

            if (userId == USER.id) {
                return;
            }

            require(['Users'], function (UserManager) {
                UserManager.get(userId).load(function (User) {
                    var EditInfo = new Element('div', {
                        'class': 'hklused-machines-machinepanel-edit',
                        html   : '<div class="hklused-machines-machinepanel-edit-info">' +
                        QUILocale.get(lg, 'gpm.password.editinfo.text', {
                            'editUserName': User.getName()
                        }) +
                        '</div>' +
                        '<div class="hklused-machines-machinepanel-edit-btn"></div>'
                    }).inject(self.$Elm);

                    var BtnElm = EditInfo.getElement(
                        '.hklused-machines-machinepanel-edit-btn'
                    );

                    new QUIButton({
                        textimage: 'icon-edit',
                        text     : QUILocale.get(lg, 'gpm.password.editinfo.btn.edit.text'),
                        alt      : QUILocale.get(lg, 'gpm.password.editinfo.btn.edit'),
                        title    : QUILocale.get(lg, 'gpm.password.editinfo.btn.edit'),
                        styles   : {
                            marginRight: 10
                        },
                        events   : {
                            onClick: function () {
                                EditInfo.destroy();
                                self.$checkEdit = false;
                                self.refresh();
                            }
                        }
                    }).inject(BtnElm);

                    new QUIButton({
                        textimage: 'icon-refresh',
                        text     : QUILocale.get(lg, 'gpm.password.editinfo.btn.refresh.text'),
                        alt      : QUILocale.get(lg, 'gpm.password.editinfo.btn.refresh'),
                        title    : QUILocale.get(lg, 'gpm.password.editinfo.btn.refresh'),
                        events   : {
                            onClick: function () {
                                EditInfo.destroy();
                                self.refresh();
                            }
                        }
                    }).inject(BtnElm);
                });
            });
        }
    });

});