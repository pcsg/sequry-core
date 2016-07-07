/**
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/SelectItem
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/elements/Select
 * @require Ajax
 * @require Locale
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/SelectItem', [

    'qui/QUI',
    'qui/controls/elements/SelectItem',

    'package/pcsg/grouppasswordmanager/bin/classes/Actors',

    'Ajax'

], function (QUI, QUIElementSelectItem, ActorHandler, QUIAjax) {
    "use strict";

    var Actors = new ActorHandler();

    return new Class({

        Extends: QUIElementSelectItem,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectItem',

        Binds: [
            'refresh'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttribute('icon', 'fa fa-user');
        },

        /**
         * Refresh the display
         *
         * @returns {Promise}
         */
        refresh: function () {
            var self = this;
            var id   = this.getAttribute('id');
            var type = 'user';

            if (id.charAt(0) === 'g') {
                type = 'group';
                this.$Icon.removeClass('fa-user');
                this.$Icon.addClass('fa-group');
            }

            id = id.substr(1);

            return Actors.getActor(id, type).then(function(Actor) {
                self.$Text.set({
                    html: Actor.name + ' (' + Actor.id + ')'
                });
            });
        }
    });
});