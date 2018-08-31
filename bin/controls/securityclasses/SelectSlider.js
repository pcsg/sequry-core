/**
 * Control for creating a new password
 *
 * @module package/sequry/core/bin/controls/securityclasses/SelectSlider
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLoaded [this] - fires when the control has finished loading
 * @event onChange [securityClassId, this] - fires if the user selects a security class
 */
define('package/sequry/core/bin/controls/securityclasses/SelectSlider', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'package/bin/html5tooltipsjs/html5tooltips',
    URL_OPT_DIR + 'bin/nouislider/distribute/nouislider.js',

    'package/sequry/core/bin/Authentication',

    'Locale',

    'css!' + URL_OPT_DIR + 'bin/nouislider/distribute/nouislider.css',
    'css!package/sequry/core/bin/controls/securityclasses/SelectSlider.css'

], function (QUIControl, QUILoader, html5tooltips, noUiSlider, Authentication, QUILocale) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/securityclasses/SelectSlider',

        Binds: [
            '$onInject',
            '$refreshFactorSelect'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.Loader           = new QUILoader();
            this.$Slider          = null;
            this.$SecurityClasses = {};
            this.$InfoElm         = null;
            this.$securityClassId = null; // currently selected security class id
            this.$dragging        = false;
            this.$CurrentToolTip  = null;
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
            var SecurityClass;

            this.Loader.show();

            var SliderContainer = this.$Elm.getElement(
                '.pcsg-gpm-securityclasses-selectslider-slider'
            );

            Authentication.getSecurityClasses().then(function (SecurityClasses) {
                // special case: only one security class
                if (Object.getLength(SecurityClasses) === 1) {
                    SecurityClass = SecurityClasses[Object.keys(SecurityClasses)[0]];

                    self.$InfoElm.set(
                        'html',
                        QUILocale.get(lg, 'controls.securityclasses.selectslider.authfactors', {
                            title          : SecurityClass.title,
                            requiredFactors: SecurityClass.requiredFactors
                        })
                    );

                    html5tooltips({
                        animateFunction: "slidein",
                        delay          : 0,
                        color          : "tangerine",
                        stickTo        : "bottom",
                        contentText    : '<b>' + SecurityClass.title + '</b><br>' +
                            SecurityClass.description,
                        targetSelector : '.pcsg-gpm-securityclasses-selectslider-info'
                    });

                    self.$Elm.setStyle('padding', '15px');
                    self.$InfoElm.setStyle('margin', 0);

                    self.$securityClassId    = SecurityClass.id;
                    self.$SecurityClasses[1] = SecurityClass;
                    self.fireEvent('loaded', [self]);

                    self.Loader.hide();

                    return;
                }

                self.$Slider = noUiSlider.create(SliderContainer, {
                    step   : 1,
                    min    : 1,
                    max    : Object.getLength(SecurityClasses),
                    range  : {
                        min: 1,
                        max: Object.getLength(SecurityClasses)
                    },
                    start  : [1],
                    snap   : false,
                    connect: false,
                    pips   : {
                        mode   : 'steps',
                        density: 100
                    }
                });

                var i                = 1;
                var sliderNumberElms = SliderContainer.getElements('.noUi-value');
                var toolTips         = [];

                var FuncSliderNumberClick = function (event) {
                    self.setValue(
                        event.target.getProperty('data-securityclassid')
                    );
                };

                for (var id in SecurityClasses) {
                    if (!SecurityClasses.hasOwnProperty(id)) {
                        continue;
                    }

                    SecurityClass       = SecurityClasses[id];
                    var sliderNumberId  = 'pcsg-gpm-securityclasses-selectslider-number-' + i;
                    var SliderNumberElm = sliderNumberElms[i - 1];

                    SliderNumberElm.addEvents({
                        click: FuncSliderNumberClick
                    });

                    SliderNumberElm.setProperty('id', sliderNumberId);
                    SliderNumberElm.setProperty('data-securityclassid', SecurityClass.id);

                    self.$SecurityClasses[i++] = SecurityClass;

                    toolTips.push({
                        animateFunction: "slidein",
                        delay          : 0,
                        color          : "tangerine",
                        stickTo        : "bottom",
                        contentText    : '<b>' + SecurityClass.title + '</b><br>' +
                            SecurityClass.description,
                        targetSelector : '#' + sliderNumberId
                    });
                }

                html5tooltips(toolTips);

                self.$Slider.on('update', function (values) {
                    self.$onChangeValue(parseInt(values[0]));
                    self.$sliderNumber = parseInt(values[0]);
                });

                self.$Slider.on('start', function () {
                    self.$dragging    = true;
                    var SliderHandle  = SliderContainer.getElement('.noUi-handle');
                    var sliderValue   = parseInt(self.$Slider.get());
                    var SecurityClass = self.$SecurityClasses[sliderValue];

                    self.$CurrentToolTip = new Element('div', {
                        'class': 'pcsg-gpm-tooltip-top',
                        html   : '<span><b>' + SecurityClass.title + '</b><br>' +
                            SecurityClass.description + '</span>'
                    }).inject(SliderHandle);
                });

                self.$Slider.on('end', function () {
                    if (self.$CurrentToolTip) {
                        self.$CurrentToolTip.destroy();
                    }

                    self.$dragging = false;
                });

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

            if (this.$dragging) {
                this.$CurrentToolTip.set(
                    'html',
                    '<span><b>' + SecurityClass.title + '</b><br>' +
                    SecurityClass.description + '</span>'
                );
            }

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
                    if (this.$Slider) {
                        this.$Slider.set(number);
                    } else {
                        this.$onChangeValue(securityClassId);
                    }

                    break;
                }
            }
        },

        getValue: function () {
            return this.$securityClassId;
        }
    });
});
