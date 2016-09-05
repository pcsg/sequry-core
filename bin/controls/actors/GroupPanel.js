/**
 * List of installed authentication modules
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/GroupPanel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupPanel.css
 *
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/GroupPanel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',
    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/GroupEdit',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/GroupPanel.css'

], function (QUI, QUIPanel, QUIButton, QUILoader, Grid, AuthHandler, GroupEdit,
             Ajax, QUILocale) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager';
    var Authentication = new AuthHandler();

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/GroupPanel',

        Binds: [
            '$onInject',
            '$onRefresh',
            '$onCreate',
            '$onResize',
            'refresh',
        ],

        options: {
            title: QUILocale.get(lg, 'actors.groups.panel.title')
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

            // content
            this.$GridContainer = new Element('div', {
                'class': 'pcsg-gpm-grouppanel-container'
            }).inject(Content);

            this.$GridFX = moofx(this.$GridContainer);

            var GridContainer = new Element('div', {
                'class': 'pcsg-gpm-grouppanel-grid'
            }).inject(this.$GridContainer);

            this.$Grid = new Grid(GridContainer, {
                columnModel: [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'group.panel.tbl.header.name'),
                    dataIndex: 'name',
                    dataType : 'text',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'group.panel.tbl.header.securityclasses'),
                    dataIndex: 'securityClasses',
                    dataType : 'text',
                    width    : 400
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
                    self.editGroup(
                        self.$Grid.getSelectedData()[0].id
                    );
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
         * refresh the groups list
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            Ajax.get('package_pcsg_grouppasswordmanager_ajax_actors_getGroups', function (groups) {
                self.$setGridData(groups);
                self.Loader.hide();
            }, {
                'package': 'pcsg/grouppasswordmanager'
            });
        },

        /**
         * Set data to table
         *
         * @param {array} groups
         */
        $setGridData: function (groups) {
            var Row;
            var data = [];

            for (var i = 0, len = groups.length; i < len; i++) {
                var Group = groups[i];

                Row = {
                    id  : Group.id,
                    name: Group.name
                };

                if (Group.securityClasses.length) {
                    Row.securityClasses = Group.securityClasses.join(', ');
                } else {
                    Row.securityClasses = QUILocale.get(lg, 'gpm.groups.panel.table.nosecurityclass');
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
         * Opens the edit security class dialog
         *
         * @param {integer} id - security class id
         */
        editGroup: function (id) {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.groups.panel.edit.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Edit = new GroupEdit({
                            groupId: id,
                            events : {
                                onLoaded : function () {
                                    self.Loader.hide();
                                },
                                onSuccess: function () {
                                    Sheet.hide();
                                    self.refresh();
                                }
                            }
                        }).inject(Sheet.getContent());

                        //Sheet.addButton(
                        //    new QUIButton({
                        //        text     : QUILocale.get('quiqqer/system', 'save'),
                        //        textimage: 'fa fa-save',
                        //        events   : {
                        //            onClick: function () {
                        //                Edit.submit();
                        //            }
                        //        }
                        //    })
                        //);
                    },
                    onClose: function (Sheet) {
                        Sheet.destroy();
                    }
                }
            }).show();
        }
    });
});