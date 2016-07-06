/**
 * List of installed authentication modules
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Panel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Panel.css
 *
 */
define('package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Create',
    'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Edit',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Panel.css'

], function (QUI, QUIPanel, QUIButton, QUILoader, QUIPopup, Grid, AuthHandler,
             SecurityClassCreate, SecurityClassEdit, Ajax, QUILocale) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager';
    var Authentication = new AuthHandler();

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Panel',

        Binds: [
            '$onInject',
            '$onRefresh',
            '$onCreate',
            '$onResize',
            'refresh',
            'createSecurityClass',
            'deleteSecurityClass'
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
                name     : 'add',
                text     : QUILocale.get(lg, 'securityclasses.panel.btn.add'),
                textimage: 'fa fa-plus',
                events   : {
                    onClick: this.createSecurityClass
                }
            });

            this.addButton({
                name     : 'edit',
                text     : QUILocale.get(lg, 'securityclasses.panel.btn.edit'),
                textimage: 'fa fa-edit',
                events   : {
                    onClick: function () {
                        self.editSecurityClass(
                            self.$Grid.getSelectedData()[0].id
                        );
                    }
                }
            });

            this.addButton({
                name     : 'delete',
                text     : QUILocale.get(lg, 'securityclasses.panel.btn.delete'),
                textimage: 'fa fa-trash',
                events   : {
                    onClick: function () {
                        self.deleteSecurityClass({
                            id   : self.$Grid.getSelectedData()[0].id,
                            title: self.$Grid.getSelectedData()[0].title
                        });
                    }
                }
            });

            // content
            this.$GridContainer = new Element('div', {
                'class': 'pcsg-gpm-securityclassespanel-container'
            }).inject(Content);

            this.$GridFX = moofx(this.$GridContainer);

            var GridContainer = new Element('div', {
                'class': 'pcsg-gpm-securityclassespanel-grid'
            }).inject(this.$GridContainer);

            this.$Grid = new Grid(GridContainer, {
                columnModel: [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 40
                }, {
                    header   : QUILocale.get(lg, 'securityclasses.panel.tbl.header.title'),
                    dataIndex: 'title',
                    dataType : 'text',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'securityclasses.panel.tbl.header.description'),
                    dataIndex: 'description',
                    dataType : 'text',
                    width    : 450
                }, {
                    header   : QUILocale.get(lg, 'securityclasses.panel.tbl.header.factors'),
                    dataIndex: 'factors',
                    dataType : 'text',
                    width    : 200
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
                onClick  : function () {
                    var selectedCount = self.$Grid.getSelectedData().length,
                        Delete        = self.getButtons('delete'),
                        Edit          = self.getButtons('edit');

                    if (selectedCount == 1) {
                        Edit.enable();
                        Delete.enable();
                    } else {
                        Edit.disable();
                        Delete.disable();
                    }
                },
                onRefresh: this.refresh
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
         * refresh the securityclasses list
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            Authentication.getSecurityClasses().then(function (SecurityClasses) {
                self.$setGridData(SecurityClasses);
                self.getButtons('edit').disable();
                self.getButtons('delete').disable();
                self.Loader.hide();
            });
        },

        /**
         * Set data to table
         *
         * @param {Object} SecurityClasses
         */
        $setGridData: function (SecurityClasses) {
            var Row;
            var data = [];

            for (var id in SecurityClasses) {
                if (!SecurityClasses.hasOwnProperty(id)) {
                    continue;
                }

                var Info           = SecurityClasses[id];
                var authPlugins    = Info.authPlugins;
                var authPluginInfo = '';

                for (var i = 0, len = authPlugins.length; i < len; i++) {
                    authPluginInfo += authPlugins[i].title;
                }

                Row = {
                    id         : id,
                    title      : Info.title,
                    description: Info.description,
                    factors    : authPluginInfo
                };

                data.push(Row);
            }

            this.$Grid.setData({
                data : data,
                page : 1,
                total: 1
            });
        },

        /**
         * Opens the create security class dialog
         */
        createSecurityClass: function () {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.securityclasses.panel.create.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Create = new SecurityClassCreate({
                            events: {
                                onLoaded : function () {
                                    self.Loader.hide();
                                },
                                onSuccess: function () {
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

        /**
         * Opens the edit security class dialog
         *
         * @param {integer} id - security class id
         */
        editSecurityClass: function (id) {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.securityclasses.panel.edit.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Edit = new SecurityClassEdit({
                            securityClassId: id,
                            events         : {
                                onLoaded : function () {
                                    self.Loader.hide();
                                },
                                onSuccess: function () {
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
                                        Edit.submit();
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
         * Deletes a security class
         *
         * @param {Object} SecurityClassInfo
         */
        deleteSecurityClass: function (SecurityClassInfo) {
            var self = this;

            // open popup
            var DeletePopup = new QUIPopup({
                title      : QUILocale.get(
                    lg, 'gpm.securityclasses.panel.delete.popup.title'
                ),
                maxHeight  : 300,
                maxWidth   : 500,
                closeButton: true,
                content    : '<div class="pcsg-gpm-securityclasses-delete-info">' +
                '<h1 class="pcsg-gpm-securityclasses-delete-info-title">' +
                QUILocale.get(lg, 'gpm.securityclasses.panel.delete.popup.info.title') +
                '</h1>' +
                '<span class="pcsg-gpm-securityclasses-delete-info-description">' +
                QUILocale.get(lg, 'gpm.securityclasses.panel.delete.popup.info.description', {
                    securityClassId   : SecurityClassInfo.id,
                    securityClassTitle: SecurityClassInfo.title
                }) +
                '</span>' +
                '</div>'
            });

            DeletePopup.open();

            DeletePopup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'gpm.securityclasses.panel.delete.popup.btn.text'),
                alt   : QUILocale.get(lg, 'gpm.securityclasses.panel.delete.popup.btn'),
                title : QUILocale.get(lg, 'gpm.securityclasses.panel.delete.popup.btn'),
                events: {
                    onClick: function () {
                        Authentication.deleteSecurityClass(
                            SecurityClassInfo.id
                        ).then(function () {
                            DeletePopup.close();
                            self.refresh();
                        });
                    }
                }
            }));
        }
    });

});