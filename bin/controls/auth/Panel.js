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
    'qui/controls/loader/Loader',
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/auth/Panel.css'

], function (QUI, QUIPanel, QUIButton, QUILoader, Grid, AuthHandler,
             Ajax, QUILocale) {
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
                name     : 'register',
                text     : QUILocale.get(lg, 'auth.panel.btn.register'),
                textimage: 'fa fa-key',
                events   : {
                    //onClick: this.createPassword
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
                pagination : true,
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
                    width    : 50
                }]
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    //self.$openPortalPanel(
                    //    self.$Grid.getSelectedData()[0].id
                    //);
                },
                onClick   : function () {
                    var selectedCount = self.$Grid.getSelectedData().length,
                        Register = self.getButtons('register');

                    if (selectedCount == 1) {
                        Register.enable();
                    } else {
                        Register.disable();
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

            return Authentication.getAuthPlugins().then(function (gridData) {
                self.$setGridData(gridData);
                self.getButtons('register').disable();
                self.Loader.hide();
            });
        },

        /**
         * Set data to table
         *
         * @param {{}} GridData
         */
        $setGridData: function (GridData) {
            var Row;

            this.$Grid.clear();

            for (var i = 0, len = GridData.length; i < len; i++) {
                var Data = GridData[i];

                Row = {
                    id         : Data.id,
                    title      : Data.title,
                    description: Data.description
                };

                if (Data.registered) {
                    Row.registered = QUILocale.get(lg, 'auth.panel.registered.yes');
                } else {
                    Row.registered = QUILocale.get(lg, 'auth.panel.registered.no');
                }

                this.$Grid.addRow(Row);
            }
        },

        /**
         * Opens the create password dialog
         */
        registerUser: function () {
            var self = this;

            //this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.passwords.panel.create.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Password = new PasswordCreate().inject(Sheet.getContent());

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
    });

});