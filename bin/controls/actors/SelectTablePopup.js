/**
 * Select actors for a SecurityClass via Grid (Popup)
 *
 * @module package/sequry/core/bin/controls/actors/SelectTablePopup
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/windows/Popup
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require package/sequry/core/bin/controls/actors/SelectTable
 *
 * @event onSubmit [selectedIds, actorType, this]
 */
define('package/sequry/core/bin/controls/actors/SelectTablePopup', [

    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',

    'Locale',

    'package/sequry/core/bin/controls/actors/SelectTable'

], function (QUIPopup, QUIButton, QUILocale, SelectTable) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/sequry/core/bin/controls/actors/SelectTablePopup',

        Binds: [
            '$onOpen',
            '$onResize',
            '$submit'
        ],

        options: {
            icon             : 'fa fa-users',
            info             : '',       // info text that is shown above the table
            securityClassIds : [],    // security class ids the actors have to be eligible for
            multiselect      : false,
            actorType        : 'all',    // can be "all", "users" or "groups"
            filterActorIds   : [],       // IDs of actors that are filtered from list (entries must have
                                         // prefix "u" (user) or "g" (group)
            showEligibleOnly : false,     // show eligible only or all
            selectedActorType: 'users' // pre-selected actor type
        },

        initialize: function (options) {
            this.parent(options);

            this.$SelectTable = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onResize: this.$onResize
            });
        },

        /**
         * Event: onOpen
         */
        $onOpen: function () {
            var title;

            switch (this.getAttribute('actorType')) {
                case 'users':
                    title = QUILocale.get(lg, 'controls.actors.selecttablepopup.title.users');
                    break;

                case 'groups':
                    title = QUILocale.get(lg, 'controls.actors.selecttablepopup.title.groups');
                    break;

                default:
                    title = QUILocale.get(lg, 'controls.actors.selecttablepopup.title.all');
            }

            this.setAttribute('title', title);

            this.$SelectTable = new SelectTable({
                info             : this.getAttribute('info'),
                securityClassIds : this.getAttribute('securityClassIds'),
                multiselect      : this.getAttribute('multiselect'),
                actorType        : this.getAttribute('actorType'),
                filterActorIds   : this.getAttribute('filterActorIds'),
                showEligibleOnly : this.getAttribute('showEligibleOnly'),
                selectedActorType: this.getAttribute('selectedActorType'),
                events           : {
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

            this.refresh();
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
