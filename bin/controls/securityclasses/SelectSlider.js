/**
 * Control for creating a new password
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/securityclasses/SelectSlider
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
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/SelectSlider.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/SelectSlider.css
 *
 * @event onLoaded [this] - fires when the control has finished loading
 * @event onChange [securityClassId, this] - fires if the user selects a security class
 */
define('package/pcsg/grouppasswordmanager/bin/controls/securityclasses/SelectSlider', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/input/Range',

    URL_OPT_DIR + 'bin/nouislider/distribute/nouislider.min.js',

    'package/pcsg/grouppasswordmanager/bin/Authentication',

    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/securityclasses/SelectSlider.css'

], function (QUIControl, QUILoader, QUIRange, noUiSlider, Authentication, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/securityclasses/SelectSlider',

        Binds: [
            '$onInject',
            '$refreshFactorSelect'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader                 = new QUILoader();
            this.$RequiredFactorsSelect = null;
            this.$Slider                = null;
            this.$SecurityClasses       = {};
            this.$RequiredFactorsElm    = null;
            this.$InfoElm               = null;
            this.$securityClassId       = null; // currently selected security class id
        },

        /**
         * create the domnode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'pcsg-gpm-securityclasses-selectslider',
                html   : '<div class="pcsg-gpm-securityclasses-selectslider-slider">' +
                '</div>' +
                '<div class="pcsg-gpm-securityclasses-selectslider-info"></div>'
            });

            this.$InfoElm = this.$Elm.getElement(
                '.pcsg-gpm-securityclasses-selectslider-info'
            );

            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            this.Loader.show();

            var SliderContainer = this.$Elm.getElement(
                '.pcsg-gpm-securityclasses-selectslider-slider'
            );

            Authentication.getSecurityClasses().then(function (SecurityClasses) {
                self.$Slider = noUiSlider.create(SliderContainer, {
                    step   : 1,
                    min    : 1,
                    max    : Object.getLength(SecurityClasses),
                    range  : {
                        min: 1,
                        max: Object.getLength(SecurityClasses)
                    },
                    start  : [1],
                    snap   : true,
                    connect: false,
                    pips   : {
                        mode   : 'steps',
                        density: 100
                    }
                });

                var i = 1;

                for (var id in SecurityClasses) {
                    if (!SecurityClasses.hasOwnProperty(id)) {
                        continue;
                    }

                    self.$SecurityClasses[i++] = SecurityClasses[id];
                }

                self.$Slider.on('update', function (values) {
                    self.$onChangeValue(parseInt(values[0]));
                });

                //self.$onChangeValue(1);
                self.Loader.hide();
                self.fireEvent('loaded', [self]);
            });
        },

        $onChangeValue: function (value) {
            var SecurityClass = this.$SecurityClasses[value];

            this.$InfoElm.set(
                'html',
                QUILocale.get(lg, 'controls.securityclasses.selectslider.authfactors', {
                    title          : SecurityClass.title,
                    requiredFactors: SecurityClass.requiredFactors
                })
            );

            //new Element('div', {
            //    'class': 'pcsg-gpm-password-help-bubble',
            //
            //});

            this.$securityClassId = SecurityClass.id;
            this.fireEvent('change', [this.$securityClassId, this]);
        },

        /**
         * Set security class id
         *
         * @param {number} securityClassId
         */
        setValue: function (securityClassId) {
            for (var number in this.$SecurityClasses) {
                if (!this.$SecurityClasses.hasOwnProperty(number)) {
                    continue;
                }

                var SecurityClass = this.$SecurityClasses[number];

                if (SecurityClass.id == securityClassId) {
                    this.$Slider.set(number);
                }
            }
        },

        getValue: function () {
            return this.$securityClassId;
        }
    });
});
