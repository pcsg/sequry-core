/**
 * Manage Invite Codes
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/Panel
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/Panel', [

    'qui/controls/desktop/Panel',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',

    'controls/grid/Grid',
    'package/pcsg/grouppasswordmanager/bin/Actors',

    'Locale',
    'Mustache',

    'text!package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/Panel.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/Panel.css'

], function (QUIPanel, QUILoader, QUIConfirm, QUIButton, Grid, Actors, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/Panel',

        Binds: [
            '$onCreate',
            '$onResize',
            '$listRefresh',
            '$onRefresh',
            '$load',
            '$setGridData',
            'refresh',
            '$unlock'
        ],

        options: {
            title: QUILocale.get(lg, 'controls.actors.groupadmins.Panel.title')
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader      = new QUILoader();
            this.$User       = null;
            this.$Grid       = null;
            this.$GridParent = null;
            this.$Panel      = null;

            this.addEvents({
                onCreate : this.$onCreate,
                onRefresh: this.$onRefresh,
                onResize : this.$onResize
            });
        },

        /**
         * Event: onCreate
         */
        $onCreate: function () {
            var self = this;

            this.Loader.inject(this.$Elm);

            this.addButton({
                name     : 'unlockAll',
                text     : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.btn.unlockAll'),
                textimage: 'fa fa-unlock',
                events   : {
                    onClick: function () {
                        self.$unlock(true);
                    }
                }
            });

            this.addButton({
                name     : 'unlock',
                text     : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.btn.unlock'),
                textimage: 'fa fa-unlock-alt',
                events   : {
                    onClick: function () {
                        self.$unlock();
                    }
                }
            });

            this.$load();
        },

        /**
         * Refresh data
         */
        refresh: function () {
            if (this.$Grid) {
                this.$Grid.refresh();
            }
        },

        /**
         * event: onResize
         */
        $onResize: function () {
            if (this.$GridParent && this.$Grid) {
                var size = this.$GridParent.getSize();

                this.$Grid.setHeight(size.y);
                this.$Grid.resize();
            }
        },

        /**
         * Load Grid
         */
        $load: function () {
            var self = this;

            this.setContent(Mustache.render(template));
            var Content = this.getContent();

            this.$GridParent = Content.getElement(
                '.pcsg-gpm-actors-groupadmins-panel-table'
            );

            this.$Grid = new Grid(this.$GridParent, {
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'userId',
                    dataType : 'number',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.header.userName'),
                    dataIndex: 'userName',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.header.group'),
                    dataIndex: 'group',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.header.securityClass'),
                    dataIndex: 'securityClass',
                    dataType : 'string',
                    width    : 200
                }, {
                    dataIndex: 'groupId',
                    dataType : 'number',
                    hidden   : true
                }, {
                    dataIndex: 'securityClassId',
                    dataType : 'number',
                    hidden   : true
                }],
                pagination       : true,
                serverSort       : true,
                selectable       : true,
                multipleSelection: true
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    // @todo
                    //self.$managePackages(
                    //    self.$Grid.getSelectedData()[0].id
                    //);
                },
                onClick   : function () {
                    self.getButtons('unlock').enable();
                },
                onRefresh : this.$listRefresh
            });

            this.resize();
            this.$Grid.refresh();
        },

        /**
         * Event: onRefresh
         */
        $onRefresh: function () {
            if (this.$Grid) {
                this.$Grid.refresh();
            }
        },

        /**
         * Refresh bundle list
         *
         * @param {Object} Grid
         */
        $listRefresh: function (Grid) {
            if (!this.$Grid) {
                return;
            }

            var self = this;

            self.getButtons('unlock').disable();
            self.getButtons('unlockAll').disable();

            var GridParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            switch (GridParams.sortOn) {
                case 'name':
                    GridParams.sortOn = 'userId';
                    break;

                case 'group':
                    GridParams.sortOn = 'groupId';
                    break;

                case 'securityclass':
                    GridParams.sortOn = 'securityClassId';
                    break;
            }

            this.Loader.show();

            Actors.getGroupAdminUnlockList(GridParams).then(function (ResultData) {
                self.Loader.hide();
                self.$setGridData(ResultData);

                if (ResultData.total) {
                    self.getButtons('unlockAll').enable();
                }
            });
        },

        /**
         * Set license data to grid
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            this.$Grid.setData(GridData);
        },

        /**
         * Unlock users for groups
         *
         * @param {Boolean} [all] - Authorize all open requests
         */
        $unlock: function (all) {
            var self             = this;
            var unlockInfo       = [];
            var unlockRequests   = [];
            var securityClassIds = [];
            var rows;

            if (all) {
                rows = this.$Grid.getData();
            } else {
                rows = this.$Grid.getSelectedData();
            }

            for (var i = 0, len = rows.length; i < len; i++) {
                var Row = rows[i];

                unlockInfo.push(
                    Row.userName + ' (ID: #' + Row.userId + ') | ' +
                    Row.group + ' | ' +
                    Row.securityClass
                );

                if (!securityClassIds.contains(Row.securityClassId)) {
                    securityClassIds.push(Row.securityClassId);
                }

                unlockRequests.push({
                    userId         : Row.userId,
                    groupId        : Row.groupId,
                    securityClassId: Row.securityClassId
                });
            }

            // open popup
            var Popup = new QUIConfirm({
                'maxHeight': 300,
                'autoclose': false,

                'information': QUILocale.get(
                    lg,
                    'controls.actors.groupadmins.Panel.unlock.popup.info', {
                        requests: unlockInfo.join('<br/>')
                    }
                ),
                'title'      : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.unlock.popup.title'),
                'texticon'   : 'fa fa-unlock',
                text         : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.unlock.popup.title'),
                'icon'       : 'fa fa-unlock',

                cancel_button: {
                    text     : false,
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : false,
                    textimage: 'icon-ok fa fa-check'
                },
                events       : {
                    onSubmit: function () {
                        Popup.Loader.show();

                        Actors.unlockUsersForGroups(
                            securityClassIds,
                            unlockRequests
                        ).then(function (success) {
                            if (!success) {
                                Popup.Loader.hide();
                                return;
                            }

                            Popup.close();
                            self.refresh();
                        });
                    }
                }
            });

            Popup.open();
        },

        /**
         * Open user panel
         *
         * @param {Number} userId
         */
        $openUserPanel: function (userId) {
            return new Promise(function (resolve, reject) {
                require([
                    'controls/users/User',
                    'utils/Panels'
                ], function (UserPanel, PanelUtils) {
                    PanelUtils.openPanelInTasks(new UserPanel(userId)).then(resolve, reject);
                }.bind(this));
            });
        }
    });
});
