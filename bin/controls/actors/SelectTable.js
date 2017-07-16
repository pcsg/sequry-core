/**
 * Select actors for a SecurityClass via Grid
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require controls/grid/Grid
 * @require package/pcsg/grouppasswordmanager/bin/Actors
 * @require package/pcsg/grouppasswordmanager/bin/Authentication
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable.css
 *
 * @event onSubmit [selectedIds, actorType, this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',
    'Locale',

    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/Actors',
    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable.css'

], function (QUIControl, QUILoader, QUIButton, QUILocale, Grid, Actors, Authentication) {
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
            multiselect    : false,
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
            this.$SearchInput   = null;
            this.$eligibleOnly  = false;
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
                        icon  : 'fa fa-user',
                        events: {
                            onClick: self.$onTypeBtnClick
                        }
                    }, {
                        name  : 'groups',
                        icon  : 'fa fa-users',
                        events: {
                            onClick: self.$onTypeBtnClick
                        }
                    }, {
                        name     : 'showeligibleonly',
                        text     : QUILocale.get(lg, 'controls.actors.selecttable.tbl.btn.showeligibleonly'),
                        textimage: 'fa fa-check-circle-o',
                        events   : {
                            onClick: function () {
                                self.$eligibleOnly = !self.$eligibleOnly;
                                self.refresh();
                            }
                        }
                    }],
                    pagination       : true,
                    selectable       : true,
                    serverSort       : true,
                    multipleSelection: self.getAttribute('multiselect'),
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

                // add search input
                var ButtonBarElm = self.$Grid.getElm().getElement('.tDiv');

                self.$SearchInput = new Element('input', {
                    'class'    : 'pcsg-gpm-actors-selecttable-searchinput',
                    placeholder: QUILocale.get(lg,
                        'controls.actors.selecttable.search.input.placeholder'
                    ),
                    type       : 'search'
                }).inject(ButtonBarElm);

                self.$SearchInput.addEventListener('search', function (event) {
                    var Input    = event.target;
                    self.$search = Input.value.trim();
                    self.refresh();
                });

                self.$Grid.addEvents({
                    onDblClick: function () {
                        self.fireEvent('submit', [
                            self.getSelectedIds(), self.$actorType, self
                        ]);
                    },
                    onRefresh : self.$listRefresh
                });

                self.resize();
                self.refresh();
                self.$SearchInput.focus();

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

            if (type === this.$actorType) {
                return;
            }

            this.$search            = false;
            this.$SearchInput.value = '';
            this.$SearchInput.focus();

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

            SearchParams.eligibleOnly    = this.$eligibleOnly;
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
                        'class': 'pcsg-gpm-actors-selecttable-eligibility',
                        html   : '<span class="pcsg-gpm-actors-selecttable-eligible">' +
                        QUILocale.get(lg,
                            'controls.actors.selecttable.tbl.eligible'
                        ) + '</span>'
                    });
                } else {
                    var reasonText;

                    if (this.$actorType === 'users') {
                        reasonText = QUILocale.get(lg,
                            'controls.actors.selecttable.tbl.not.eligible.user'
                        );
                    } else {
                        reasonText = QUILocale.get(lg,
                            'controls.actors.selecttable.tbl.not.eligible.group'
                        );
                    }

                    Row.notice = new Element('div', {
                        'class': 'pcsg-gpm-actors-selecttable-eligibility',
                        html   : '<span class="pcsg-gpm-actors-selecttable-noteligible">' +
                        QUILocale.get(lg,
                            'controls.actors.selecttable.tbl.not.eligible'
                        ) + '</span>' +
                        '<span>' + reasonText + '</span>'
                    });
                }
            }

            this.$Grid.setData(GridData);

            var TableButtons = this.$Grid.getAttribute('buttons');
            TableButtons[this.$actorType].setActive();

            if (this.$eligibleOnly) {
                TableButtons.showeligibleonly.setActive();
            } else {
                TableButtons.showeligibleonly.setNormal();
            }
        },

        /**
         * Get IDs of all selected actors
         *
         * @return {Array}
         */
        getSelectedIds: function () {
            var selectedIds  = [];
            var selectedData = this.$Grid.getSelectedData();

            for (var i = 0, len = selectedData.length; i < len; i++) {
                selectedIds.push(selectedData[i].id);
            }

            return selectedIds;
        },

        /**
         * Get currently selected actor type
         *
         * @returns {string} - "users" / "groups"
         */
        getActorType: function () {
            return this.$actorType;
        }
    });
});
