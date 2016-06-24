/**
 * Select for users and groups that are eligible as password owner and receivers
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require qui/controls/loader/Loader
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Authentication
 * @require Ajax
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect.css
 *
 * @event onLoaded
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/classes/Authentication',

    //'css!package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect.css'

], function (QUI, QUIControl, QUIButton, QUISelect, QUILoader, QUILocale, AuthHandler) {
    "use strict";

    var lg             = 'pcsg/grouppasswordmanager',
        Authentication = new AuthHandler();

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect',

        Binds: [
            '$onInject',
            'refresh',
            'submit',
            'getActor',
            'switchActorToUsers',
            'switchActorToGroups'
        ],

        options: {
            'actorType'      : 'user', // type of actor to display ("user", "group")
            'securityClassId': false    // id of security class
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader       = new QUILoader();
            this.$ActorLabels      = {};
            this.$initialLoad = true;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            var self = this;

            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-auth-actorselect',
                html   : '<div class="pcsg-gpm-auth-actorselect-select"></div>' +
                '<div class="pcsg-gpm-auth-actorselect-switch"></div>'
            });

            var ActorSwitchElm = this.$Elm.getElement(
                '.pcsg-gpm-auth-actorselect-switch'
            );

            var ActorSelectElm = this.$Elm.getElement(
                '.pcsg-gpm-auth-actorselect-select'
            );

            this.$ActorSwitchBtn = new QUIButton({
                'icon': 'fa fa-users',
                alt   : QUILocale.get(lg, 'controls.eligibleactorselect.switch.groups.btn'),
                title : QUILocale.get(lg, 'controls.eligibleactorselect.switch.groups.btn'),
                type  : 'users',
                events: {
                    onClick: function (Btn) {
                        if (Btn.getAttribute('type') === 'groups') {
                            self.$switchActorToGroups();
                            return;
                        }

                        self.$switchActorToUsers();
                    }
                }
            }).inject(ActorSwitchElm);

            this.$ActorSelect = new QUISelect({
                styles: {
                    'width': '250px'
                }
            }).inject(ActorSelectElm);

            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        setActorValue: function(value, actorType)
        {
            var self = this;

            switch (actorType) {
                case 'user':
                    this.$switchActorToUsers().then(function() {
                        self.$ActorSelect.setValue(value);
                    });
                    break;

                case 'group':
                    this.$switchActorToGroups().then(function() {
                        self.$ActorSelect.setValue(value);
                    });
                    break;
            }
        },

        /**
         * Switches available actors to QUIQQER users
         *
         * @return {Promise}
         */
        $switchActorToUsers: function()
        {
            this.setAttribute('actorType', 'user');

            this.$ActorSwitchBtn.setAttributes({
                icon : 'fa fa-users',
                alt  : QUILocale.get(lg, 'controls.eligibleactorselect.switch.groups.btn'),
                title: QUILocale.get(lg, 'controls.eligibleactorselect.switch.groups.btn'),
                type : 'groups'
            });

            return this.refresh();
        },

        /**
         * Switches available actors to QUIQQER groups
         *
         * @return {Promise}
         */
        $switchActorToGroups: function()
        {
            this.setAttribute('actorType', 'group');

            this.$ActorSwitchBtn.setAttributes({
                icon : 'fa fa-user',
                alt  : QUILocale.get(lg, 'controls.eligibleactorselect.switch.users.btn'),
                title: QUILocale.get(lg, 'controls.eligibleactorselect.switch.users.btn'),
                type : 'users'
            });

            return this.refresh();
        },

        /**
         * Refresh the control - reloads eligible actors
         *
         * @return {Promise}
         */
        refresh: function () {
            var self            = this;
            var securityClassId = this.getAttribute('securityClassId');

            this.Loader.show();
            this.$ActorLabels = {};

            switch (this.getAttribute('actorType')) {
                case 'group':
                    return new Promise(function(resolve) {
                        Authentication.getEligibleGroupsBySecurityClass(
                            securityClassId
                        ).then(function (groups) {
                            self.$ActorSelect.clear();

                            var first = null;

                            for (var i = 0, len = groups.length; i < len; i++) {
                                self.$ActorSelect.appendChild(
                                    groups[i].name + ' (' + groups[i].id + ')',
                                    groups[i].id,
                                    'fa fa-users'
                                );

                                self.$ActorLabels[groups[i].id] = groups[i].name +
                                    ' (' + groups[i].id + ')';

                                if (first === null) {
                                    first = groups[i].id;
                                }
                            }

                            if (first !== null) {
                                self.$ActorSelect.setValue(first);
                            }

                            resolve();
                            self.Loader.hide();

                            if (self.$initialLoad) {
                                self.$initialLoad = false;
                                self.fireEvent('loaded');
                            }
                        });
                    });
                    break;

                default:
                    return new Promise(function(resolve) {
                        Authentication.getEligibleUsersBySecurityClass(
                            securityClassId
                        ).then(function (users) {
                            self.$ActorSelect.clear();

                            var first = null;

                            for (var i = 0, len = users.length; i < len; i++) {
                                self.$ActorSelect.appendChild(
                                    users[i].username + ' (' + users[i].id + ')',
                                    users[i].id,
                                    'fa fa-user'
                                );

                                self.$ActorLabels[users[i].id] = users[i].username +
                                    ' (' + users[i].id + ')';

                                if (first === null) {
                                    first = users[i].id;
                                }
                            }

                            if (first !== null) {
                                self.$ActorSelect.setValue(first);
                            }

                            resolve();
                            self.Loader.hide();

                            if (self.$initialLoad) {
                                self.$initialLoad = false;
                                self.fireEvent('loaded');
                            }
                        });
                    });
            }
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            this.refresh('users');
        },

        /**
         * Get label of selected actor
         *
         * @returns {Object}
         */
        getActorLabel: function() {
            return this.$ActorLabels[this.$ActorSelect.getValue()];
        },

        /**
         * Returns currently selected actor ID and type
         *
         * @returns {Object}
         */
        getActor: function () {
            return {
                'id'  : this.$ActorSelect.getValue(),
                'type': this.getAttribute('actorType')
            };
        }
    });
});
