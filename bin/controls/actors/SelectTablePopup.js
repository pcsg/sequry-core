/**
 * Select actors for a SecurityClass via Grid (Popup)
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/windows/Popup
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable
 *
 * @event onSubmit [selectedIds, actorType, this]
 */
define('package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup', [

    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',

    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTable'

], function (QUIPopup, QUIButton, QUILocale, SelectTable) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/actors/SelectTablePopup',

        Binds: [
            '$onOpen',
            '$onResize',
            '$submit'
        ],

        options: {
            securityClassId: false,   // security class id the actors have to be eligible for
            multiselect    : false,
            actorType      : 'all', // can be "all", "users" or "groups"
            filterActorIds : []   // IDs of actors that are filtered from list (entries must have
            // prefix "u" (user) or "g" (group)
        },

        initialize: function (options) {
            this.parent(options);

            this.$SelectTable = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * Event: onOpen
         */
        $onOpen: function () {
            this.$SelectTable = new SelectTable({
                securityClassId: this.getAttribute('securityClassId'),
                multiselect    : this.getAttribute('multiselect'),
                actorType      : this.getAttribute('actorType'),
                filterActorIds : this.getAttribute('filterActorIds'),
                events         : {
                    onSubmit: this.$submit
                }
            }).inject(this.getContent());

            this.addButton(new QUIButton({
                text     : QUILocale.get(lg, 'controls.actors.selecttablepopup.btn.confirm'),
                textimage: 'fa fa-check',
                events   : {
                    onClick: this.$submit
                }
            }));
        },

        /**
         * Submit actor selection
         */
        $submit: function () {
            this.fireEvent('submit', [
                this.$SelectTable.getSelectedIds(),
                this.$SelectTable.getActorType(),
                this
            ]);

            this.close();
        },

        /**
         * Event: onResize
         */
        $onResize: function () {
            if (this.$SelectTable) {
                this.$SelectTable.resize();
            }
        }
    });
});
