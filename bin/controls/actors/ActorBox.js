/**
 * Box containing users and/or groups
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/ActorBox
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require qui/controls/loader/Loader
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Authentication
 * @require Ajax
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/actors/ActorBox.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/actors/ActorBox.css
 *
 * @event onLoaded
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/ActorBox', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/actors/ActorBox.css'

], function (QUI, QUIControl, QUIButton, QUISelect, QUILoader, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/ActorBox',

        Binds: [
            'refresh',
            'submit',
            'getActor',
            'switchActorToUsers',
            'switchActorToGroups'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader       = new QUILoader();
            this.$Actors      = {};
            this.$initialLoad = true;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-auth-actorbox',
                html   : '<div class="pcsg-gpm-auth-actorbox-box"></div>'
            });

            this.Loader.inject(this.$Elm);
            this.$ActorBox = this.$Elm.getElement(
                '.pcsg-gpm-auth-actorbox-box'
            );

            return this.$Elm;
        },

        /**
         * Adds an actor
         *
         * @param {Object} ActorData
         */
        addActor: function (ActorData) {
            var self = this;

            if (ActorData.id in this.$Actors) {
                return;
            }

            var ActorElm = new Element('div', {
                'class': 'pcsg-gpm-auth-actorbox-actor',
                html   : '<span class="pcsg-gpm-auth-actorbox-actor-icon"></span>' +
                '<span class="pcsg-gpm-auth-actorbox-actor-name">' +
                ActorData.name +
                '</span>' +
                '<span class="pcsg-gpm-auth-actorbox-actor-remove fa fa-remove"></span>'
            });

            // set remove event
            var RemoveElm = ActorElm.getElement(
                'span.pcsg-gpm-auth-actorbox-actor-remove'
            );

            RemoveElm.setProperty(
                'data-id',
                ActorData.id
            );

            RemoveElm.addEvents({
                click: function (event) {
                    self.removeActor(
                        event.target.getProperty('data-id')
                    );
                }
            });

            // set actor icon
            var actorIcon = 'fa fa-user';

            if (ActorData.type === 2) {
                actorIcon = 'fa fa-users';
            }

            ActorElm.getElement(
                'span.pcsg-gpm-auth-actorbox-actor-icon'
            ).addClass(actorIcon);

            this.$Actors[ActorData.id] = {
                type: ActorData.type,
                elm : ActorElm
            };

            ActorElm.inject(this.$ActorBox);
        },

        /**
         * Remove actor
         *
         * @param {number} id
         */
        removeActor: function (id) {
            if (id in this.$Actors) {
                this.$Actors[id].elm.destroy();
                delete this.$Actors[id];
            }
        },

        /**
         * Returns currently added actors
         *
         * @returns {Array}
         */
        getActors: function () {
            var actors = [];

            for (var id in this.$Actors) {
                if (!this.$Actors.hasOwnProperty(id)) {
                    continue;
                }

                actors.push({
                    id  : id,
                    type: this.$Actors[id].type
                });
            }

            return actors;
        },

        $onInject: function() {
            // @todo
        }
    });
});
