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
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/passwords/Create',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel.css'

], function (QUI, QUIPanel, QUIButton, QUILoader, Grid, PasswordHandler,
             PasswordCreate, Ajax, QUILocale) {
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
            'createPassword'
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
                name: 'add',
                text: QUILocale.get(lg, 'controls.gpm.passwords.btn.add'),
                textimage: 'fa fa-plus',
                events: {
                    onClick: this.createPassword
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
                    width    : 60
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.title'),
                    dataIndex: 'title',
                    dataType : 'text',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.description'),
                    dataIndex: 'description',
                    dataType : 'text',
                    width    : 200
                }]
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    //self.$openPortalPanel(
                    //    self.$Grid.getSelectedData()[0].id
                    //);
                },
                onClick   : function () {
                    //var selected = self.$Grid.getSelectedData()[0],
                    //    Delete = self.getButtons('delete'),
                    //    Edit = self.getButtons('edit'),
                    //    Copy = self.getButtons('copy');
                    //
                    //Delete.enable();
                    //Edit.enable();
                    //Copy.enable();
                    //
                    //Delete.setAttribute(
                    //    'text',
                    //    QUILocale.get('quiqqer/system', 'delete') + ' (#' + selected.id + ')'
                    //);
                    //
                    //Edit.setAttribute(
                    //    'text',
                    //    QUILocale.get('quiqqer/system', 'edit') + ' (#' + selected.id + ')'
                    //);
                    //
                    //Copy.setAttribute(
                    //    'text',
                    //    QUILocale.get(lg, 'controls.portallist.panel.copy') + ' (#' + selected.id + ')'
                    //);
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
         * refresh the password list
         *
         * @param {{}} GridParams
         * @return {Promise}
         */
        refresh: function (GridParams) {
            if (!this.$Grid) {
                return;
            }

            var self = this;

            this.Loader.show();

            GridParams = GridParams || {};

            GridParams.perPage = this.$Grid.options.perPage;
            GridParams.page    = this.$Grid.options.page;

            return Passwords.getPasswords(GridParams).then(function (gridData) {
                self.$setGridData(gridData);
                self.Loader.hide();
            });
        },

        $setGridData: function (GridData) {
            var self = this;
            var Row;

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

                        var Password = new PasswordCreate({
                            events: {
                                onLoaded: function() {
                                    self.Loader.hide();
                                }
                            }
                        }).inject(Sheet.getContent());

                        //var Product = new CreateProduct({
                        //    events: {
                        //        onLoaded: function () {
                        //            self.Loader.hide();
                        //        }
                        //    }
                        //}).inject(Sheet.getContent());


                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get('quiqqer/system', 'save'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: function () {
                                        Password.submit();

                                        //self.Loader.show();
                                        //
                                        //Product.submit().then(function (Product) {
                                        //    self.updateChild(Product.id);
                                        //
                                        //    Sheet.hide().then(function () {
                                        //        Sheet.destroy();
                                        //        self.refresh();
                                        //    });
                                        //}).catch(function (err) {
                                        //    if (typeOf(err) == 'string') {
                                        //        QUI.getMessageHandler().then(function (MH) {
                                        //            MH.addError(err);
                                        //        });
                                        //    }
                                        //
                                        //    self.Loader.hide();
                                        //});
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
         * (Re-)opens a machine panel
         *
         * @param {integer} machineId
         */
        $openPasswordPanel: function (machineId) {
            var self = this;

            require([
                'package/pcsg/grouppasswordmanager/bin/passwords/MachinePanel',
                'utils/Panels'
            ], function (MachinePanel, Panels) {
                var MPanel = new MachinePanel({
                    machineId: machineId,
                    '#id'    : machineId
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

                Panels.openPanelInTasks(MPanel);
            });
        }

    });

});