require.config({
    paths: {
        "ClipboardJS"  : URL_OPT_DIR + 'bin/clipboard/dist/clipboard',
        "html5tooltips": URL_OPT_DIR + 'quiqqer/tooltips/bin/html5tooltips'
    }
});

/**
 * Parses all inputs in a DOMNode and adds specific interaction buttons
 *
 * @module package/sequry/core/bin/controls/utils/InputButtons
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/sequry/core/bin/controls/utils/InputButtons', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',

    'package/sequry/core/bin/Passwords',

    'Locale',
    'ClipboardJS',

    'css!package/sequry/core/bin/controls/utils/InputButtons.css'

], function (QUIControl, QUIButton, Passwords, QUILocale, Clipboard) {
    "use strict";

    var lg = 'sequry/core';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/sequry/core/bin/controls/utils/InputButtons',

        Binds: [
            'parse',
            '$addCopyBtn',
            '$addViewToggleBtn',
            '$addGeneratePassBtn',
            '$addOpenUrlBtn'
        ],

        /**
         * Parse Input DOMNode or all input DOMNodes in the given DOMNode
         * and add buttons
         *
         * @param {HTMLElement} ParseElm - The input element the buttons are added to the ParseElm
         * @param {Array} buttonTypes - Types of buttons to be added
         *
         * Possible buttonTypes:
         * - copy
         * - viewtoggle
         * - generatepass
         * - openurl
         *
         * @param {Array} [customButtons] - List of custom buttons that are added to the ParseElm
         */
        parse: function (ParseElm, buttonTypes, customButtons) {
            var inputElms;

            customButtons = customButtons || [];

            //if (ParseElm.nodeName === 'INPUT') {
            inputElms = [ParseElm];
            //} else {
            //    inputElms = ParseElm.getElements('input');
            //}

            var copy         = this.getAttribute('copy'),
                viewtoggle   = this.getAttribute('viewtoggle'),
                generatepass = this.getAttribute('generatepass'),
                openurl      = this.getAttribute('openurl');

            inputElms.forEach(function (InputElm) {
                var i, len;

                // pre-configured buttons
                for (i = 0, len = buttonTypes.length; i < len; i++) {
                    switch (buttonTypes[i]) {
                        case 'copy':
                            this.$addCopyBtn(InputElm);
                            break;

                        case 'viewtoggle':
                            this.$addViewToggleBtn(InputElm);
                            break;

                        case 'generatepass':
                            this.$addGeneratePassBtn(InputElm);
                            break;

                        case 'openurl':
                            this.$addOpenUrlBtn(InputElm);
                            break;
                    }
                }

                // custom buttons
                for (i = 0, len = customButtons.length; i < len; i++) {
                    var Btn = customButtons[i];

                    Btn.getElm().addClass('pcsg-gpm-utils-inputbuttons-btn');
                    Btn.setAttribute('InputElm', InputElm);
                    Btn.inject(InputElm, 'after');
                }
            }.bind(this));
        },

        /**
         * Add button to copy input value to clipboard
         *
         * @param InputElm
         */
        $addCopyBtn: function (InputElm) {
            var CopyBtn = new QUIButton({
                'class': 'pcsg-gpm-utils-inputbuttons-btn',
                Elm    : InputElm,
                icon   : 'fa fa-copy',
                events : {
                    onClick: function (Btn) {
                        var Elm = Btn.getAttribute('Elm');

                        if (Elm.nodeName === 'INPUT') {
                            Elm.select();
                        }

                        var ToolTip = new Element('div', {
                            'class': 'pcsg-gpm-tooltip-left',
                            html   : '<span>' +
                            QUILocale.get(lg, 'controls.utils.inputbuttons.copy') +
                            '</span>'
                        }).inject(Btn.getElm(), 'after');

                        (function () {
                            moofx(ToolTip).animate({
                                opacity: 0
                            }, {
                                duration: 1000,
                                callback: function () {
                                    ToolTip.destroy();
                                }
                            });
                        }.delay(750));
                    }
                }
            }).inject(InputElm, 'after');

            new Clipboard(CopyBtn.getElm(), {
                text: function () {
                    var Elm = this.getAttribute('Elm');

                    console.log(Elm.nodeName);

                    if (Elm.nodeName === 'INPUT') {
                        return Elm.value;
                    }

                    if (Elm.nodeName === 'DIV') {
                        return Elm.innerHTML.replace(/<br\s*\/?>/mg,"\n");
                    }

                    return Elm.innerHTML.trim();
                }.bind(CopyBtn)
            });
        },

        /**
         * Add button to toggle between original type and "password" type
         *
         * @param InputElm
         */
        $addViewToggleBtn: function (InputElm) {
            var type = InputElm.getProperty('type');

            new QUIButton({
                'class'     : 'pcsg-gpm-utils-inputbuttons-btn',
                originaltype: type === 'password' ? 'text' : type,
                icon        : type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash',
                action      : type === 'password' ? 'show' : 'hide',
                events      : {
                    onClick: function (Btn) {
                        if (Btn.getAttribute('action') === 'show') {
                            Btn.setAttributes({
                                icon  : 'fa fa-eye-slash',
                                action: 'hide'
                            });

                            InputElm.setProperty('type', Btn.getAttribute('originaltype'));
                            InputElm.focus();
                            InputElm.select();

                            return;
                        }

                        Btn.setAttributes({
                            icon  : 'fa fa-eye',
                            action: 'show'
                        });

                        InputElm.setProperty('type', 'password');
                        InputElm.blur();
                    }
                }
            }).inject(InputElm, 'after');
        },

        /**
         * Add button to generate a random, strong password
         *
         * @param InputElm
         */
        $addGeneratePassBtn: function (InputElm) {
            new QUIButton({
                'class': 'pcsg-gpm-utils-inputbuttons-btn',
                icon   : 'fa fa-random',
                events : {
                    onClick: function (Btn) {
                        Btn.setAttribute('icon', 'fa fa-spinner fa-spin');
                        Btn.disable();

                        Passwords.generateRandomPassword().then(function (rndPass) {
                            InputElm.value = rndPass;
                            Btn.setAttribute('icon', 'fa fa-random');
                            Btn.enable();
                        });
                    }
                }
            }).inject(InputElm, 'after');
        },

        /**
         * Add button to open input value as url (new tab)
         *
         * @param {HTMLElement} InputElm
         */
        $addOpenUrlBtn: function (InputElm) {
            new QUIButton({
                'class': 'pcsg-gpm-utils-inputbuttons-btn',
                icon   : 'fa fa-external-link',
                events : {
                    onClick: function () {
                        var href;

                        if (InputElm.get('href')) {
                            href = InputElm.href;
                        } else {
                            var LinkElm = InputElm.getElement('a');

                            if (LinkElm && LinkElm.get('href')) {
                                href = LinkElm.href;
                            }
                        }

                        if (!href) {
                            href = InputElm.value;
                        }

                        if (!href) {
                            href = InputElm.innerHTML;
                        }

                        if (!href) {
                            return;
                        }

                        var AnchorElm = new Element('a', {
                            href  : href,
                            target: '_blank'
                        });

                        AnchorElm.click();
                        AnchorElm.destroy();
                    }
                }
            }).inject(InputElm, 'after');
        }
    });
});
