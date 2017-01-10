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
    'qui/controls/buttons/Seperator',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',

    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',

    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Passwords',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Create',
    'package/pcsg/grouppasswordmanager/bin/controls/password/View',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Share',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Edit',
    'package/pcsg/grouppasswordmanager/bin/controls/passwords/Search',
    'package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate',
    'package/pcsg/grouppasswordmanager/bin/controls/password/Authenticate',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/passwords/Panel.css'

], function (QUI, QUIPanel, QUISeparator, QUIButton, QUILoader, QUIPopup, QUIConfirm,
             QUISiteMap, QUISiteMapItem, Grid, PasswordHandler, PasswordCreate,
             PasswordView, PasswordShare, PasswordEdit, PasswordSearch,
             AuthenticationControl, PasswordAuthentication, Ajax, QUILocale) {
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

        initialize: function (options) {
            this.setAttribute('title', QUILocale.get(lg, 'passwords.panel.title'));

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

            this.addButton(new QUISeparator());

            this.addButton({
                name     : 'add',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.add'),
                textimage: 'fa fa-plus',
                events   : {
                    onClick: this.createPassword
                }
            });

            this.addButton(new QUISeparator());

            this.addButton({
                name     : 'view',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.view'),
                textimage: 'fa fa-eye',
                events   : {
                    onClick: function () {
                        self.viewPassword(
                            self.$Grid.getSelectedData()[0].id
                        );
                    }
                }
            });

            this.addButton(new QUISeparator());

            this.addButton({
                name     : 'share',
                text     : QUILocale.get(lg, 'controls.gpm.passwords.btn.share'),
                textimage: 'fa fa-share-alt',
                events   : {
                    onClick: function () {
                        self.sharePassword(
                            self.$Grid.getSelectedData()[0].id
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
                        self.editPassword(
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
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.gpm.passwords.panel.tbl.header.accesstype'),
                    dataIndex: 'accessType',
                    dataType : 'node',
                    width    : 50
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
                    self.viewPassword(
                        self.$Grid.getSelectedData()[0].id,
                        self.$Grid.getSelectedData()[0].securityClassId
                    );
                },
                onClick   : function () {
                    var Data = self.$Grid.getSelectedData()[0];

                    self.getButtons('view').enable();

                    if (Data.isOwner) {
                        self.getButtons('delete').enable();
                        self.getButtons('edit').enable();
                        self.getButtons('share').enable();
                    }
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

        /**
         * Set password data to grid table
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            var Row;
            var self = this;

            this.getButtons('delete').disable();
            this.getButtons('view').disable();
            this.getButtons('edit').disable();
            this.getButtons('share').disable();

            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Data = GridData.data[i];

                Row = Data;

                switch (Data.access) {
                    case 'user':
                        Row.accessType = new Element('span', {
                            'class': 'fa fa-user',
                            styles : {
                                'text-align'   : 'center',
                                'padding-right': 5,
                                width          : '100%'
                            }
                        });
                        break;

                    case 'group':
                        Row.accessType = new Element('span', {
                            'class': 'fa fa-users',
                            styles : {
                                'text-align'   : 'center',
                                'padding-right': 5,
                                width          : '100%'
                            }
                        });
                        break;
                }

                if (Data.sharedWithUsers || Data.sharedWithGroups) {
                    Row.shared = new Element('div', {
                        'class'   : 'pcsg-gpm-passwords-panel-grid-shared',
                        'data-row': i,
                        events    : {
                            click: function (event) {
                                self.$showSharedWithPopup(
                                    this.getProperty('data-row')
                                );
                            }
                        }
                    });

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
                }
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
         */
        viewPassword: function (passwordId) {
            var self = this;

            this.Loader.show();

            this.createSheet({
                title : QUILocale.get(lg, 'gpm.passwords.panel.view.title'),
                events: {
                    onShow : function (Sheet) {
                        Sheet.getContent().setStyle('padding', 20);

                        var View = new PasswordView({
                            passwordId: passwordId,
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
                                        Share.submit();
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
                                    Edit.destroy();
                                    Sheet.destroy();
                                    self.refresh();
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
                                        Edit.submit();
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
                'maxHeight': 750,
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

                        Content.getElement(
                            '.textbody h2'
                        ).setStyle('display', 'none');

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

            var AuthControl = new PasswordAuthentication({
                passwordId: Password.id,
                events    : {
                    onSubmit: function (AuthData) {
                        Passwords.getShareUsersAndGroups(
                            Password.id,
                            AuthData
                        ).then(
                            function (shareUsersGroups) {
                                AuthControl.destroy();
                                shareActors = shareUsersGroups;
                                Popup.open();
                            },
                            function () {
                                // @todo getShareData error
                            }
                        );
                    },
                    onClose : function () {
                        self.fireEvent('close');
                    }
                }
            });

            AuthControl.open();
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
        }
    });
});