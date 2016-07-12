/**
 * Password listing
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel.css
 *
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Create',
    'package/pcsg/grouppasswordmanager/bin/controls/password/View',
    'package/pcsg/grouppasswordmanager/bin/controls/passwords/Search',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel.css'

], function (QUI, QUIPanel, QUIButton, QUILoader, QUIPopup, Grid, PasswordHandler,
             PasswordCreate, PasswordView, PasswordSearch, AuthenticationControl, Ajax, QUILocale) {
    "use strict";

    var lg        = 'pcsg/grouppasswordmanager';
    var Passwords = new PasswordHandler();

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel',

        Binds: [
            '$onInject',
            '$onRefresh',
            '$onCreate',
            '$onResize',
            'refresh',
            'createPassword',
            'viewPassword',
            'showSearch',
            '$listRefresh',
            '$addRemoveSearchBtn'
        ],

        options: {},

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
            this.$SearchParams  = {};
            this.$removeBtn     = false;
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
                name  : 'search',
                icon  : 'fa fa-search',
                events: {
                    onClick: this.showSearch
                }
            });

            this.addButton({
                name     : 'add',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.add'),
                textimage: 'fa fa-plus',
                events   : {
                    onClick: this.createPassword
                }
            });

            this.addButton({
                name     : 'view',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.view'),
                textimage: 'fa fa-eye',
                events   : {
                    onClick: function () {
                        self.viewPassword(
                            self.$Grid.getSelectedData()[0].id,
                            self.$Grid.getSelectedData()[0].securityClassId
                        );
                    }
                }
            });

            this.addButton({
                name     : 'edit',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.edit'),
                textimage: 'fa fa-edit',
                events   : {
                    onClick: function () {
                        self.$openPasswordPanel(
                            self.$Grid.getSelectedData()[0].id
                        );
                    }
                }
            });

            this.addButton({
                name     : 'delete',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.delete'),
                textimage: 'fa fa-trash',
                events   : {
                    onClick: function () {
                        self.deletePassword(
                            self.$Grid.getSelectedData()[0]
                        );
                    }
                }
            });

            // content
            this.$GridContainer = new Element('div', {
                'class': 'pcsg-gpm-panel-container'
            }).inject(Content);

            this.$GridFX = moofx(this.$GridContainer);

            var GridContainer = new Element('div', {
                'class': 'pcsg-gpm-panel-grid'
            }).inject(this.$GridContainer);

            this.$Grid = new Grid(GridContainer, {
                pagination : true,
                columnModel: [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.title'),
                    dataIndex: 'title',
                    dataType : 'text',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.description'),
                    dataIndex: 'description',
                    dataType : 'text',
                    width    : 500
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.datatype'),
                    dataIndex: 'dataType',
                    dataType : 'text',
                    width    : 200
                }, {
                    dataIndex: 'securityClassId',
                    dataType : 'integer',
                    hidden   : true
                }]
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    self.viewPassword(
                        self.$Grid.getSelectedData()[0].id,
                        self.$Grid.getSelectedData()[0].securityClassId
                    );
                },
                onClick   : function () {
                    var Delete = self.getButtons('delete');
                    var View   = self.getButtons('view');
                    var Edit   = self.getButtons('edit');

                    View.enable();
                    Delete.enable();
                    Edit.enable();
                },
                onRefresh : this.$listRefresh
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
         * refresh grid
         *
         * @param {Object} Grid
         */
        $listRefresh: function (Grid) {
            var self = this;

            this.Loader.show();

            var GridParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            return Passwords.getPasswords(
                Object.merge(GridParams, this.$SearchParams)
            ).then(function (gridData) {
                self.$setGridData(gridData);
                self.Loader.hide();
            });
        },

        /**
         * refresh the password list
         */
        refresh: function () {
            this.$Grid.refresh();
        },

        $setGridData: function (GridData) {
            var Row;
            var Delete = this.getButtons('delete');
            var View   = this.getButtons('view');
            var Edit   = this.getButtons('edit');

            View.disable();
            Delete.disable();
            Edit.disable();

            for (var i = 0, len = GridData.data.length; i < len; i++) {
                Row = GridData.data[i];
            }

            this.$Grid.setData(GridData);
        },

        /**
         * Opens the create password dialog
         */
        createPassword: function () {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.passwords.panel.create.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Create = new PasswordCreate({
                            events: {
                                onLoaded: function () {
                                    self.Loader.hide();
                                },
                                onFinish: function () {
                                    Sheet.hide();
                                    self.refresh();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get('quiqqer/system', 'save'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: function () {
                                        Create.submit();
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

        /**+
         * Deletes a password
         *
         * @param {Object} PasswordInfo
         */
        deletePassword: function (PasswordInfo) {
            var self = this;

            // open popup
            var DeletePopup = new QUIPopup({
                title      : QUILocale.get(
                    lg, 'gpm.passwords.panel.delete.popup.title'
                ),
                maxHeight  : 300,
                maxWidth   : 500,
                closeButton: true,
                content    : '<div class="pcsg-gpm-passwords-delete-info">' +
                '<h1 class="pcsg-gpm-passwords-delete-info-title">' +
                QUILocale.get(lg, 'gpm.passwords.panel.delete.popup.info.title') +
                '</h1>' +
                '<span class="pcsg-gpm-passwords-delete-info-description">' +
                QUILocale.get(lg, 'gpm.passwords.panel.delete.popup.info.description', {
                    passwordId   : PasswordInfo.id,
                    passwordTitle: PasswordInfo.title
                }) +
                '</span>' +
                '</div>'
            });

            DeletePopup.open();

            DeletePopup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'gpm.passwords.panel.delete.popup.btn.text'),
                alt   : QUILocale.get(lg, 'gpm.passwords.panel.delete.popup.btn'),
                title : QUILocale.get(lg, 'gpm.passwords.panel.delete.popup.btn'),
                events: {
                    onClick: function () {
                        var AuthControl = new AuthenticationControl({
                            securityClassId: PasswordInfo.securityClassId,
                            events         : {
                                onSubmit: function (AuthData) {
                                    DeletePopup.Loader.show();

                                    Passwords.deletePassword(
                                        PasswordInfo.id,
                                        AuthData
                                    ).then(
                                        function () {
                                            AuthControl.destroy();
                                            DeletePopup.close();
                                            self.refresh();
                                        },
                                        function () {
                                            DeletePopup.Loader.hide();
                                        }
                                    );
                                }
                            }
                        });

                        AuthControl.open();
                    }
                }
            }));
        },

        /**
         * Opens password view
         *
         * @param {number} passwordId
         * @param {number} securityClassId
         */
        viewPassword: function (passwordId, securityClassId) {
            new PasswordView({
                passwordId     : passwordId,
                securityClassId: securityClassId
            }).open();
        },

        /**
         * Opens the create password dialog
         */
        showSearch: function () {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.passwords.panel.search.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Search = new PasswordSearch({
                            events: {
                                onLoaded: function () {
                                    self.Loader.hide();
                                },
                                onSubmit: function (SearchData) {
                                    self.$SearchParams = SearchData;
                                    self.$Grid.refresh();
                                    self.$addRemoveSearchBtn();
                                    Search.destroy();
                                    Sheet.hide();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get('pcsg/grouppasswordmanager', 'gpm.passwords.panel.btn.search'),
                                textimage: 'fa fa-search',
                                events   : {
                                    onClick: function () {
                                        Search.submit();
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
         * Add button that removes search
         */
        $addRemoveSearchBtn: function () {
            var self = this;

            if (this.$removeBtn) {
                return;
            }

            this.$removeBtn = true;

            this.addButton({
                name     : 'searchremove',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.searchremove'),
                textimage: 'fa fa-times',
                styles   : {
                    float: 'right'
                },
                events   : {
                    onClick: function (Btn) {
                        self.$SearchParams = {};
                        self.$Grid.refresh();
                        Btn.destroy();
                        self.$removeBtn = false;
                    }
                }
            });
        },

        /**
         * (Re-)opens a single password panel
         *
         * @param {integer} passwordId
         */
        $openPasswordPanel: function (passwordId) {
            var self = this;

            require([
                'package/pcsg/grouppasswordmanager/bin/controls/password/Panel',
                'utils/Panels'
            ], function (PasswordPanel, Panels) {
                var PPanel = new PasswordPanel({
                    passwordId: passwordId,
                    '#id'     : passwordId
                });

                //MPanel.addEvents({
                //    onDeleteMachine: function (machineId) {
                //        //var row = self.$getRowByMachineId(machineId);
                //        //
                //        //if (row !== false) {
                //        //    self.Table.deleteRow(row);
                //        //}
                //    },
                //    onEditMachine  : function (machineId) {
                //        //self.$refreshMachine(machineId);
                //    }
                //});

                Panels.openPanelInTasks(PPanel);
            });
        }

    });

});