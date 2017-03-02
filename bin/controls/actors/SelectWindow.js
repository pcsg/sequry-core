/**
 * Window for selecting actors based on security class eligibility
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/SelectWindow
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectWindow.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectWindow.css
 *
 * @event onSubmit [
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/SelectWindow', [

    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'qui/utils/Form',
    'Locale',

    'controls/grid/Grid',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',
    'package/pcsg/grouppasswordmanager/bin/controls/actors/Authenticate',

    'Ajax',

    'text!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectWindow.html',
    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/SelectWindow.css'

], function (QUIPopup, QUIButton, QUIFormUtils, QUILocale, Grid,
             AuthHandler, AuthenticationControl, Ajax, template) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectWindow',

        Binds: [
            '$onInject',
            'submit',
            '$showRecovery',
            '$print',
            '$buildContent',
            '$startSync'
        ],

        options: {
            securityClassId: false,   // security class id the actors have to be eligible for
            title          : QUILocale.get(lg, 'controls.actors.selectwindow.title')
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
                onOpen: this.$onOpen
            });

            this.$Grid          = null;
            this.$GridContainer = null;
            this.$actorType     = 'users';
        },

        /**
         * Event: onOpen
         */
        $onOpen: function () {
            var self    = this;
            var Content = this.getContent();

            this.getElm().addClass('pcsg-gpm-actors-selectwindow');

            Content.set(
                'html',
                '<div class="pcsg-gpm-actors-selectwindow-grid"></div>'
            );

            // content
            this.$GridContainer = Content.getElement(
                '.pcsg-gpm-actors-selectwindow-grid'
            );

            this.$Grid = new Grid(this.$GridContainer, {
                pagination       : true,
                selectable       : true,
                serverSort       : true,
                multipleSelection: true,
                columnModel      : [{
                    header   : QUILocale.get(lg, 'controls.actors.selectwindow.tbl.header.id'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.selectwindow.tbl.header.name'),
                    dataIndex: 'name',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'controls.actors.selectwindow.tbl.header.notice'),
                    dataIndex: 'notice',
                    dataType : 'string',
                    width    : 250
                }]
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    self.$submit();
                },
                onRefresh : this.$listRefresh
            });
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
