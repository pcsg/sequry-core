/**
 * Button for Group administrators
 *
 * @module package/sequry/core/bin/controls/actors/groupadmins/GroupAdminButton
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/controls/actors/groupadmins/GroupAdminButton', [

    'qui/controls/buttons/Button',

    'Locale'

], function (QUIButton, QUILocale) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIButton,
        Type   : 'package/sequry/core/bin/controls/actors/groupadmins/GroupAdminButton',

        Binds: [
            'actorSearch',
            '$onSearchButtonClick'
        ],

        options: {
            textimage   : 'fa fa-users',
            openRequests: 0 // number of open group administration requests
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttribute(
                'text',
                QUILocale.get(lg, 'controls.actors.groupadmins.GroupAdminButton.text', {
                    openRequests: this.getAttribute('openRequests')
                })
            );

            this.addEvents({
                onClick: this.$onClick
            });
        },

        /**
         * Event: onClick
         */
        $onClick: function () {
            require([
                'package/sequry/core/bin/controls/actors/groupadmins/Panel',
                'utils/Panels'
            ], function (GroupAdminPanel, PanelUtils) {
                var GroupAdminPanelInstance = new GroupAdminPanel();

                PanelUtils.openPanelInTasks(GroupAdminPanelInstance).then(function (Panel) {
                    Panel.open();
                });
            });
        }
    });
});