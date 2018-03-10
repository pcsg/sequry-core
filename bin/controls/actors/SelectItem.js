/**
 * SelectItem for actors/Select control
 *
 * @module package/sequry/core/bin/controls/actors/SelectItem
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/controls/actors/SelectItem', [

    'qui/QUI',
    'qui/controls/elements/SelectItem',

    'package/sequry/core/bin/Actors',

    'Ajax'

], function (QUI, QUIElementSelectItem, Actors) {
    "use strict";

    return new Class({

        Extends: QUIElementSelectItem,
        Type   : 'package/sequry/core/bin/controls/actors/SelectItem',

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