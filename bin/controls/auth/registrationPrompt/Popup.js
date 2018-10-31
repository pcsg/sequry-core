/**
 * Popup for (mandatory) registration with authentication plugins
 *
 * @module package/sequry/core/bin/controls/auth/registrationPrompt/Popup
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSubmit [AuthData, this]
 * @event onClose [this]
 * @event onAbort [this]
 */
define('package/sequry/core/bin/controls/auth/registrationPrompt/Popup', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'Locale',
    'Mustache',

    'package/sequry/core/bin/Authentication',

    'text!package/sequry/core/bin/controls/auth/registrationPrompt/Popup.html',
    'text!package/sequry/core/bin/controls/auth/registrationPrompt/Popup.Plugin.html',
    'css!package/sequry/core/bin/controls/auth/registrationPrompt/Popup.css'

], function (QUI, QUIPopup, QUIButton, QUILocale, Mustache, Authentication, template, templatePlugin) {
    "use strict";

    var lg       = 'sequry/core';
    var lgPrefix = 'controls.auth.registrationPrompt.Popup.';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/sequry/core/bin/controls/auth/registrationPrompt/Popup',

        Binds: [
            '$onInject',
            '$onOpen',
            '$onCreate',
            '$buildContent',
            '$loadOverview',
            '$getPluginElm'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                maxWidth: 500
            });

            this.addEvents({
                onCreate: this.$onCreate,
                onOpen  : this.$onOpen
            });

            this.$CloseBtn = null;
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        $onCreate: function () {
            var self = this;

            this.$Elm.addClass('sequry-core-auth-registrationprompt-popup');

            this.$CloseBtn = new QUIButton({
                'class': 'btn-green',
                text   : QUILocale.get(lg, lgPrefix + '.btn_close'),
                alt    : QUILocale.get(lg, lgPrefix + '.btn_close'),
                title  : QUILocale.get(lg, lgPrefix + '.btn_close'),
                events : {
                    onClick: function () {
                        self.fireEvent('submit', [self]);
                    }
                }
            });

            this.addButton(this.$CloseBtn);

            // add authenticate button that is only enabled
            // when user has authenticated with all SecurityClasses
            this.$CloseBtn.disable();
        },

        $onOpen: function () {
            this.$loadOverview();
        },

        /**
         * Load authentication plugin overview
         */
        $loadOverview: function () {
            var self = this;

            this.setContent(Mustache.render(template, {
                info: QUILocale.get(lg, lgPrefix + 'template.info')
            }));

            this.Loader.show();

            var PluginsContainer = this.getContent().getElement(
                '.sequry-core-auth-registrationprompt-popup-authplugins'
            );

            PluginsContainer.set('html', '');

            Authentication.getRegistrationPromptList().then(function (list) {
                for (var i = 0, len = list.length; i < len; i++) {
                    self.$getPluginElm(list[i]).inject(PluginsContainer);
                }

                self.Loader.hide();
            });
        },

        /**
         * Get element for an authentication plugin
         *
         * @param {Object} AuthPlugin
         * @return {Element}
         */
        $getPluginElm: function (AuthPlugin) {
            var status;
            var elmClass = 'sequry-core-auth-registrationprompt-popup-authplugins-entry';

            if (AuthPlugin.registered) {
                status = QUILocale.get(lg, lgPrefix + 'status_registered');
                elmClass += ' sequry-core-auth-registrationprompt-popup-authplugins-entry__registered';
            } else if (AuthPlugin.registrationRequired) {
                status = QUILocale.get(lg, lgPrefix + 'status_registration_required');
                elmClass += ' sequry-core-auth-registrationprompt-popup-authplugins-entry__required';
            } else {
                status = QUILocale.get(lg, lgPrefix + 'status_registration_not_registered');
                elmClass += ' sequry-core-auth-registrationprompt-popup-authplugins-entry__not_registered';
            }

            var PluginElm = new Element('div', {
                'class'  : elmClass,
                'data-id': AuthPlugin.id,
                html     : Mustache.render(templatePlugin, {
                    title : AuthPlugin.title,
                    status: status
                })
            });

            if (!AuthPlugin.registered) {
                PluginElm.addEvents({
                    click: function (event) {
                        var Elm;

                        if (event.target.hasClass('sequry-core-auth-registrationprompt-popup-authplugins-entry')) {
                            Elm = event.target;
                        } else {
                            Elm = event.target.getParent('.sequry-core-auth-registrationprompt-popup-authplugins-entry');
                        }

                        console.log("clicked: " + Elm.get('data-id'));
                    }
                });
            }

            return PluginElm;
        },

        /**
         * Open registration for an auth plugin
         *
         * @param {Number} authPluginId
         */
        $openRegistration: function (authPluginId) {
            console.log(authPluginId);
        }
    });
});
