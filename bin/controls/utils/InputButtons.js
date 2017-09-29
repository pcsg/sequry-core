/**
 * Parses all inputs in a DOMNode and adds specific interaction buttons
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',

    'package/pcsg/grouppasswordmanager/bin/Passwords',

    'Locale',
    'ClipboardJS',

    'css!package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons.css'

], function (QUIControl, QUIButton, Passwords, QUILocale, Clipboard) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/utils/InputButtons',

        Binds: [
            'parse',
            '$addCopyBtn',
            '$addViewToggleBtn',
            '$addGeneratePassBtn',
            '$addOpenUrlBtn'
        ],

        options: {
            copy        : true,   // add button to copy input value to clipboard
            viewtoggle  : false,  // add button to toggle between original type and "password" type
            generatepass: false,  // add button to generate a random, strong password
            openurl     : false,  // add button to open input value as url (new tab)
            custom      : []
        },

        /**
         * Parse Input DOMNode or all input DOMNodes in the given DOMNode
         * and add buttons
         */
        parse: function (ParseElm) {
            var inputElms;

            if (ParseElm.nodeName === 'INPUT') {
                inputElms = [ParseElm];
            } else {
                inputElms = ParseElm.getElements('input');
            }

            var copy         = this.getAttribute('copy'),
                viewtoggle   = this.getAttribute('viewtoggle'),
                generatepass = this.getAttribute('generatepass'),
                openurl      = this.getAttribute('openurl');

            inputElms.forEach(function (InputElm) {
                if (openurl) {
                    this.$addOpenUrlBtn(InputElm);
                }

                if (generatepass) {
                    this.$addGeneratePassBtn(InputElm);
                }

                if (viewtoggle) {
                    this.$addViewToggleBtn(InputElm);
                }

                if (copy) {
                    this.$addCopyBtn(InputElm);
                }

                this.$addCustomBtns(InputElm);
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

                    if (Elm.nodeName === 'INPUT') {
                        return Elm.value;
                    }

                    if (Elm.nodeName === 'DIV') {
                        var children = Elm.getChildren();

                        if (children.length) {
                            return children[0].innerHTML.trim();
                        }
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
                originaltype: type,
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
         * @param InputElm
         */
        $addOpenUrlBtn: function (InputElm) {
            new QUIButton({
                'class': 'pcsg-gpm-utils-inputbuttons-btn',
                icon   : 'fa fa-external-link',
                events : {
                    onClick: function (Btn) {
                        var AnchorElm = new Element('a', {
                            href  : InputElm.value,
                            target: '_blank'
                        });

                        AnchorElm.click();
                        AnchorElm.destroy();
                    }
                }
            }).inject(InputElm, 'after');
        },

        /**
         * Add custom buttons
         *
         * @param InputElm
         */
        $addCustomBtns: function(InputElm)
        {
            var customBtns = this.getAttribute('custom');

            for (var i = 0, len = customBtns.length; i < len; i++) {
                var Btn = customBtns[i];

                Btn.getElm().addClass('pcsg-gpm-utils-inputbuttons-btn');
                Btn.setAttribute('InputElm', InputElm);
                Btn.inject(InputElm, 'after');
            }
        }
    });
});
