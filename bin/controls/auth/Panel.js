/**
 * List of installed authentication modules
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/auth/Panel
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/auth/Panel', [

    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'qui/controls/loader/Loader',
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Register',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Change',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/CodePopup',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/recovery/Recovery',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Panel.css'

], function (QUIPanel, QUIButton, QUIConfirm, QUILoader, Grid, Authentication, AuthRegister,
             AuthChange, RecoveryCodePopup, Recovery, AuthWindow, QUIAjax,
             QUILocale) {
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
            'changeAuthInfo',
            'recoverAuthData'
        ],

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

            this.$CurrentSheet = null;
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

            this.addButton({
                name     : 'regenerate',
                text     : QUILocale.get(lg, 'auth.panel.btn.regenerate'),
                textimage: 'fa fa-retweet',
                events   : {
                    onClick: function () {
                        self.regenerateRecoveryCode(
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
                        Recovery      = self.getButtons('recovery'),
                        Regenerate    = self.getButtons('regenerate');

                    if (selectedCount === 1) {
                        if (!Row.isregistered) {
                            Register.enable();
                            Change.disable();
                            Recovery.disable();
                            Regenerate.disable();
                        } else {
                            Register.disable();
                            Change.enable();
                            Recovery.enable();
                            Regenerate.enable();

                        }
                    } else {
                        Register.disable();
                        Change.disable();
                        Recovery.disable();
                        Regenerate.disable();
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
                self.getButtons('regenerate').disable();
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

            var showSyncWindow = function (Btn) {
                self.$syncAuthPlugin(
                    Btn.getAttribute('authPluginId')
                );
            };

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
                            onClick: showSyncWindow
                        }
                    }).create();
                } else {
                    Row.sync = new Element('span', {
                        html: '&nbsp;'
                    });
                }

                data.push(Row);
            }

            if (!this.$Grid) {
                return;
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
        $syncAuthPlugin: function (authPluginId) {
            var self = this;

            var startSync = function() {
                self.Loader.show();

                QUIAjax.post(
                    'package_pcsg_grouppasswordmanager_ajax_auth_syncAuthPlugin',
                    function () {
                        self.Loader.hide();
                        self.refresh();
                    }, {
                        'package'   : 'pcsg/grouppasswordmanager',
                        authPluginId: authPluginId
                    }
                );
            };

            Authentication.getNonFullyAccessibleSecurityClassIds(
                authPluginId
            ).then(function (securityClassIds) {
                Authentication.multiSecurityClassAuth(
                    securityClassIds,
                    QUILocale.get(lg, 'auth.panel.sync_info')
                ).then(
                    startSync,
                    function() {
                        self.refresh();
                    }
                );
            });
        },

        /**
         * Opens register dialog
         */
        registerUser: function () {
            var self = this;

            if (this.$CurrentSheet) {
                this.$CurrentSheet.destroy();
                this.$CurrentSheet = null;
            }

            this.Loader.show();

            var AuthPluginData = self.$Grid.getSelectedData()[0];
            var Register, RegisterSheet;

            this.$CurrentSheet = this.createSheet({
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
            });

            this.$CurrentSheet.show();

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

                self.$syncAuthPlugin(authPluginId);
            });
        },

        /**
         * Opens the change auth information dialog
         */
        changeAuthInfo: function () {
            var self = this;

            if (this.$CurrentSheet) {
                this.$CurrentSheet.destroy();
                this.$CurrentSheet = null;
            }

            this.Loader.show();

            var AuthPluginData = self.$Grid.getSelectedData()[0];

            this.$CurrentSheet = this.createSheet({
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
            });

            this.$CurrentSheet.show();
        },

        /**
         * Show sheet with auth data recovery process
         *
         * @param {Number} authPluginId
         */
        recoverAuthData: function (authPluginId) {
            var self = this;

            this.Loader.show();

            if (this.$CurrentSheet) {
                this.$CurrentSheet.destroy();
                this.$CurrentSheet = null;
            }

            Authentication.getAuthPluginInfo(authPluginId).then(function (AuthPluginData) {
                self.$CurrentSheet = self.createSheet({
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
                });

                self.$CurrentSheet.show();
            });
        },

        /**
         * Re-generate a recovery code
         */
        regenerateRecoveryCode: function () {
            var self           = this;
            var AuthPluginData = self.$Grid.getSelectedData()[0];

            this.Loader.show();

            var getAuthData = function () {
                return new Promise(function (resolve, reject) {
                    var Popup = new AuthWindow({
                        authPluginIds: [AuthPluginData.id],
                        required     : 1,
                        info         : QUILocale.get(lg,
                            'controls.auth.panel.regenerateRecoveryCode.authwindow.info'
                        ),
                        events       : {
                            onSubmit: function (AuthData) {
                                resolve(AuthData[AuthPluginData.id]);
                                Popup.close();
                            },
                            onClose : function () {
                                reject();
                                Popup.close();
                            },
                            onAbort : function () {
                                reject();
                                Popup.close();
                            }
                        }
                    });

                    Popup.open();
                });
            };

            Authentication.getRecoveryCodeId(AuthPluginData.id).then(function (recoveryCodeId) {
                var ConfirmPopup = new QUIConfirm({
                    icon       : 'fa fa-retweet',
                    texticon   : 'fa fa-retweet',
                    autoclose  : false,
                    title      : QUILocale.get(lg, 'auth.panel.regenerate.popup.title', {
                        authPluginTitle: AuthPluginData.title
                    }),
                    information: QUILocale.get(lg, 'auth.panel.regenerate.popup.info', {
                        recoveryCodeId: recoveryCodeId
                    }),
                    maxHeight  : 350,
                    maxWidth   : 850,
                    closeButton: true,
                    ok_button  : {
                        text     : QUILocale.get(lg, 'auth.panel.regenerate.popup.btn.confirm'),
                        textimage: 'fa fa-retweet'
                    },
                    text       : QUILocale.get(lg, 'auth.panel.regenerate.popup.header', {
                        authPluginTitle: AuthPluginData.title
                    }),
                    events     : {
                        onOpen  : function () {
                            self.Loader.hide();
                        },
                        onSubmit: function () {
                            ConfirmPopup.Loader.show();

                            getAuthData().then(function (authData) {
                                Authentication.regenerateRecoveryCode(
                                    AuthPluginData.id,
                                    authData
                                ).then(function (RecoveryCodeData) {
                                    if (!RecoveryCodeData) {
                                        ConfirmPopup.Loader.hide();
                                        return;
                                    }

                                    new RecoveryCodePopup({
                                        RecoveryCodeData: RecoveryCodeData,
                                        events          : {
                                            onClose: function () {
                                                ConfirmPopup.close();
                                            }
                                        }
                                    }).open();
                                }, function () {
                                    ConfirmPopup.Loader.hide();
                                });
                            }, function () {
                                ConfirmPopup.Loader.hide();
                            });
                        }
                    }
                });

                ConfirmPopup.open();
            });
        }
    });

});