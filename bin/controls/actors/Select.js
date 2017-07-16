/**
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/Select
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/elements/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup
 * @require Ajax
 * @require Locale
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/Select', [

    'qui/QUI',
    'qui/controls/elements/Select',

    'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup',

    'Ajax',
    'Locale'

], function (QUI, QUIElementSelect, SelectTablePopup, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIElementSelect,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/Select',

        Binds: [
            'actorSearch',
            '$onSearchButtonClick'
        ],

        options: {
            actorType      : 'all', // "users", "groups", "all"
            securityClassId: false,  // id of security class this actors are searched for
            Search         : false,
            filterActorIds : []   // IDs of actors that are filtered from list (entries must have
            // prefix "u" (user) or "g" (group)
        },

        initialize: function (options) {
            this.parent(options);

            switch (this.getAttribute('actorType')) {
                case 'users':
                    this.setAttribute('icon', 'fa fa-user');
                    this.setAttribute(
                        'placeholder',
                        QUILocale.get(lg, 'actors.select.placeholder.users')
                    );
                    break;

                case 'groups':
                    this.setAttribute('icon', 'fa fa-users');
                    this.setAttribute(
                        'placeholder',
                        QUILocale.get(lg, 'actors.select.placeholder.groups')
                    );
                    break;

                default:
                    this.setAttribute('icon', 'fa fa-users');
                    this.setAttribute(
                        'placeholder',
                        QUILocale.get(lg, 'actors.select.placeholder.both')
                    );
            }

            if (this.getAttribute('Search') === false) {
                this.setAttribute('Search', this.actorSearch);
            }

            this.setAttribute('child', 'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectItem');

            this.addEvents({
                onSearchButtonClick: this.$onSearchButtonClick
            });
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
                QUIAjax.get('package_pcsg_grouppasswordmanager_ajax_actors_suggestSearch', resolve, {
                    'package'      : 'pcsg/grouppasswordmanager',
                    type           : self.getAttribute('actorType'),
                    search         : value,
                    securityClassId: self.getAttribute('securityClassId'),
                    limit          : 10
                });
            });
        },

        /**
         * Return actors
         *
         * @returns {Array}
         */
        getActors: function () {
            var actors = [];

            // users
            var actorIds = this.getValue();

            if (actorIds === '') {
                return actors;
            }

            actorIds = actorIds.split(',');

            for (var i = 0, len = actorIds.length; i < len; i++) {
                var id = actorIds[i];

                // is user
                if (id.charAt(0) === 'u') {
                    actors.push({
                        id  : id.substr(1),
                        type: 'user'
                    });

                    continue;
                }

                // is group
                actors.push({
                    id  : id.substr(1),
                    type: 'group'
                });
            }

            return actors;
        },

        /**
         * Event: onSearchButtonClick
         */
        $onSearchButtonClick: function () {
            var self           = this;
            var filterActorIds = Array.clone(this.getAttribute('filterActorIds'));

            if (this.getValue() !== '') {
                filterActorIds.combine(this.getValue().split(','));
            }

            new SelectTablePopup({
                securityClassId: this.getAttribute('securityClassId'),
                multiselect    : this.getAttribute('multiple'),
                actorType      : this.getAttribute('actorType'),
                filterActorIds : filterActorIds,
                events         : {
                    onSubmit: function (ids, actorType) {
                        var prefix = 'u';

                        if (actorType === 'groups') {
                            prefix = 'g';
                        }

                        for (var i = 0, len = ids.length; i < len; i++) {
                            self.addItem(prefix + ids[i]);
                        }
                    }
                }
            }).open();
        }
    });
});