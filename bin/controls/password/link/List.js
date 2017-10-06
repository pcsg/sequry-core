/**
 * List all PasswordLinks of a Password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/link/List
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onClose [this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/link/List', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',

    'controls/grid/Grid',
    'package/pcsg/grouppasswordmanager/bin/controls/password/link/Create',
    'package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons',

    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Passwords',

    'css!package/pcsg/grouppasswordmanager/bin/controls/password/link/List.css'

], function (QUI, QUIControl, QUILoader, QUIButton, QUIPopup, QUIConfirm, Grid, PasswordLinkCreate,
             InputButtons, QUILocale, Mustache, Passwords) {
    "use strict";

    var lg              = 'pcsg/grouppasswordmanager';
    var UrlButtonParser = new InputButtons({
        openurl: true
    });

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/link/List',

        Binds: [
            '$onCreate',
            '$listRefresh',
            '$setGridData',
            'refresh',
            '$addLink',
            '$showCalls',
            '$showUrl'
        ],

        options: {
            passwordId: false // passwordId
        },

        initialize: function (options) {
            this.parent(options);

            this.$Grid          = null;
            this.$GridContainer = null;
            this.Loader         = new QUILoader();

            this.addEvents({
                onResize: this.$onResize,
                onInject: this.$onInject
            });
        },

        /**
         * Event: onResize
         */
        $onResize: function () {
            if (this.$GridContainer && this.$Grid) {
                var size = this.$GridContainer.getSize();

                this.$Grid.setHeight(size.y);
                this.$Grid.resize();
            }
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            var self = this;

            this.$Elm.addClass('pcsg-gpm-password-linklist');
            this.Loader.inject(this.$Elm);

            this.$GridContainer = new Element('div', {
                'class': 'pcsg-gpm-password-linklist-grid'
            }).inject(this.$Elm);

            this.$Grid = new Grid(this.$GridContainer, {
                pagination       : true,
                selectable       : true,
                serverSort       : false,
                multipleSelection: true,
                buttons          : [{
                    name     : 'add',
                    text     : QUILocale.get(lg, 'controls.password.linklist.tbl.btn.add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$addLink
                    }
                }, {
                    name     : 'calls',
                    text     : QUILocale.get(lg, 'controls.password.linklist.tbl.btn.calls'),
                    textimage: 'fa fa-hand-pointer-o',
                    events   : {
                        onClick: this.$showCalls
                    }
                }, {
                    name     : 'deactivate',
                    text     : QUILocale.get(lg, 'controls.password.linklist.tbl.btn.deactivate'),
                    textimage: 'fa fa-square-o',
                    events   : {
                        onClick: function () {
                            self.$deactivate(
                                self.$Grid.getSelectedData()[0].id
                            );
                        }
                    }
                }],

                columnModel: [{
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.id'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.validUntil'),
                    dataIndex: 'validUntil',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.callCount'),
                    dataIndex: 'callCount',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.passwordProtected'),
                    dataIndex: 'passwordProtected',
                    dataType : 'node',
                    width    : 125
                }, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.active'),
                    dataIndex: 'active',
                    dataType : 'node',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.createUser'),
                    dataIndex: 'createUser',
                    dataType : 'string',
                    width    : 275
                }, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.link'),
                    dataIndex: 'link',
                    dataType : 'node',
                    width    : 75
                }, {
                    dataIndex: 'calls',
                    hidden   : true
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.$listRefresh,
                onClick   : function () {
                    var selectedCount = self.$Grid.getSelectedData().length,
                        TableButtons  = self.$Grid.getAttribute('buttons');

                    if (selectedCount == 1) {
                        TableButtons.calls.enable();
                        TableButtons.deactivate.enable();
                    } else {
                        TableButtons.calls.disable();
                        TableButtons.deactivate.disable();
                    }
                },
                onDblClick: this.$showCalls
            });

            this.resize();
            this.refresh();
            this.fireEvent('loaded', [this]);
        },

        /**
         * Refresh control
         */
        refresh: function () {
            this.$Grid.refresh();
        },

        /**
         * Refresh PasswordLink list
         *
         * @returns {Promise}
         */
        $listRefresh: function () {
            var self = this;

            this.Loader.show();

            var TableButtons = self.$Grid.getAttribute('buttons');
            TableButtons.calls.disable();
            TableButtons.deactivate.disable();

            Passwords.getLinkList(this.getAttribute('passwordId')).then(function (list) {
                self.Loader.hide();
                self.$setGridData(list);
            }, function () {
                self.fireEvent('close', [self]);
            });
        },

        /**
         * Set list data to grid
         *
         * @param {Array} list
         */
        $setGridData: function (list) {
            var self      = this;
            var Row, data = [];

            var FuncOnUrlLinkClick = function () {
                self.$showUrl(this.get('data-url'));
            };

            for (var i = 0, len = list.length; i < len; i++) {
                var Data = list[i];

                Row = {
                    id: Data.id
                };

                if (Data.validUntil) {
                    Row.validUntil = Data.validUntil;
                } else {
                    Row.validUntil = QUILocale.get(lg,
                        'controls.password.linklist.tbl.validUntil.infinite'
                    );
                }

                // calls
                Row.callCount = Data.callCount;

                if (Data.maxCalls) {
                    Row.callCount += ' / ' + Data.maxCalls;
                }

                Row.calls = Data.calls;

                // active status
                if (Data.active) {
                    Row.active = new Element('span', {
                        'class': 'fa fa-check',
                        title  : QUILocale.get(lg, 'controls.password.linklist.tbl.active'),
                        alt    : QUILocale.get(lg, 'controls.password.linklist.tbl.active')
                    });
                } else {
                    Row.active = new Element('span', {
                        'class': 'fa fa-close',
                        title  : QUILocale.get(lg, 'controls.password.linklist.tbl.inactive'),
                        alt    : QUILocale.get(lg, 'controls.password.linklist.tbl.inactive')
                    });
                }

                if (Data.active) {
                    Row.link = new Element('div', {
                        'class'   : 'pcsg-gpm-password-linklist-url-link',
                        'data-url': Data.link,
                        html      : '<span class="fa fa-link"></span>' +
                        '<span>' + QUILocale.get(lg,
                            'controls.password.linklist.tbl.link.text'
                        ) + '</span>',
                        events    : {
                            click: FuncOnUrlLinkClick
                        }
                    });
                } else {
                    Row.link = new Element('span', {
                        html: '-'
                    });
                }

                // password protection status
                if (Data.password) {
                    Row.passwordProtected = new Element('span', {
                        'class': 'fa fa-check',
                        title  : QUILocale.get(lg, 'controls.password.linklist.tbl.password'),
                        alt    : QUILocale.get(lg, 'controls.password.linklist.tbl.password')
                    });
                } else {
                    Row.passwordProtected = new Element('span', {
                        'class': 'fa fa-close',
                        title  : QUILocale.get(lg, 'controls.password.linklist.tbl.no_password'),
                        alt    : QUILocale.get(lg, 'controls.password.linklist.tbl.no_password')
                    });
                }

                // createUser
                Row.createUser = Data.createDate +
                    ' (' + Data.createUserName +
                    ' - #' + Data.createUserId + ')';

                data.push(Row);
            }

            this.$Grid.setData({
                data : data,
                page : 1,
                total: 1
            });
        },

        /**
         * Add new PasswordLink
         */
        $addLink: function () {
            var self = this;
            var LinkCreateControl;

            // open popup
            var Popup = new QUIPopup({
                icon       : 'fa fa-plus',
                title      : QUILocale.get(
                    lg, 'controls.password.linklist.add.popup.title'
                ),
                maxHeight  : 800,
                maxWidth   : 700,
                events     : {
                    onOpen: function () {
                        Popup.Loader.show();

                        LinkCreateControl = new PasswordLinkCreate({
                            passwordId   : self.getAttribute('passwordId'),
                            showSubmitBtn: false,
                            events: {
                                onLoaded: function() {
                                    Popup.Loader.hide();
                                }
                            }
                        }).inject(Popup.getContent());
                    }
                },
                closeButton: true
            });

            Popup.open();

            Popup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.password.linklist.add.popup.btn.submit.text'),
                alt   : QUILocale.get(lg, 'controls.password.linklist.add.popup.btn.submit'),
                title : QUILocale.get(lg, 'controls.password.linklist.add.popup.btn.submit'),
                events: {
                    onClick: function () {
                        Popup.Loader.show();

                        LinkCreateControl.submit().then(function () {
                            Popup.close();
                            self.refresh();
                        }, function () {
                            Popup.Loader.hide();
                        });
                    }
                }
            }));
        },

        /**
         * Show list of single calls for a PasswordLink
         */
        $showCalls: function () {
            var calls = this.$Grid.getSelectedData()[0].calls;

            var FuncOnOpen = function () {
                var Content = Popup.getContent();

                var GridParent = new Element('div', {
                    'class': 'pcsg-gpm-password-link-list-calls-grid'
                }).inject(Content);

                var CallsGrid = new Grid(GridParent, {
                    pagination       : false,
                    selectable       : false,
                    serverSort       : false,
                    multipleSelection: false,

                    columnModel: [{
                        header   : QUILocale.get(lg, 'controls.password.linklist.calls.tbl.header.date'),
                        dataIndex: 'date',
                        dataType : 'string',
                        width    : 150
                    }, {
                        header   : QUILocale.get(lg, 'controls.password.linklist.calls.tbl.header.ipAdress'),
                        dataIndex: 'ipAdress',
                        dataType : 'string',
                        width    : 150
                    }]
                });

                var callsData = [];

                for (var i = 0, len = calls.length; i < len; i++) {
                    var Call = calls[i];
                    var Row  = {
                        date    : Call.date,
                        ipAdress: Call.REMOTE_ADDR || '-'
                    };

                    callsData.push(Row);
                }

                CallsGrid.setData({
                    data : callsData,
                    page : 1,
                    total: 1
                });

                CallsGrid.setHeight(GridParent.getSize().y);
                CallsGrid.resize();
            };

            // open popup
            var Popup = new QUIPopup({
                icon       : 'fa fa-hand-pointer-o',
                title      : QUILocale.get(
                    lg, 'controls.password.linklist.calls.popup.title'
                ),
                maxHeight  : 650,
                maxWidth   : 350,
                events     : {
                    onOpen: FuncOnOpen
                },
                closeButton: true
            });

            Popup.open();
        },

        /**
         * Show password link URL
         *
         * @param {Number} linkId
         */
        $deactivate: function (linkId) {
            var self = this;

            new QUIConfirm({
                maxHeight    : 225,
                icon         : 'fa fa-square-o',
                texticon     : 'fa fa-square-o',
                title        : QUILocale.get(lg, 'controls.password.linklist.deactivate.title'),
                information  : QUILocale.get(lg, 'controls.password.linklist.deactivate.information', {
                    linkId: linkId
                }),
                text         : QUILocale.get(lg, 'controls.password.linklist.deactivate.text'),
                ok_button    : {
                    text     : QUILocale.get(lg, 'controls.password.linklist.deactivate.confirm'),
                    textimage: false
                },
                events       : {
                    onSubmit: function (Confirm) {
                        Confirm.Loader.show();

                        Passwords.deactivateLink(linkId).then(function(success) {
                            if (success) {
                                Confirm.close();
                                self.refresh();
                                return;
                            }

                            Confirm.Loader.hide();
                        }, function() {
                            Confirm.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        /**
         * Show password link URL
         *
         * @param {String} url
         */
        $showUrl: function (url) {
            new QUIConfirm({
                maxHeight    : 175,
                cancel_button: false,
                icon         : 'fa fa-link',
                title        : QUILocale.get(lg, 'controls.password.linklist.showurl.title'),
                ok_button    : {
                    text     : QUILocale.get(lg, 'controls.password.linklist.showurl.ok'),
                    textimage: false
                },
                events       : {
                    onOpen: function (Confirm) {
                        Confirm.setContent(
                            '<input type="text" value="' + url + '" class="pcsg-gpm-password-linklist-url">'
                        );

                        var UrlInput = Confirm.getContent().getElement(
                            'input'
                        );

                        UrlInput.addEvent('focus', function (event) {
                            event.target.select();
                        });

                        UrlInput.select();

                        UrlButtonParser.parse(UrlInput);
                    }
                }
            }).open();
        }
    });
});
