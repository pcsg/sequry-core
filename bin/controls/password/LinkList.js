/**
 * List all PasswordLinks of a Password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/LinkList
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/Passwords
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/LinkList.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/LinkList.css
 *
 * @event onCreate [this] - fires after a new PasswordLinkList has been successfully created
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/LinkList', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',
    'controls/grid/Grid',

    'Locale',
    'Mustache',

    'package/pcsg/grouppasswordmanager/bin/Passwords',

    'text!package/pcsg/grouppasswordmanager/bin/controls/password/LinkList.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/password/LinkList.css'

], function (QUI, QUIControl, QUILoader, QUIButton, Grid, QUILocale, Mustache, Passwords, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/LinkList',

        Binds: [
            'create'
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
                onCreate: this.$onCreate
            });
        },

        /**
         * Event: onCreate
         */
        $onCreate: function () {
            this.$Elm.addClass('pcsg-gpm-password-linklist');
            this.Loader.inject(this.$Elm);

            this.$GridContainer = new Element('div', {
                'class': 'pcsg-gpm-password-linklist-grid'
            }).inject(this.$Elm);

            this.$Grid = new Grid(this.$GridContainer, {
                pagination       : false,
                selectable       : true,
                serverSort       : false,
                multipleSelection: true,
                columnModel      : [, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.id'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'controls.password.linklist.tbl.header.validUntil'),
                    dataIndex: 'validUntil',
                    dataType : 'node',
                    width    : 200
                }]
            });

            this.$Grid.addEvents({
                onRefresh: this.$listRefresh
            });
        },

        /**
         * Refresh PasswordLink list
         *
         * @returns {Promise}
         */
        $listRefresh: function () {
            var self = this;

            this.Loader.show();

            Passwords.getLinkList(this.getAttribute('passwordId')).then(function(list) {
                self.Loader.show();
                self.$setGridData(list);
            });
        },

        /**
         * Set list data to grid
         *
         * @param {Array} list
         */
        $setGridData: function(list)
        {
            var Row;
            var data = [];
            var self = this;

            for (var i = 0, len = list.length; i < len; i++) {
                var Data = list[i];

                Row = {
                    id         : Data.id,
                    title      : Data.validUntil
                };

                if (Data.active) {
                    Row.registered   = QUILocale.get(lg, 'auth.panel.registered.yes');
                    Row.isregistered = true;
                } else {
                    Row.registered   = QUILocale.get(lg, 'auth.panel.registered.no');
                    Row.isregistered = false;
                }

                if (Data.sync) {
                    Row.sync = new QUIButton({
                        icon        : 'fa fa-exclamation-triangle',
                        authPluginId: Data.id,
                        height      : 20,
                        styles      : {
                            color        : 'red',
                            'line-height': 0
                        },
                        events      : {
                            onClick: function (Btn) {
                                self.$showSyncAuthPluginWindow(
                                    Btn.getAttribute('authPluginId')
                                );
                            }
                        }
                    }).create();
                } else {
                    Row.sync = new Element('span', {
                        html: '&nbsp;'
                    });
                }

                data.push(Row);
            }

            this.$Grid.setData({
                data : data,
                page : 1,
                total: 1
            });
        }
    });
});
