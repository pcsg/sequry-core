/**
 * Select actors for a SecurityClass via Grid
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable.css
 *
 * @event onSubmit [
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',
    'qui/utils/Form',
    'Locale',

    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/Actors',
    'package/pcsg/grouppasswordmanager/bin/Authentication',

    //'text!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable.css'

], function (QUIControl, QUILoader, QUIButton, QUIFormUtils, QUILocale, Grid,
             Actors, Authentication) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable',

        Binds: [
            '$onInject',
            '$onCreate',
            'submit',
            '$listRefresh',
            '$setGridData',
            'resize',
            'refresh',
            '$onTypeBtnClick'
        ],

        options: {
            securityClassId: false   // security class id the actors have to be eligible for
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                backgroundClosable: true,
                closeButton       : true,
                titleCloseButton  : true,
                maxWidth          : 500
            });

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader         = new QUILoader();
            this.$Grid          = null;
            this.$GridParent    = null;
            this.$actorType     = 'users';
            this.$search        = false;
            this.$SecurityClass = null;
        },

        /**
         * Event: onCreate
         */
        $onInject: function () {
            var self = this;

            this.Loader.inject(this.$Elm);

            this.$Elm.addClass('pcsg-gpm-actors-selecttable');

            this.$Elm.set(
                'html',
                '<div class="pcsg-gpm-actors-selecttable-grid"></div>'
            );

            this.Loader.show();

            Authentication.getSecurityClassInfo(this.getAttribute('securityClassId')).then(function (SecurityClass) {
                self.$SecurityClass = SecurityClass;

                // content
                self.$GridParent = self.$Elm.getElement(
                    '.pcsg-gpm-actors-selecttable-grid'
                );

                self.$Grid = new Grid(self.$GridParent, {
                    buttons          : [{
                        name  : 'users',
                        text  : QUILocale.get(lg, 'controls.actors.selecttable.tbl.btn.users'),
                        icon  : 'fa fa-user',
                        events: {
                            onClick: self.$onTypeBtnClick
                        }
                    }, {
                        name  : 'groups',
                        text  : QUILocale.get(lg, 'controls.actors.selecttable.tbl.btn.groups'),
                        icon  : 'fa fa-users',
                        events: {
                            onClick: self.$onTypeBtnClick
                        }
                    }],
                    pagination       : true,
                    selectable       : true,
                    serverSort       : true,
                    multipleSelection: false,
                    columnModel      : [{
                        header   : QUILocale.get(lg, 'controls.actors.selecttable.tbl.header.id'),
                        dataIndex: 'id',
                        dataType : 'integer',
                        width    : 100
                    }, {
                        header   : QUILocale.get(lg, 'controls.actors.selecttable.tbl.header.name'),
                        dataIndex: 'name',
                        dataType : 'string',
                        width    : 200
                    }, {
                        header   : QUILocale.get(lg, 'controls.actors.selecttable.tbl.header.notice', {
                            securityClassTitle: self.$SecurityClass.title
                        }),
                        dataIndex: 'notice',
                        dataType : 'node',
                        width    : 500
                    }, {
                        dataIndex: 'eligible',
                        dataType : 'boolean',
                        hidden   : true
                    }]
                });

                self.$Grid.addEvents({
                    onDblClick: function () {
                        self.$submit();
                    },
                    onRefresh : self.$listRefresh
                });

                self.resize();
                self.refresh();

                var TableButtons = self.$Grid.getAttribute('buttons');
                TableButtons.users.setActive();
            });
        },

        /**
         * Event: onClick (type switch buttons)
         *
         * @param {Object} Btn - qui/controls/buttons/Button
         */
        $onTypeBtnClick: function (Btn) {
            var TableButtons = this.$Grid.getAttribute('buttons');
            var type         = Btn.getAttribute('name');

            if (type === 'users') {
                TableButtons.groups.setNormal();
            } else {
                TableButtons.users.setNormal();
            }

            this.$actorType = type;
            this.refresh();
        },

        /**
         * Refresh data
         */
        refresh: function () {
            this.$Grid.refresh();
        },

        /**
         * Resize control
         */
        resize: function () {
            if (this.$Grid && this.$GridParent) {
                this.$Grid.setHeight(this.$GridParent.getSize().y);
                this.$Grid.resize();
            }
        },

        /**
         * Refresh Grid
         *
         * @param {Object} Grid
         */
        $listRefresh: function (Grid) {
            if (!this.$Grid) {
                return;
            }

            var self = this;

            var SearchParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            switch (SearchParams.sortOn) {
                case 'name':
                    SearchParams.sortOn = 'username';
                    break;

                case 'notice':
                    return;
            }

            if (this.$search) {
                SearchParams.search = this.$search;
            }

            SearchParams.type            = this.$actorType;
            SearchParams.securityClassId = this.getAttribute('securityClassId');

            this.Loader.show();

            Actors.search(SearchParams).then(function (ResultData) {
                self.Loader.hide();
                self.$setGridData(ResultData);
            });
        },

        /**
         * Set user data to grid
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Row = GridData.data[i];

                if (Row.eligible) {
                    Row.notice = new Element('div', {
                        'class': 'pcsg-gpm-actors-selecttable-eligible',
                        html   : '<span>' + QUILocale.get(lg,
                            'controls.actors.selecttable.tbl.eligible'
                        ) + '</span>'
                    });
                } else {
                    Row.notice = new Element('div', {
                        'class': 'pcsg-gpm-actors-selecttable-noteligible',
                        html   : '<span>' + QUILocale.get(lg,
                            'controls.actors.selecttable.tbl.not.eligible'
                        ) + '</span>'
                    });
                }
            }

            this.$Grid.setData(GridData);

            var TableButtons = this.$Grid.getAttribute('buttons');
            TableButtons[this.$actorType].setActive();
        },

        /**
         * Submit selected actors (user or groups)
         */
        $submit: function () {
            var selectedIds  = [];
            var selectedData = this.$Grid.getSelectedData();

            for (var i = 0, len = selectedData.length; i < len; i++) {
                selectedIds.push(selectedData[i].id);
            }

            var SubmitData = {
                users : [],
                groups: []
            };

            if (this.$actorType === 'users') {
                SubmitData.users = selectedIds;
            } else {
                SubmitData.groups = selectedIds;
            }

            this.fireEvent('submit', [SubmitData, this]);
        }
    });
});
