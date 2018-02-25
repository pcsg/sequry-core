/**
 * Search and list actors for a specific SecurityClass
 *
 * @module package/sequry/core/bin/controls/actors/Search
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @requrie Ajax
 * @require Locale
 * @require css!package/sequry/core/bin/controls/actors/Search.css
 *
 */
define('package/sequry/core/bin/controls/actors/Search', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Popup',
    'qui/controls/loader/Loader',
    'controls/grid/Grid',

    'Mustache',
    'Ajax',
    'Locale',

    'text!package/sequry/core/bin/controls/actors/Search.html',
    'css!package/sequry/core/bin/controls/actors/Search.css'

], function (QUI, QUIControl, QUIButton, QUIPopup, QUILoader, Grid, Mustache,
             QUIAjax, QUILocale, template) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/actors/Search',

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
            securityClassId: false,
            multiSelect    : true
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
            this.$actorType     = 'users';
            this.$search        = '';
        },

        /**
         * event on DOMElement creation
         */
        $onCreate: function () {
            var self = this;

            var lgPrefix = 'controls.actors.search.template.';

            this.$Elm.set('html', Mustache.render(template, {
                info       : QUILocale.get(lg, lgPrefix + 'info'),
                labelUsers : QUILocale.get(lg, lgPrefix + 'labelUsers'),
                labelGroups: QUILocale.get(lg, lgPrefix + 'labelGroups')
            }));

            // content
            this.$GridContainer = this.$Elm.getElement(
                '.pcsg-gpm-actors-search-table'
            );

            this.$Grid = new Grid(this.$GridContainer, {
                columnModel: [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 40
                }, {
                    header   : QUILocale.get(lg, 'auth.panel.tbl.header.title'),
                    dataIndex: 'name',
                    dataType : 'text',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'auth.panel.tbl.header.description'),
                    dataIndex: 'description',
                    dataType : 'text',
                    width    : 350
                }, {
                    header   : QUILocale.get(lg, 'auth.panel.tbl.header.annotation'),
                    dataIndex: 'annotation',
                    dataType : 'text',
                    width    : 500
                }],

                pagination : true,
                filterInput: true,

                perPage: 150,
                page   : 1,

                alternaterows    : true,
                resizeColumns    : true,
                selectable       : true,
                multipleSelection: this.getAttribute('multiSelect'),
                resizeHeaderOnly : true,
                serverSort       : true
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    //self.$openPortalSearch(
                    //    self.$Grid.getSelectedData()[0].id
                    //);
                },
                onClick   : function () {

                },
                onRefresh : this.refresh
            });
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            this.resize();
            this.refresh();
        },

        /**
         * Event: onRefresh
         */
        $onRefresh: function () {
            this.refresh();
        },

        /**
         * Event: onResize
         */
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
                                        if (!Change.check()) {
                                            return;
                                        }

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
        }
    });

});