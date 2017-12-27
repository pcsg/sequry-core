/**
 * Select users or groups that are eligible for a specific SecurityClass
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/Select
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/Select', [

    'qui/controls/elements/Select',

    'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup',

    'Ajax',
    'Locale'

], function (QUIElementSelect, SelectTablePopup, QUIAjax, QUILocale) {
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
            popupInfo        : '',     // info that is shown in the ActorSelect Popup
            actorType        : 'all',  // "users", "groups", "all"
            securityClassIds : [],     // ids of security classes this actors are searched for
            Search           : false,
            filterActorIds   : [],     // IDs of actors that are filtered from list (entries must have
            // prefix "u" (user) or "g" (group)
            showEligibleOnly : false,  // show eligible only or all
            selectedActorType: 'users' // pre-selected actor type
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
                    'package'       : 'pcsg/grouppasswordmanager',
                    type            : self.getAttribute('actorType'),
                    search          : value,
                    securityClassIds: JSON.encode(self.getAttribute('securityClassIds')),
                    limit           : 10
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
                info             : this.getAttribute('popupInfo'),
                securityClassIds : this.getAttribute('securityClassIds'),
                multiselect      : this.getAttribute('multiple'),
                actorType        : this.getAttribute('actorType'),
                showEligibleOnly : this.getAttribute('showEligibleOnly'),
                selectedActorType: this.getAttribute('selectedActorType'),
                filterActorIds   : filterActorIds,
                events           : {
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