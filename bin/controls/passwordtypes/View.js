/**
 * Control for password content input with different types
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/View
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/pcsg/grouppasswordmanager/bin/classes/Passwords
 * @require package/pcsg/grouppasswordmanager/bin/controls/auth/Authenticate
 * @require package/pcsg/grouppasswordmanager/bin/controls/securityclasses/Select
 * @require package/pcsg/grouppasswordmanager/bin/controls/actors/EligibleActorSelect
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/View.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/View.css
 *
 * @event onLoaded
 */
define('package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/View', [

    'qui/QUI',
    'qui/controls/Control',

    'Ajax',
    'Locale',

    'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/Select'

    //'css!package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/View.css'

], function (QUI, QUIControl, QUIAjax, QUILocale, TypeSelect) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/passwordtypes/View',

        Binds: [
            '$onInject'
        ],

        options: {
            type: false // initial type
        },

        initialize: function (options) {
            this.parent(options);
            this.$TypeSelect = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.set({
                'class': 'pcsg-gpm-password-types-view',
                html   : '<div class="pcsg-gpm-password-types-view-content"></div>'
            });

            this.$ViewContent = this.$Elm.getElement('.pcsg-gpm-password-types-view-content');

            return this.$Elm;
        },

        /**
         * event: on control inject into DOM
         */
        $onInject: function()
        {
            this.$loadTemplate();
        },

        /**
         * Load password type edit template
         */
        $loadTemplate: function () {
            var self = this;

            QUIAjax.get(
                'package_pcsg_grouppasswordmanager_ajax_passwordtypes_getViewHtml',
                function (templateHtml) {
                    self.$ViewContent.set('html', templateHtml);
                }, {
                    'package': 'pcsg/grouppasswordmanager',
                    type     : this.getAttribute('type')
                }
            );
        }
    });
});
