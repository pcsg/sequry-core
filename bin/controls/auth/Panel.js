/**
 * List of installed authentication modules
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Panel
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Popup',
    'qui/controls/loader/Loader',
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Register',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Change',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/CodePopup',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Panel.css'

], function (QUI, QUIPanel, QUIButton, QUIPopup, QUILoader, Grid, Authentication, AuthRegister,
             AuthChange, RecoveryCodePopup, SyncAuthPluginWindow, Recovery, Ajax, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/auth/Panel',

        Binds: [
            '$onInject',
            '$onRefresh',
            '$onCreate',
            '$onResize',
            'refresh',
            'registerUser',
            'changeAuthInfo'
        ],

        options: {
            openRecoveryForAuthPluginId: false // immediately open recovery panel for the given AuthPlugin ID
        },

        initialize: function (options) {
            this.setAttribute('title', QUILocale.get(lg, 'auth.panel.title'));

            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onRefresh: this.$onRefresh,
                onCreate : this.$onCreate,
                onResize : this.$onResize
            });

            this.Loader         = new QUILoader();
            this.$GridContainer = null;
            this.$Grid          = null;
        },

        /**
         * event on DOMElement creation
         */
        $onCreate: function () {
            var self    = this,
                Content = this.getContent();

            Content.setStyles({
                padding: 0
            });

            // buttons
            this.addButton({
                name     : 'register',
                text     : QUILocale.get(lg, 'auth.panel.btn.register'),
                textimage: 'fa fa-key',
                events   : {
                    onClick: this.registerUser
                }
            });

            this.addButton({
                name     : 'change',
                text     : QUILocale.get(lg, 'auth.panel.btn.change'),
                textimage: 'fa fa-edit',
                events   : {
                    onClick: this.changeAuthInfo
                }
            });

            this.addButton({
                name     : 'recovery',
                text     : QUILocale.get(lg, 'auth.panel.btn.recovery'),
                textimage: 'fa fa-question-circle',
                events   : {
                    onClick: function () {
                        self.recoverAuthData(
                            self.$Grid.getSelectedData()[0].id
                        );
                    }
                }
            });

            // content
            this.$GridContainer = new Element('div', {
                'class': 'pcsg-gpm-authpanel-container'
            }).inject(Content);

            this.$GridFX = moofx(this.$GridContainer);

            var GridContainer = new Element('div', {
                'class': 'pcsg-gpm-authpanel-grid'
            }).inject(this.$GridContainer);

            this.$Grid = new Grid(GridContainer, {
                columnModel: [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 40
                }, {
                    header   : QUILocale.get(lg, 'auth.panel.tbl.header.title'),
                    dataIndex: 'title',
                    dataType : 'text',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'auth.panel.tbl.header.description'),
                    dataIndex: 'description',
                    dataType : 'text',
                    width    : 500
                }, {
                    header   : QUILocale.get(lg, 'auth.panel.tbl.header.registered'),
                    dataIndex: 'registered',
                    dataType : 'text',
                    width    : 75
                }, {
                    header   : '&nbsp;',
                    dataIndex: 'sync',
                    dataType : 'node',
                    width    : 65
                }, {
                    dataIndex: 'isregistered',
                    hidden   : true
                }],

                pagination : false,
                filterInput: true,

                perPage: 1000,
                page   : 1,

                alternaterows    : true,
                resizeColumns    : true,
                selectable       : true,
                multipleSelection: false,
                resizeHeaderOnly : true
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    var Row = self.$Grid.getSelectedData()[0];

                    if (Row.isregistered) {
                        self.changeAuthInfo();
                        return;
                    }

                    self.registerUser();
                },
                onClick   : function () {
                    var selectedCount = self.$Grid.getSelectedData().length,
                        Row           = self.$Grid.getSelectedData()[0],
                        Register      = self.getButtons('register'),
                        Change        = self.getButtons('change'),
                        Recovery      = self.getButtons('recovery');

                    if (selectedCount == 1) {
                        if (!Row.isregistered) {
                            Register.enable();
                            Change.disable();
                            Recovery.disable();
                        } else {
                            Register.disable();
                            Change.enable();
                            Recovery.enable();

                        }
                    } else {
                        Register.disable();
                        Change.disable();
                        Recovery.disable();
                    }
                },
                onRefresh : this.refresh
            });
        },

        $onInject: function () {
            this.resize();
            this.refresh();
        },

        $onRefresh: function () {
            this.refresh();
        },

        $onResize: function () {
            var size = this.$GridContainer.getSize();

            this.$Grid.setHeight(size.y);
            this.$Grid.resize();
        },

        /**
         * refresh the auth plugin list
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            return Authentication.getAuthPlugins().then(function (authPlugins) {
                self.$setGridData(authPlugins);
                self.Loader.hide();

                // disable buttons
                self.getButtons('register').disable();
                self.getButtons('change').disable();
                self.getButtons('recovery').disable();

                if (self.getAttribute('openRecoveryForAuthPluginId')) {
                    self.recoverAuthData(self.getAttribute('openRecoveryForAuthPluginId'));
                }
            });
        },

        /**
         * Set data to table
         *
         * @param {array} authPlugins
         */
        $setGridData: function (authPlugins) {
            var Row;
            var data = [];
            var self = this;

            for (var i = 0, len = authPlugins.length; i < len; i++) {
                var Data = authPlugins[i];

                Row = {
                    id         : Data.id,
                    title      : Data.title,
                    description: Data.description
                };

                if (Data.registered) {
                    Row.registered   = QUILocale.get(lg, 'auth.panel.registered.yes');
                    Row.isregistered = true;
                } else {
                    Row.registered   = QUILocale.get(lg, 'auth.panel.registered.no');
                    Row.isregistered = false;
                }

                if (Data.sync) {
                    Row.sync = new QUIButton({
                        icon        : 'fa fa-exclamation-triangle',
                        authPluginId: Data.id,
                        height      : 20,
                        styles      : {
                            color        : 'red',
                            'line-height': 0
                        },
                        events      : {
                            onClick: function (Btn) {
                                self.$showSyncAuthPluginWindow(
                                    Btn.getAttribute('authPluginId')
                                );
                            }
                        }
                    }).create();
                } else {
                    Row.sync = new Element('span', {
                        html: '&nbsp;'
                    });
                }

                data.push(Row);
            }

            this.$Grid.setData({
                data : data,
                page : 1,
                total: 1
            });
        },

        /**
         * Open window to synchronise an authentication plugin
         *
         * @param {Number} authPluginId
         */
        $showSyncAuthPluginWindow: function (authPluginId) {
            var self = this;

            this.Loader.show();

            Authentication.getNonFullyAccessibleSecurityClassIds(
                authPluginId
            ).then(function (securityClassIds) {
                self.Loader.hide();

                new SyncAuthPluginWindow({
                    authPluginId    : authPluginId,
                    securityClassIds: securityClassIds,
                    events          : {
                        onSuccess: function () {
                            self.refresh();
                        }
                    }
                }).open();
            });
        },

        /**
         * Opens register dialog
         */
        registerUser: function () {
            var self = this;

            this.Loader.show();

            var AuthPluginData = self.$Grid.getSelectedData()[0];
            var Register, RegisterSheet;

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.auth.panel.register.title'),
                events: {
                    onShow : function (Sheet) {
                        RegisterSheet = Sheet;

                        Sheet.getContent().setStyle('padding', 20);

                        Register = new AuthRegister({
                            authPluginId: AuthPluginData.id,
                            events      : {
                                onFinish: function () {
                                    self.Loader.hide();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get(lg, 'auth.panel.register.btn.register'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: FuncOnRegisterBtnClick
                                }
                            })
                        );
                    },
                    onClose: function (Sheet) {
                        Sheet.destroy();
                    }
                }
            }).show();

            var FuncOnRegisterBtnClick = function () {
                self.Loader.show();

                Register.submit().then(function (RecoveryCodeData) {
                    self.Loader.hide();

                    if (!RecoveryCodeData) {
                        return;
                    }

                    RegisterSheet.hide().then(function () {
                        RegisterSheet.destroy();

                        new RecoveryCodePopup({
                            RecoveryCodeData: RecoveryCodeData,
                            events          : {
                                onClose: function () {
                                    RecoveryCodeData = null;
                                    self.$nonFullyAccessiblePasswordCheck(
                                        AuthPluginData.id
                                    );
                                }
                            }
                        }).open();
                    });
                });
            };
        },

        /**
         * Checks for all passwords that can be accessed with a specific
         * authentication plugin of the user has access to all those passwords
         * via this authentication plugin
         *
         * @param {number} authPluginId
         */
        $nonFullyAccessiblePasswordCheck: function (authPluginId) {
            var self = this;

            Authentication.hasNonFullyAccessiblePasswords(
                authPluginId
            ).then(function (result) {
                if (!result) {
                    self.refresh();
                    return;
                }

                new SyncAuthPluginWindow({
                    authPluginId: authPluginId,
                    events      : {
                        onSuccess: function (SyncWindow) {
                            SyncWindow.close();
                            self.refresh();
                        },
                        onClose  : function () {
                            self.refresh();
                        }
                    }
                }).open();
            });
        },

        /**
         * Opens the change auth information dialog
         */
        changeAuthInfo: function () {
            var self = this;

            this.Loader.show();

            var AuthPluginData = self.$Grid.getSelectedData()[0];

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.auth.panel.change.title', {
                    authPluginTitle: AuthPluginData.title
                }),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Change = new AuthChange({
                            Parent      : self,
                            authPluginId: AuthPluginData.id,
                            events      : {
                                onLoaded: function () {
                                    self.Loader.hide();
                                },
                                onFinish: function () {
                                    Sheet.destroy();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get(lg, 'auth.panel.change.btn.register'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: function () {
                                        Change.submit();
                                    }
                                }
                            })
                        );
                    },
                    onClose: function (Sheet) {
                        Sheet.destroy();
                    }
                }
            }).show();
        },

        /**
         * Show sheet with auth data recovery process
         *
         * @param {Number} authPluginId
         */
        recoverAuthData: function (authPluginId) {
            var self = this;

            this.Loader.show();

            Authentication.getAuthPluginInfo(authPluginId).then(function (AuthPluginData) {
                this.createSheet({
                    title : QUILocale.get(lg, 'gpm.auth.panel.recovery.title', {
                        authPluginTitle: AuthPluginData.title
                    }),
                    events: {
                        onShow : function (Sheet) {
                            Sheet.getContent().setStyle('padding', 20);

                            new Recovery({
                                authPluginId: authPluginId,
                                events      : {
                                    onLoaded: function () {
                                        self.Loader.hide();
                                    },
                                    onFinish: function () {
                                        Sheet.destroy();
                                    }
                                }
                            }).inject(Sheet.getContent());
                        },
                        onClose: function (Sheet) {
                            Sheet.destroy();
                        }
                    }
                }).show();
            }.bind(this));
        }
    });

});