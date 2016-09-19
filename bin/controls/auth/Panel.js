/**
 * List of installed authentication modules
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Panel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/auth/Panel.css
 *
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Popup',
    'qui/controls/loader/Loader',
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Register',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Change',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/SyncAuthPluginWindow',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Panel.css'

], function (QUI, QUIPanel, QUIButton, QUIPopup, QUILoader, Grid, AuthHandler, AuthRegister,
             AuthChange, RecoveryCodeWindow, SyncAuthPluginWindow, Ajax, QUILocale) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager';
    var Authentication = new AuthHandler();

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
            title: QUILocale.get(lg, 'auth.panel.title')
        },

        initialize: function (options) {
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
                    //self.$openPortalPanel(
                    //    self.$Grid.getSelectedData()[0].id
                    //);
                },
                onClick   : function () {
                    var selectedCount = self.$Grid.getSelectedData().length,
                        Row           = self.$Grid.getSelectedData()[0],
                        Register      = self.getButtons('register'),
                        Change        = self.getButtons('change');

                    if (selectedCount == 1) {
                        if (!Row.isregistered) {
                            Register.enable();
                            Change.disable();
                        } else {
                            Register.disable();
                            Change.enable();
                        }
                    } else {
                        Register.disable();
                        Change.disable();
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
                self.getButtons('register').disable();
                self.getButtons('change').disable();
                self.Loader.hide();
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
                                new SyncAuthPluginWindow({
                                    authPluginId: Btn.getAttribute('authPluginId')
                                }).open();
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

                        new RecoveryCodeWindow({
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
                        onClose: function() {
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
                            authPluginId: AuthPluginData.id,
                            events      : {
                                onFinish: function () {
                                    self.Loader.hide();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get(lg, 'auth.panel.change.btn.register'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: function () {
                                        if (!Change.check()) {
                                            return;
                                        }

                                        self.Loader.show();

                                        Change.submit().then(function (RecoveryCodeData) {
                                            self.Loader.hide();

                                            if (!RecoveryCodeData) {
                                                return;
                                            }

                                            Sheet.hide().then(function () {
                                                Sheet.destroy();

                                                new RecoveryCodeWindow({
                                                    authPluginId    : AuthPluginData.id,
                                                    authPluginTitle : AuthPluginData.title,
                                                    RecoveryCodeData: RecoveryCodeData,
                                                    events          : {
                                                        onClose: function () {
                                                            RecoveryCodeData = null;
                                                            self.refresh();
                                                        }
                                                    }
                                                }).open();
                                            });
                                        });
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
        }
    });

});