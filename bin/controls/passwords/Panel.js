/**
 * Password listing and CRUD operations
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Seperator',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',

    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',

    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/Passwords',
    'package/pcsg/grouppasswordmanager/bin/Authentication',
    'package/pcsg/grouppasswordmanager/bin/Actors',
    'package/pcsg/grouppasswordmanager/bin/Categories',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Create',
    'package/pcsg/grouppasswordmanager/bin/controls/password/View',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Share',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Edit',
    'package/pcsg/grouppasswordmanager/bin/controls/passwords/Search',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/RecoveryCodeWindow',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Select',
    'package/pcsg/grouppasswordmanager/bin/controls/categories/private/Select',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel.css'

], function (QUI, QUIPanel, QUISeparator, QUIButton, QUISelect, QUILoader, QUIPopup, QUIConfirm,
             QUISiteMap, QUISiteMapItem, Grid, Passwords, Authentication, Actors, Categories,
             PasswordCreate, PasswordView, PasswordShare, PasswordEdit, PasswordSearch,
             AuthenticationControl, PasswordAuthentication, RecoveryCodeWindow,
             CategorySelect, CategorySelectPrivate, Ajax, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel',

        options: {
            passwordId: false, // immediately open password on inject
            icon      : 'fa fa-diamond',
            '#id'     : 'pcsg-gpm-list'
        },

        Binds: [
            '$onInject',
            '$onCreate',
            '$onResize',
            '$onDestroy',
            'refresh',
            'createPassword',
            'viewPassword',
            'showSearch',
            '$listRefresh',
            '$addRemoveSearchBtn',
            '$copyPasswordContent',
            'setSearchCategory',
            'setSearchFilters',
            'removeSearchFilters',
            '$showCategoryInfo',
            '$showPasswordsCategoryDialog',
            '$getRowDataByPasswordId'
        ],

        initialize: function (options) {
            this.setAttribute('title', QUILocale.get(lg, 'passwords.panel.title'));

            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onRefresh: this.$onRefresh,
                onCreate : this.$onCreate,
                onResize : this.$onResize
            });

            this.Loader          = new QUILoader();
            this.$GridContainer  = null;
            this.$Grid           = null;
            this.$SearchParams   = {
                search: {}
            };
            this.$dblClickAction = 'view';
            this.$InfoElm        = null;
            this.$searchUsed     = false;
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

            Content.set(
                'html',
                '<div class="pcsg-gpm-passwords-panel-info" style="display: none;"></div>' +
                '<div class="pcsg-gpm-passwords-panel-table"></div>'
            );

            this.$InfoElm = Content.getElement(
                '.pcsg-gpm-passwords-panel-info'
            );

            // buttons
            this.addButton({
                name     : 'add',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.add'),
                textimage: 'fa fa-plus',
                events   : {
                    onClick: this.createPassword
                }
            });

            this.addButton(new QUISeparator());

            new QUIButton({
                name  : 'categories',
                alt   : QUILocale.get(lg, 'controls.gpm.passwords.btn.categories'),
                title : QUILocale.get(lg, 'controls.gpm.passwords.btn.categories'),
                icon  : 'fa fa-book',
                events: {
                    onClick: this.$showPasswordsCategoryDialog
                },
                styles: {
                    'border-left-width' : 1,
                    'border-right-width': 1,
                    'float'             : 'right',
                    width               : 40
                }
            }).inject(this.getHeader());

            var DblClickActionSelect = new QUISelect({
                placeholderText      : false,
                placeholderIcon      : false,
                placeholderSelectable: false, // placeholder is standard selectable menu child
                showIcons            : false,
                events               : {
                    onChange: function (value) {
                        self.$dblClickAction = value;
                    }
                }
            });

            DblClickActionSelect.appendChild(
                QUILocale.get(lg, 'controls.gpm.passwords.dblclickaction.view'),
                'view'
            ).appendChild(
                QUILocale.get(lg, 'controls.gpm.passwords.dblclickaction.edit'),
                'edit'
            ).appendChild(
                QUILocale.get(lg, 'controls.gpm.passwords.dblclickaction.share'),
                'share'
            );

            this.addButton(DblClickActionSelect);

            DblClickActionSelect.setValue('view');

            // content
            this.$GridContainer = Content.getElement(
                '.pcsg-gpm-passwords-panel-table'
            );

            var GridContainer = new Element('div', {
                'class': 'pcsg-gpm-panel-grid'
            }).inject(this.$GridContainer);

            this.$Grid = new Grid(GridContainer, {
                pagination       : true,
                selectable       : true,
                serverSort       : true,
                multipleSelection: true,
                columnModel      : [{
                    header   : '&nbsp;',
                    dataIndex: 'favorite',
                    dataType : 'node',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.actions'),
                    dataIndex: 'permissions',
                    dataType : 'node',
                    width    : 100
                }, {
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
                    width    : 400
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.datatype'),
                    dataIndex: 'dataType',
                    dataType : 'text',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.accesstype'),
                    dataIndex: 'accessType',
                    dataType : 'node',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.securityClass'),
                    dataIndex: 'securityClass',
                    dataType : 'string',
                    width    : 125
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.owner'),
                    dataIndex: 'owner',
                    dataType : 'node',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.shared'),
                    dataIndex: 'shared',
                    dataType : 'node',
                    width    : 75
                }, {
                    dataIndex: 'securityClassId',
                    dataType : 'integer',
                    hidden   : true
                }, {
                    dataIndex: 'isOwner',
                    dataType : 'integer',
                    hidden   : true
                }]
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    var Row  = self.$Grid.getSelectedData()[0];
                    var pwId = Row.id;

                    switch (self.$dblClickAction) {
                        case 'edit':
                            if (Row.isOwner) {
                                self.editPassword(pwId);
                                break;
                            }

                            self.viewPassword(pwId);
                            break;

                        case 'share':
                            if (Row.canShare) {
                                self.sharePassword(pwId);
                                break;
                            }

                            self.viewPassword(pwId);
                            break;

                        default:
                            self.viewPassword(pwId);
                    }
                },
                onRefresh : this.$listRefresh
            });

            // search button
            this.$SearchInput = new Element('div', {
                'class': 'pcsg-gpm-passwords-panel-search',
                html   : '<input type="text" data-mode="search"/><span class="fa fa-search-plus"></span>'
            });

            this.$SearchInput.getElement('input').setProperty(
                'placeholder',
                QUILocale.get(lg, 'controls.gpm.passwords.panel.search.placeholder')
            );

            var SearchIcon = this.$SearchInput.getElement('span');

            this.$SearchInput.getElement('input').addEvents({
                keyup: function (event) {
                    if (event.target.value.trim() === '') {
                        SearchIcon.removeClass('fa-search');
                        SearchIcon.addClass('fa-search-plus');
                    } else {
                        SearchIcon.addClass('fa-search');
                        SearchIcon.removeClass('fa-search-plus');
                    }

                    if (event.code === 13) {
                        self.$SearchParams.search.searchterm = event.target.value.trim();

                        if (event.target.value.trim() !== '') {
                            self.$searchUsed = true;
                        }

                        self.refresh();
                    }
                }
            });

            SearchIcon.addEvents({
                click: function () {
                    var Input = self.$SearchInput.getElement('input');

                    if (Input.getProperty('data-mode') === 'refresh') {
                        Input.value = '';
                        Input.focus();
                        self.$SearchParams.search = {};
                        self.$searchUsed          = false;
                        self.refresh();

                        return;
                    }

                    if (Input.value.trim() === '') {
                        Input.value = '';
                        self.showSearch();
                        return;
                    }

                    self.$SearchParams.search.searchterm = Input.value.trim();
                    self.refresh();
                }
            });

            this.addButton(this.$SearchInput);

            window.PasswordList = this;
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            var self = this;

            this.resize();
            this.refresh().then(function () {
                var pwId = self.getAttribute('passwordId');

                if (pwId) {
                    self.viewPassword.delay(200, self, pwId);
                }

                self.fireEvent('loaded', [self]);
                self.$initialRegistration();
            });
        },

        /**
         * Event: onResize
         */
        $onResize: function () {
            // workaround - force shows text on button bar buttons
            this.getButtonBar()
                .getElm()
                .getElement('.qui-toolbar-tabs')
                .removeClass('qui-toolbar--mobile');

            this.$GridContainer.setStyle(
                'height',
                this.getContent().getSize().y - this.$InfoElm.getSize().y
            );

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

            switch (Grid.getAttribute('sortOn')) {
                case 'permissions':
                case 'accessType':
                case 'shared':
                    Grid.setAttribute('sortOn', false);
                    break;

                case 'owner':
                    Grid.setAttribute('sortOn', 'ownerId');
                    break;

                case 'securityClass':
                    Grid.setAttribute('sortOn', 'securityClassId');
                    break;
            }

            var GridParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            return Passwords.getPasswords(
                Object.merge(GridParams, this.$SearchParams)
            ).then(function (gridData) {
                //self.$showCategoryInfo();
                self.$setGridData(gridData);
                self.Loader.hide();
            });
        },

        /**
         * Refresh the password list
         *
         * @return {Promise}
         */
        refresh: function () {
            var SearchIcon = this.$SearchInput.getElement('span');

            if (this.$searchUsed) {
                SearchIcon.removeClass('fa-search');
                SearchIcon.removeClass('fa-search-plus');
                SearchIcon.addClass('fa-times');
                this.$SearchInput.getElement('input').setProperty('data-mode', 'refresh');
            } else {
                SearchIcon.removeClass('fa-times');
                SearchIcon.addClass('fa-search-plus');
                this.$SearchInput.getElement('input').setProperty('data-mode', 'search');
            }

            return this.$listRefresh(this.$Grid);
        },

        /**
         * Set password data to grid table
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            var Row;
            var self = this;

            var FuncAccessInfoClick = function () {
                self.$showAccessInfoPopup(
                    this.getProperty('data-row')
                );
            };

            var FuncShareInfoClick = function () {
                self.$showSharedWithPopup(
                    this.getProperty('data-row')
                );
            };

            var FuncFavoMouseEnter = function (event) {
                var Elm  = event.target;
                var favo = parseInt(Elm.getProperty('data-favo'));

                if (favo) {
                    Elm.removeClass('fa-star');
                    Elm.addClass('fa-star-o');

                    return;
                }

                Elm.removeClass('fa-star-o');
                Elm.addClass('fa-star');
            };

            var FuncFavoMouseLeave = function (event) {
                var Elm  = event.target;
                var favo = parseInt(Elm.getProperty('data-favo'));

                if (favo) {
                    Elm.removeClass('fa-star-o');
                    Elm.addClass('fa-star');

                    return;
                }

                Elm.removeClass('fa-star');
                Elm.addClass('fa-star-o');
            };

            var FuncFavoClick = function (event) {
                var Elm  = event.target;
                var favo = parseInt(Elm.getProperty('data-favo'));
                var pwId = Elm.getProperty('data-pwid');

                self.Loader.show();

                Passwords.setFavoriteStatus(pwId, !favo).then(function (newStatus) {
                    self.Loader.hide();

                    newStatus = newStatus ? 1 : 0;
                    Elm.setProperty('data-favo', newStatus);

                    if (newStatus) {
                        Elm.removeClass('fa-star-o');
                        Elm.addClass('fa-star');
                        return;
                    }

                    Elm.removeClass('fa-star');
                    Elm.addClass('fa-star-o');
                });
            };

            var FuncActionView = function (event) {
                self.viewPassword(event.target.getProperty('data-pwid'));
            };

            var FuncActionEdit = function (event) {
                self.editPassword(event.target.getProperty('data-pwid'));
            };

            var FuncActionShare = function (event) {
                self.sharePassword(event.target.getProperty('data-pwid'));
            };

            var FuncActionDelete = function (event) {
                self.deletePassword(event.target.getProperty('data-pwid'));
            };

            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Data = GridData.data[i];

                Row = Object.clone(Data);

                // access type
                Row.accessType = new Element('div', {
                    'class'   : 'pcsg-gpm-passwords-panel-table-accesstype',
                    'data-row': i,
                    events    : {
                        click: FuncAccessInfoClick
                    }
                });

                switch (Data.access) {
                    case 'user':
                        new Element('span', {
                            'class': 'fa fa-user'
                        }).inject(Row.accessType);
                        break;

                    case 'group':
                        new Element('span', {
                            'class': 'fa fa-users'
                        }).inject(Row.accessType);
                        break;
                }

                if (!Data.isOwner) {
                    new Element('span', {
                        'class': 'fa fa-share-alt pcsg-gpm-passwords-panel-table-accesstype-share-indicator'
                    }).inject(Row.accessType);
                }

                // security class
                Row.securityClass = Data.securityClassTitle;

                // favorite
                Row.favorite = new Element('div', {
                    'class': 'pcsg-gpm-passwords-panel-table-favorite',
                    title  : QUILocale.get(lg, 'controls.gpm.passwords.panel.favo.alt'),
                    alt    : QUILocale.get(lg, 'controls.gpm.passwords.panel.favo.alt')
                });

                var FavoElm = new Element('span', {
                    'data-pwid': Data.id,
                    events     : {
                        click     : FuncFavoClick,
                        mouseenter: FuncFavoMouseEnter,
                        mouseleave: FuncFavoMouseLeave
                    }
                }).inject(Row.favorite);

                if (parseInt(Data.favorite)) {
                    FavoElm.addClass('fa fa-star');
                    FavoElm.setProperty('data-favo', 1);
                } else {
                    FavoElm.addClass('fa fa-star-o');
                    FavoElm.setProperty('data-favo', 0);
                }

                // show actions (permissions)
                Row.permissions = new Element('div', {
                    'class': 'pcsg-gpm-passwords-panel-table-actions'
                });

                new Element('span', {
                    'class'    : 'fa fa-eye',
                    'data-pwid': Data.id,
                    alt        : QUILocale.get(lg, 'controls.gpm.passwords.action.view'),
                    title      : QUILocale.get(lg, 'controls.gpm.passwords.action.view'),
                    events     : {
                        click: FuncActionView
                    }
                }).inject(Row.permissions);

                var Share = new Element('span', {
                    'class'    : 'fa fa-share-alt',
                    'data-pwid': Data.id,
                    alt        : QUILocale.get(lg, 'controls.gpm.passwords.action.share'),
                    title      : QUILocale.get(lg, 'controls.gpm.passwords.action.share')
                }).inject(Row.permissions);

                var Edit = new Element('span', {
                    'class'    : 'fa fa-edit',
                    'data-pwid': Data.id,
                    alt        : QUILocale.get(lg, 'controls.gpm.passwords.action.edit'),
                    title      : QUILocale.get(lg, 'controls.gpm.passwords.action.edit')
                }).inject(Row.permissions);

                var Delete = new Element('span', {
                    'class'    : 'fa fa-trash',
                    'data-pwid': Data.id,
                    alt        : QUILocale.get(lg, 'controls.gpm.passwords.action.delete'),
                    title      : QUILocale.get(lg, 'controls.gpm.passwords.action.delete')
                }).inject(Row.permissions);

                if (Data.isOwner) {
                    if (Data.canShare) {
                        Share.addEvent('click', FuncActionShare);
                    } else {
                        Share.addClass('pcsg-gpm-passwords-panel-table-action-disabled');
                    }

                    if (Data.canDelete) {
                        Delete.addEvent('click', FuncActionDelete);
                    } else {
                        Delete.addClass('pcsg-gpm-passwords-panel-table-action-disabled');
                    }

                    Edit.addEvent('click', FuncActionEdit);
                } else {
                    Share.addClass('pcsg-gpm-passwords-panel-table-action-disabled');
                    Edit.addClass('pcsg-gpm-passwords-panel-table-action-disabled');
                    Delete.addClass('pcsg-gpm-passwords-panel-table-action-disabled');
                }

                // show owner
                switch (Data.ownerType) {
                    case "1":
                        if (Data.isOwner) {
                            Row.owner = new Element('div', {
                                'class': 'pcsg-gpm-passwords-panel-table-owner',
                                html   : '<span class="fa fa-user"></span>' +
                                '<span>' +
                                QUILocale.get(
                                    lg,
                                    'controls.gpm.passwords.panel.tbl.owner.myself'
                                ) +
                                '</span>'
                            });
                        } else {
                            Row.owner = new Element('div', {
                                'class': 'pcsg-gpm-passwords-panel-table-owner',
                                html   : '<span class="fa fa-user"></span>' +
                                '<span>' + Data.ownerName + '</span>'
                            });
                        }
                        break;

                    case "2":
                        Row.owner = new Element('div', {
                            'class': 'pcsg-gpm-passwords-panel-table-owner',
                            html   : '<span class="fa fa-users"></span>' +
                            '<span>' + Data.ownerName + '</span>'
                        });
                        break;
                }

                // show share info
                if (Data.isOwner) {
                    Row.shared = new Element('div', {
                        'class'   : 'pcsg-gpm-passwords-panel-grid-shared',
                        'data-row': i,
                        events    : {
                            click: FuncShareInfoClick
                        }
                    });

                    if (!Data.sharedWithUsers && !Data.sharedWithGroups) {
                        new Element('span', {
                            html: QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.shared.no')
                        }).inject(Row.shared);
                    }

                    if (Data.sharedWithUsers) {
                        new Element('span', {
                            'class': 'fa fa-user'
                        }).inject(Row.shared);
                    }

                    if (Data.sharedWithGroups) {
                        if (Data.sharedWithUsers) {
                            new Element('span', {
                                'class': 'pcsg-gpm-passwords-panel-grid-shared-separator',
                                html   : '&'
                            }).inject(Row.shared);
                        }

                        new Element('span', {
                            'class': 'fa fa-users'
                        }).inject(Row.shared);
                    }
                } else {
                    Row.shared = new Element('div', {
                        'class': 'pcsg-gpm-passwords-panel-grid-shared-no-permission',
                        title  : QUILocale.get(lg,
                            'controls.passwords.panel.tabl.shared.no_permission'
                        )
                    });

                    new Element('span', {
                        'class': 'fa fa-question-circle'
                    }).inject(Row.shared);
                }

                GridData.data[i] = Row;
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
                                        self.Loader.show();

                                        Create.submit().then(function () {
                                            self.Loader.hide();
                                        }, function () {
                                            self.Loader.hide();
                                        });
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
         * @param {number} passwordId
         */
        deletePassword: function (passwordId) {
            var self    = this;
            var RowData = this.$getRowDataByPasswordId(passwordId);

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
                    passwordId   : RowData.id,
                    passwordTitle: RowData.title
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
                        DeletePopup.Loader.show();

                        Passwords.deletePassword(RowData.id).then(
                            function () {
                                DeletePopup.close();
                                self.refresh();

                                if (window.PasswordCategories) {
                                    window.PasswordCategories.refreshCategories();
                                }
                            },
                            function () {
                                DeletePopup.close();
                            }
                        );
                    }
                }
            }));
        },

        /**
         * Opens password view
         *
         * @param {number} passwordId
         */
        viewPassword: function (passwordId) {
            var self    = this;
            var RowData = this.$getRowDataByPasswordId(passwordId);
            var canEdit = false;

            if (RowData) {
                canEdit = RowData.isOwner;
            }

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.passwords.panel.view.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var View = new PasswordView({
                            passwordId: passwordId,
                            //editPublicCategories: canEdit,
                            events    : {
                                onLoaded: function () {
                                    self.Loader.hide();
                                },
                                onClose : function () {
                                    View.destroy();
                                    Sheet.destroy();
                                    self.Loader.hide();
                                }
                            }
                        }).inject(Sheet.getContent());

                        if (!canEdit) {
                            return;
                        }

                        // edit btn
                        new QUIButton({
                            text     : QUILocale.get(lg,
                                'controls.gpm.passwords.panel.view.button.edit'
                            ),
                            textimage: 'fa fa-edit',
                            styles   : {
                                float : 'none',
                                margin: '12px 5px'
                            },
                            events   : {
                                onClick: function () {
                                    Sheet.destroy();
                                    self.editPassword(passwordId);
                                }
                            }
                        }).inject(
                            Sheet.getButtons().getElement('.qui-panel-sheet-buttons'),
                            'top'
                        );
                    },
                    onClose: function (Sheet) {
                        Sheet.destroy();
                    }
                }
            }).show();
        },

        /**
         * Opens password view
         *
         * @param {number} passwordId
         */
        sharePassword: function (passwordId) {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.passwords.panel.share.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Share = new PasswordShare({
                            passwordId: passwordId,
                            events    : {
                                onLoaded: function () {
                                    self.Loader.hide();
                                },
                                onClose : function () {
                                    Share.destroy();
                                    Sheet.destroy();
                                    self.Loader.hide();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get('quiqqer/system', 'save'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: function () {
                                        self.Loader.show();

                                        Share.submit().then(function () {
                                            Share.destroy();
                                            Sheet.destroy();
                                            self.Loader.hide();
                                            self.refresh();
                                        });
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
         * Opens password view
         *
         * @param {number} passwordId
         */
        editPassword: function (passwordId) {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.passwords.panel.edit.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var Edit = new PasswordEdit({
                            passwordId: passwordId,
                            events    : {
                                onLoaded: function () {
                                    self.Loader.hide();
                                },
                                onClose : function () {
                                    self.Loader.hide();
                                    Edit.destroy();
                                    Sheet.destroy();
                                }
                            }
                        }).inject(Sheet.getContent());

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get('quiqqer/system', 'save'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: function () {
                                        self.Loader.show();

                                        Edit.submit().then(function () {
                                            Edit.destroy();
                                            Sheet.destroy();
                                            self.refresh();
                                            self.Loader.hide();
                                        }, function () {
                                            self.Loader.hide();
                                        });
                                    }
                                }
                            })
                        );
                    },
                    onClose: function (Sheet) {
                        self.refresh();
                        Sheet.destroy();
                    }
                }
            }).show();
        },

        /**
         * Set category ID for category based search
         *
         * @param {number|false} catId - ID or false if no category
         */
        setSearchCategory: function (catId) {
            this.$SearchParams.categoryId        = catId;
            this.$SearchParams.categoryIdPrivate = false;
        },

        /**
         * Set category ID for private category based search
         *
         * @param {number|false} catId - ID or false if no category
         */
        setSearchCategoryPrivate: function (catId) {
            this.$SearchParams.categoryIdPrivate = catId;
            this.$SearchParams.categoryId        = false;
        },

        /**
         * Set specific search filters
         *
         * @param {array} filters
         */
        setSearchFilters: function (filters) {
            this.$SearchParams.filters = filters;
        },

        /**
         * Remove all search filters
         */
        removeSearchFilters: function () {
            this.$SearchParams.filters = null;
        },

        /**
         * Show dialog for setting categories to multiple passwords
         */
        $showPasswordsCategoryDialog: function () {
            var publicCatIds = [], privateCatIds = [];
            var rows         = this.$Grid.getSelectedData();
            var pwIds        = [];

            if (!rows.length) {
                return;
            }

            for (var i = 0, len = rows.length; i < len; i++) {
                pwIds.push(rows[i].id);
            }

            var self = this;

            this.Loader.show();

            var FuncSetPublicCategories = function (AuthData) {
                Popup.Loader.show();

                Categories.setPublicPasswordsCategories(
                    pwIds,
                    publicCatIds,
                    AuthData
                ).then(function () {
                    Popup.Loader.hide();
                    Popup.close();
                });
            };

            var FuncSetPrivateCategories = function () {
                Popup.Loader.show();

                Categories.setPrivatePasswordsCategories(pwIds, privateCatIds).then(function () {
                    Popup.Loader.hide();
                    Popup.close();
                });
            };

            var FuncSubmit = function () {
                if (!privateCatIds.length && !publicCatIds.length) {
                    Popup.close();
                    return;
                }

                if (privateCatIds.length && !publicCatIds.length) {
                    FuncSetPrivateCategories();
                    return;
                }

                Passwords.getSecurityClassIds(pwIds).then(function (securityClassIds) {
                    if (securityClassIds.length === 1) {
                        Authentication.securityClassAuth(
                            securityClassIds[0]
                        ).then(function (AuthData) {
                            FuncSetPublicCategories(AuthData);

                            if (privateCatIds.length) {
                                FuncSetPrivateCategories();
                            }
                        });

                        return;
                    }

                    Authentication.multiSecurityClassAuth(
                        securityClassIds
                    ).then(function (AuthData) {
                        FuncSetPublicCategories(AuthData);

                        if (privateCatIds.length) {
                            FuncSetPrivateCategories();
                        }
                    });
                });
            };

            // open popup
            var Popup = new QUIConfirm({
                'class'    : 'pcsg-gpm-passwords-panel-categories',
                'maxHeight': 300,
                maxWidth   : 600,
                'autoclose': true,

                'title'   : QUILocale.get(lg, 'controls.gpm.passwords.panel.categories.title'),
                'texticon': 'fa fa-book',
                'icon'    : 'fa fa-book',

                events: {
                    onOpen  : function () {
                        var Content = Popup.getContent();

                        Content.set(
                            'html',
                            '<div class="pcsg-gpm-passwords-panel-categories-info">' +
                            QUILocale.get(lg, 'controls.gpm.passwords.panel.categories.info') +
                            '</div>' +
                            '<div class="pcsg-gpm-passwords-panel-categories-public">' +
                            '<span><b>' + QUILocale.get(lg, 'controls.categories.panel.public.title') + '</b></span>' +
                            '</div>' +
                            '<div class="pcsg-gpm-passwords-panel-categories-private">' +
                            '<span><b>' + QUILocale.get(lg, 'controls.categories.panel.private.title') + '</b></span>' +
                            '</div>'
                        );

                        new CategorySelect({
                            events: {
                                onChange: function (catIds) {
                                    publicCatIds = catIds;
                                }
                            }
                        }).inject(
                            Content.getElement(
                                '.pcsg-gpm-passwords-panel-categories-public'
                            )
                        );

                        new CategorySelectPrivate({
                            events: {
                                onChange: function (catIds) {
                                    privateCatIds = catIds;
                                }
                            }
                        }).inject(
                            Content.getElement(
                                '.pcsg-gpm-passwords-panel-categories-private'
                            )
                        );

                    },
                    onSubmit: FuncSubmit,
                    onClose : function () {
                        self.Loader.hide();
                    }
                }
            });

            Popup.open();
        },

        /**
         * Shows info on category filter
         */
        $showCategoryInfo: function () {
            if (!this.$SearchParams.categoryId && !this.$SearchParams.categoryIdPrivate) {
                this.$InfoElm.setStyle('display', 'none');
                this.resize();
                return;
            }

            var self = this;

            this.Loader.show();

            // private category search
            if (this.$SearchParams.categoryIdPrivate) {
                Categories.getPrivate([this.$SearchParams.categoryIdPrivate]).then(function (categories) {
                    self.$InfoElm.set(
                        'html',
                        '<span>' +
                        QUILocale.get(lg, 'controls.gpm.passwords.panel.category.private.info', {
                            category: categories[0].title
                        }) +
                        '</span>'
                    );

                    self.$InfoElm.setStyle('display', '');
                    self.resize();

                    self.Loader.hide();
                });

                return;
            }

            Categories.getPublic([this.$SearchParams.categoryId]).then(function (categories) {
                self.$InfoElm.set(
                    'html',
                    '<span>' +
                    QUILocale.get(lg, 'controls.gpm.passwords.panel.category.info', {
                        category: categories[0].title
                    }) +
                    '</span>'
                );

                self.$InfoElm.setStyle('display', '');
                self.resize();

                self.Loader.hide();
            });
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
                                    self.$SearchParams.search                   = SearchData;
                                    self.$SearchInput.getElement('input').value = SearchData.searchterm;
                                    self.$searchUsed                            = true;
                                    self.refresh();
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
         * Show password owner and how the user has access to the password
         *
         * @param {int} row - row in grid
         */
        $showAccessInfoPopup: function (row) {
            var self       = this;
            var Password   = this.$Grid.getDataByRow(row);
            var AccessInfo = {};

            this.Loader.show();

            // open popup
            var Popup = new QUIConfirm({
                'class'    : 'pcsg-gpm-passwords-panel-access-info',
                'maxHeight': 400,
                maxWidth   : 500,
                'autoclose': true,

                'information': false,
                'title'      : QUILocale.get(lg, 'controls.gpm.passwords.panel.accessinfo.title', {
                    passwordId   : Password.id,
                    passwordTitle: Password.title
                }),
                'texticon'   : 'fa fa-key',
                'icon'       : 'fa fa-key',

                cancel_button: false,
                ok_button    : {
                    text     : false,
                    textimage: 'fa fa-check'
                },
                events       : {
                    onOpen  : function () {
                        var Content = Popup.getContent();

                        Content.set(
                            'html',
                            '<div class="pcsg-gpm-passwords-panel-access-info-owner">' +
                            '<span>' +
                            QUILocale.get(lg, 'controls.gpm.passwords.panel.accessinfo.owner') +
                            '</span>' +
                            '</div>' +
                            '<div class="pcsg-gpm-passwords-panel-access-info-access"></div>'
                        );

                        var OwnerInfoElm = new Element('div', {
                            html: '<span>' + AccessInfo.owner.name + ' (' + AccessInfo.owner.id + ')</span>'
                        }).inject(
                            Content.getElement(
                                '.pcsg-gpm-passwords-panel-access-info-owner'
                            )
                        );

                        switch (AccessInfo.owner.type) {
                            case 'user':
                                new Element('span', {
                                    'class': 'fa fa-user'
                                }).inject(OwnerInfoElm, 'top');
                                break;

                            case 'group':
                                new Element('span', {
                                    'class': 'fa fa-users'
                                }).inject(OwnerInfoElm, 'top');
                                break;
                        }

                        var SiteMap = new QUISiteMap({}).inject(
                            Content.getElement('.pcsg-gpm-passwords-panel-access-info-access')
                        );

                        var AccessItem = new QUISiteMapItem({
                            text       : QUILocale.get(
                                lg,
                                'controls.gpm.passwords.panel.accessinfo.acess'
                            ),
                            icon       : 'fa fa-key',
                            contextmenu: false,
                            hasChildren: true,
                            dragable   : false
                        });

                        SiteMap.appendChild(AccessItem);

                        if (AccessInfo.access.user) {
                            if (AccessInfo.userIsOwner) {
                                AccessItem.appendChild(new QUISiteMapItem({
                                    text       : QUILocale.get(
                                        lg,
                                        'controls.gpm.passwords.panel.accessinfo.personal.owner.access'
                                    ),
                                    icon       : 'fa fa-user',
                                    contextmenu: false,
                                    hasChildren: false,
                                    dragable   : false
                                }));
                            } else {
                                AccessItem.appendChild(new QUISiteMapItem({
                                    text       : QUILocale.get(
                                        lg,
                                        'controls.gpm.passwords.panel.accessinfo.personal.access'
                                    ),
                                    icon       : 'fa fa-user',
                                    contextmenu: false,
                                    hasChildren: false,
                                    dragable   : false
                                }));
                            }
                        }

                        for (var i = 0, len = AccessInfo.access.groups.length; i < len; i++) {
                            var Group = AccessInfo.access.groups[i];

                            AccessItem.appendChild(new QUISiteMapItem({
                                text       : Group.name + ' (' + Group.id + ')',
                                icon       : 'fa fa-users',
                                contextmenu: false,
                                hasChildren: false,
                                dragable   : false
                            }));
                        }

                        SiteMap.openAll();
                        self.Loader.hide();
                    },
                    onSubmit: function () {
                        Popup.close();
                    }
                }
            });

            Passwords.getAccessInfo(Password.id).then(
                function (accessInfoResult) {
                    AccessInfo = accessInfoResult;
                    Popup.open();
                },
                function () {
                    // @todo error
                }
            );
        },

        /**
         * Show users and groups a password is shared with
         *
         * @param {int} row - row in grid
         */
        $showSharedWithPopup: function (row) {
            var self        = this;
            var Password    = this.$Grid.getDataByRow(row);
            var shareActors = [];

            this.Loader.show();

            // open popup
            var Popup = new QUIConfirm({
                'class'    : 'pcsg-gpm-passwords-panel-share-info',
                'maxHeight': 700,
                maxWidth   : 350,
                'autoclose': true,

                'information': QUILocale.get(lg, 'controls.gpm.passwords.panel.shareinfo.info', {
                    passwordId   : Password.id,
                    passwordTitle: Password.title
                }),
                'title'      : QUILocale.get(lg, 'controls.gpm.passwords.panel.shareinfo.title'),
                'texticon'   : 'fa fa-share-alt',
                'icon'       : 'fa fa-share-alt',

                cancel_button: false,
                ok_button    : {
                    text     : false,
                    textimage: 'fa fa-check'
                },
                events       : {
                    onOpen  : function () {
                        var Content = Popup.getContent();
                        var SiteMap = new QUISiteMap({}).inject(Content);

                        var UsersItem = new QUISiteMapItem({
                            text       : QUILocale.get(lg, 'controls.gpm.passwords.panel.shareinfo.users.text'),
                            icon       : 'fa fa-user',
                            contextmenu: false,
                            hasChildren: true,
                            dragable   : false
                        });

                        SiteMap.appendChild(UsersItem);

                        var GroupsItem = new QUISiteMapItem({
                            text       : QUILocale.get(lg, 'controls.gpm.passwords.panel.shareinfo.groups.text'),
                            icon       : 'fa fa-users',
                            contextmenu: false,
                            hasChildren: true,
                            dragable   : false
                        });

                        SiteMap.appendChild(GroupsItem);

                        var i, len;

                        // users
                        for (i = 0, len = shareActors.users.length; i < len; i++) {
                            var ShareUser = shareActors.users[i];

                            UsersItem.appendChild(new QUISiteMapItem({
                                text       : ShareUser.name + ' (' + ShareUser.id + ')',
                                icon       : 'fa fa-user',
                                contextmenu: false,
                                hasChildren: false,
                                dragable   : false
                            }));
                        }

                        // groups
                        for (i = 0, len = shareActors.groups.length; i < len; i++) {
                            var ShareGroup = shareActors.groups[i];

                            GroupsItem.appendChild(new QUISiteMapItem({
                                text       : ShareGroup.name + ' (' + ShareGroup.id + ')',
                                icon       : 'fa fa-users',
                                contextmenu: false,
                                hasChildren: false,
                                dragable   : false
                            }));
                        }

                        SiteMap.openAll();
                        self.Loader.hide();
                    },
                    onSubmit: function () {
                        Popup.close();
                    }
                }
            });

            Passwords.getShareUsersAndGroups(Password.id).then(
                function (shareUsersGroups) {
                    shareActors = shareUsersGroups;
                    Popup.open();
                },
                function () {
                    // @todo getShareData error
                }
            );
        },

        /**
         * Initiates registration for first-time users
         */
        $initialRegistration: function () {
            var self = this;

            Promise.all([
                Actors.canUsePasswordManager(),
                Authentication.getDefaultAuthPluginId()
            ]).then(function (result) {
                var canUse              = result[0];
                var defaultAuthPluginId = result[1];

                if (canUse) {
                    return;
                }

                self.getButtons('add').disable();

                var FuncSubmit = function () {
                    var Content       = Popup.getContent();
                    var PasswordInput = Content.getElement('input');

                    var password = PasswordInput.value.trim();

                    if (password === '') {
                        PasswordInput.value = '';
                        PasswordInput.focus();
                        return;
                    }

                    Popup.Loader.show();

                    Authentication.registerUser(
                        defaultAuthPluginId, password
                    ).then(function (RecoveryData) {
                        if (!RecoveryData) {
                            Popup.Loader.hide();
                            PasswordInput.value = '';
                            PasswordInput.focus();

                            return;
                        }

                        new RecoveryCodeWindow({
                            RecoveryCodeData: RecoveryData,
                            events          : {
                                onClose: function () {
                                    RecoveryData = null;
                                    Popup.close();
                                    self.getButtons('add').enable();
                                }
                            }
                        }).open();

                        Popup.Loader.hide();
                    });
                };

                // open popup
                var Popup = new QUIPopup({
                    title             : QUILocale.get(
                        lg, 'controls.gpm.passwords.panel.initialRegistration.title'
                    ),
                    'class'           : 'pcsg-passwords-panel-initialRegistration',
                    maxHeight         : 375,
                    maxWidth          : 600,
                    closeButton       : false,
                    backgroundClosable: false,
                    titleCloseButton  : false,
                    events            : {
                        onOpen: function () {
                            var Content = Popup.getContent();

                            Content.set(
                                'html',
                                '<h1>' +
                                QUILocale.get(lg,
                                    'controls.gpm.passwords.panel.initialRegistration.header'
                                ) +
                                '</h1>' +
                                '<p>' +
                                QUILocale.get(lg,
                                    'controls.gpm.passwords.panel.initialRegistration.info'
                                ) +
                                '</p>' +
                                '<label>' +
                                '<span>' +
                                QUILocale.get(lg,
                                    'controls.gpm.passwords.panel.initialRegistration.label'
                                ) +
                                '</span>' +
                                '<input type="password">' +
                                '</label>'
                            );

                            var Input = Content.getElement('input');

                            Input.addEvents({
                                keyup: function (event) {
                                    if (event.code === 13) {
                                        FuncSubmit();
                                        this.blur();
                                    }
                                }
                            });

                            Input.focus();
                        }
                    }
                });

                var FuncOpenPopup = function () {
                    Popup.open();

                    Popup.addButton(new QUIButton({
                        text  : QUILocale.get(lg, 'controls.gpm.passwords.panel.initialRegistration.btn'),
                        alt   : QUILocale.get(lg, 'controls.gpm.passwords.panel.initialRegistration.btn'),
                        title : QUILocale.get(lg, 'controls.gpm.passwords.panel.initialRegistration.btn'),
                        events: {
                            onClick: FuncSubmit
                        }
                    }));
                };

                var passwordChangePopups = QUI.Controls.getByType(
                    'controls/users/password/Password'
                );

                if (!passwordChangePopups.length) {
                    FuncOpenPopup();
                    return;
                }

                passwordChangePopups[0].addEvents({
                    onClose: function () {
                        FuncOpenPopup();
                    }
                });
            });
        },

        /**
         * Get password grid data by password ID
         *
         * @param {number} passwordId
         * @return {false|Object}
         */
        $getRowDataByPasswordId: function (passwordId) {
            var data = this.$Grid.getData();

            for (var i = 0, len = data.length; i < len; i++) {
                if (data[i].id === passwordId) {
                    return data[i];
                }
            }

            return false;
        }
    });
});