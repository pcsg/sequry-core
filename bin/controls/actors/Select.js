/**
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/elements/Select
 * @require Ajax
 * @require Locale
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/Select', [

    'qui/QUI',
    'qui/controls/elements/Select',
    'Ajax',
    'Locale'

], function (QUI, QUIElementSelect, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIElementSelect,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',

        Binds: [
            'actorSearch'
        ],

        options: {
            actorType      : 'all', // "users", "groups", "all"
            securityClassId: false  // id of security class this actors are searched for
        },

        initialize: function (options) {
            this.parent(options);

            switch (this.getAttribute('actorType')) {
                case 'users':
                    this.setAttribute('icon', 'fa fa-user');
                    break;

                default:
                    this.setAttribute('icon', 'fa fa-users');
            }

            this.setAttribute('Search', this.actorSearch);
            this.setAttribute('child', 'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectItem');
            this.setAttribute('searchbutton', false);

            this.setAttribute(
                'placeholder',
                QUILocale.get(lg, 'control.actor.select.placeholder')
            );
        },

        /**
         * Search actors
         *
         * @param {String} value
         * @returns {Promise}
         */
        actorSearch: function (value) {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_auth_actorSearch', resolve, {
                    'package'      : 'pcsg/grouppasswordmanager',
                    type           : self.getAttribute('actorType'),
                    search         : value,
                    securityClassId: self.getAttribute('securityClassId'),
                    limit          : 10
                });
            });
        }
    });
});