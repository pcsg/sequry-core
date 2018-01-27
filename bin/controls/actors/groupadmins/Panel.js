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

    'Locale',
    'Mustache',

    'text!package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/Panel.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/Panel.css'

], function (QUIPanel, QUILoader, QUIConfirm, QUIButton, Grid, QUILocale, Mustache, template) {
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
            '$create',
            '$toggleActiveStatus',
            '$managePackages',
            '$delete',
            '$editBundle',
            'refresh',
            '$openUserPanel',
            '$sendMail'
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
                name     : 'unlock',
                text     : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.btn.create'),
                textimage: 'fa fa-unlock',
                events   : {
                    onClick: function () {
                        self.$create();
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
                '.pcsg-gpm-actors-groupadmins-panel-manager-table'
            );

            this.$Grid = new Grid(this.$GridParent, {
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'userId',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.header.name'),
                    dataIndex: 'name',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.header.group'),
                    dataIndex: 'group',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.header.securityclass'),
                    dataIndex: 'securityclass',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.header.unlock'),
                    dataIndex: 'unlock',
                    dataType : 'node',
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
                onClick   : function (event) {
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

            InviteCodes.getList(GridParams).then(function (ResultData) {
                self.Loader.hide();
                self.$setGridData(ResultData);
            });
        },

        /**
         * Set license data to grid
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            var textUnused       = QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.status.unused');
            var textUnlimited    = QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.validUntil.unlimited');
            var textInvalid      = QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.status.invalid');
            var textUserNotExist = QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.user.not_exist');

            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Row = GridData.data[i];

                if (!Row.email) {
                    Row.email = '-';
                }

                var StatusElm = new Element('span', {
                    'class': 'pcsg-gpm-actors-groupadmins-panel-manager-tbl-status'
                });

                if (!Row.valid) {
                    StatusElm.set('html', textInvalid);
                    StatusElm.addClass('pcsg-gpm-actors-groupadmins-panel-manager-tbl-status-invalid');
                } else if (!Row.useDate) {
                    StatusElm.set('html', textUnused);
                    StatusElm.addClass('pcsg-gpm-actors-groupadmins-panel-manager-tbl-status-unused');
                } else {
                    StatusElm.set('html', QUILocale.get(lg, 'controls.actors.groupadmins.Panel.tbl.status.used', {
                        useDate: Row.useDate
                    }));
                    StatusElm.addClass('pcsg-gpm-actors-groupadmins-panel-manager-tbl-status-used');
                }

                Row.status = StatusElm;

                if (!Row.validUntilDate) {
                    Row.validUntilDate = textUnlimited;
                }

                if (!Row.userId) {
                    if (Row.useDate) {
                        Row.user = new Element('span', {
                            'class': 'pcsg-gpm-actors-groupadmins-panel-manager-tbl-user-not_exist',
                            html   : textUserNotExist
                        });
                    } else {
                        Row.user = new Element('span', {html: '-'});
                    }
                } else {
                    Row.user = new Element('div', {
                        'class': 'pcsg-gpm-actors-groupadmins-panel-manager-tbl-user',
                        html   : Row.username
                    });
                }

                if (!Row.title) {
                    Row.title = '-';
                }

                var MailSentElm = new Element('span', {
                    'class': 'fa'
                });

                if (!Row.mailSent) {
                    MailSentElm.addClass('fa-close');
                } else {
                    MailSentElm.addClass('fa-check');
                }

                Row.mailSent = MailSentElm;
            }

            this.$Grid.setData(GridData);
        },

        /**
         * Create new InviteCode
         *
         * @param {Boolean} [quickCreate]
         */
        $create: function (quickCreate) {
            var self = this;

            quickCreate = quickCreate || false;

            if (quickCreate) {
                InviteCodes.create({}).then(function (inviteCodeId) {
                    if (!inviteCodeId) {
                        return;
                    }

                    self.refresh();
                });

                return;
            }

            var FuncSubmit = function () {
                var Content = Popup.getContent();
                var Form    = Content.getElement('form');

                Popup.Loader.show();

                InviteCodes.create(QUIFormUtils.getFormData(Form)).then(function (inviteCodeId) {
                    if (!inviteCodeId) {
                        Popup.Loader.hide();
                        return;
                    }

                    self.refresh();
                    Popup.close();
                });
            };

            // open popup
            var lgPrefix = 'controls.actors.groupadmins.Panel.create.template.';

            var Popup = new QUIPopup({
                icon       : 'fa fa-plus',
                title      : QUILocale.get(
                    lg, 'controls.actors.groupadmins.Panel.create.popup.title'
                ),
                maxHeight  : 450,
                maxWidth   : 450,
                events     : {
                    onOpen: function () {
                        var Content = Popup.getContent();
                        var Form    = Content.getElement('form');

                        Form.addEvent('submit', function (event) {
                            event.stop();
                            FuncSubmit();
                        });

                        var EmailInput       = Content.getElement('input[name="email"]');
                        var SendMailCheckbox = Content.getElement('input[name="sendmail"]');

                        EmailInput.addEvent('keyup', function (event) {
                            if (event.target.value.trim() === '') {
                                SendMailCheckbox.checked  = false;
                                SendMailCheckbox.disabled = true;

                                return;
                            }

                            SendMailCheckbox.disabled = false;
                        });

                        Content.getElement('input[name="title"]').focus();
                    }
                },
                closeButton: true,
                content    : Mustache.render(templateCreate, {
                    labelTitle   : QUILocale.get(lg, lgPrefix + 'labelTitle'),
                    labelEmail   : QUILocale.get(lg, lgPrefix + 'labelEmail'),
                    labelDate    : QUILocale.get(lg, lgPrefix + 'labelDate'),
                    labelSendMail: QUILocale.get(lg, lgPrefix + 'labelSendMail'),
                    labelAmount  : QUILocale.get(lg, lgPrefix + 'labelAmount')
                })
            });

            Popup.open();

            Popup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.create.popup.btn.confirm_text'),
                alt   : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.create.popup.btn.confirm'),
                title : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.create.popup.btn.confirm'),
                events: {
                    onClick: FuncSubmit
                }
            }));
        },

        /**
         * Remove all selected licenses
         */
        $delete: function () {
            var self       = this;
            var deleteData = [];
            var deleteIds  = [];
            var rows       = this.$Grid.getSelectedData();

            for (var i = 0, len = rows.length; i < len; i++) {
                deleteData.push(
                    rows[i].title + ' (ID: #' + rows[i].id + ')'
                );

                deleteIds.push(rows[i].id);
            }

            // open popup
            var Popup = new QUIConfirm({
                'maxHeight': 300,
                'autoclose': false,

                'information': QUILocale.get(
                    lg,
                    'controls.actors.groupadmins.Panel.delete.popup.info', {
                        codes: deleteData.join('<br/>')
                    }
                ),
                'title'      : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.delete.popup.title'),
                'texticon'   : 'fa fa-trash',
                text         : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.delete.popup.title'),
                'icon'       : 'fa fa-trash',

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

                        InviteCodes.delete(deleteIds).then(function (success) {
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
         * Send InviteCodes via Mail
         */
        $sendMail: function () {
            var self        = this;
            var sendMailIds = [];
            var rows        = this.$Grid.getSelectedData();

            for (var i = 0, len = rows.length; i < len; i++) {
                sendMailIds.push(rows[i].id);
            }

            // open popup
            var Popup = new QUIConfirm({
                'maxHeight': 300,
                'autoclose': false,

                'information': QUILocale.get(lg, 'controls.actors.groupadmins.Panel.sendmail.popup.info'),
                'title'      : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.sendmail.popup.title'),
                'text'       : QUILocale.get(lg, 'controls.actors.groupadmins.Panel.sendmail.popup.title'),
                'texticon'   : 'fa fa-envelope-o',
                'icon'       : 'fa fa-envelope-o',

                cancel_button: {
                    text     : false,
                    textimage: 'fa fa-remove'
                },
                ok_button    : {
                    text     : false,
                    textimage: 'fa fa-check'
                },
                events       : {
                    onOpen  : function () {
                        var Content = Popup.getContent();

                        new Element('label', {
                            'class': 'pcsg-gpm-actors-groupadmins-panel-manager-sendmail-resend',
                            html   : '<span>' +
                            QUILocale.get(lg, 'controls.actors.groupadmins.Panel.sendmail.popup.label.resend') +
                            '</span>' +
                            '<input type="checkbox" name="resend"/>'
                        }).inject(Content);
                    },
                    onSubmit: function () {
                        Popup.Loader.show();

                        var SendMailInput = Popup.getContent().getElement(
                            'input[name="resend"]'
                        );

                        InviteCodes.sendMail(sendMailIds, SendMailInput.checked).then(function (success) {
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
