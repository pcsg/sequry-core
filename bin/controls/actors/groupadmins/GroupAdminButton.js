/**
 * Button for Group administrators
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/GroupAdminButton
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/GroupAdminButton', [

    'qui/controls/buttons/Button',

    'Locale'

], function (QUIButton, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIButton,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/groupadmins/GroupAdminButton',

        Binds: [
            'actorSearch',
            '$onSearchButtonClick'
        ],

        options: {
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
            console.log("open Group Admin Panel");
        }
    });
});