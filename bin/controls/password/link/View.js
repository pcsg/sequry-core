/**
 * Parses password data called from a PasswordLink
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/password/link/View
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require Locale
 * @require Mustache
 * @require package/pcsg/grouppasswordmanager/bin/Passwords
 * @require text!package/pcsg/grouppasswordmanager/bin/controls/password/link/View.html
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/password/link/View.css
 *
 * @event onSubmit [this] - fires after a new PasswordLink has been successfully created
 */
define('package/pcsg/grouppasswordmanager/bin/controls/password/link/View', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'Locale'

], function (QUIControl, QUIButton, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/password/link/View',

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            })
        },

        /**
         * Event: onImport
         *
         * @return {HTMLDivElement}
         */
        $onImport: function () {
            //var Elm               = this.getElm();
            //var passwordInputElms = Elm.getElements(
            //    'input[type="password"]'
            //);
            //
            //passwordInputElms.forEach(function(Elm) {
            //    Elm.type = 'text';
            //});

            this.$Elm = this.getElm();
            this.$parseView();
        },

        /**
         * Parse DOM elements of the view and add specific controls (e.g. copy / show password buttons)
         */
        $parseView: function () {
            // copy elements
            var i, len, Elm, CopyBtn;
            var copyElms = this.$Elm.getElements('.gpm-passwordtypes-copy');

            var FuncCopyBtnClick = function (Btn) {
                var Elm = Btn.getAttribute('Elm');

                if (Elm.nodeName === 'INPUT') {
                    Elm.select();
                }

                var ToolTip = new Element('div', {
                    'class': 'pcsg-gpm-tooltip',
                    html   : '<span>' +
                    QUILocale.get(lg, 'controls.password.view.tooltip.copy') +
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
            };

            for (i = 0, len = copyElms.length; i < len; i++) {
                Elm = copyElms[i];

                CopyBtn = new QUIButton({
                    Elm   : Elm,
                    icon  : 'fa fa-copy',
                    events: {
                        onClick: FuncCopyBtnClick
                    }
                }).inject(Elm, 'after');

                //new Clipboard(CopyBtn.getElm(), {
                //    text: function () {
                //        var Elm = this.getAttribute('Elm');
                //
                //        if (Elm.nodeName === 'INPUT') {
                //            return Elm.value;
                //        }
                //
                //        if (Elm.nodeName === 'DIV') {
                //            var children = Elm.getChildren();
                //
                //            if (children.length) {
                //                return children[0].innerHTML.trim();
                //            }
                //        }
                //
                //        return Elm.innerHTML.trim();
                //    }.bind(CopyBtn)
                //});
            }

            // show elements (switch between show and hide)
            var showElms = this.$Elm.getElements('.gpm-passwordtypes-show');

            for (i = 0, len = showElms.length; i < len; i++) {
                Elm = showElms[i];

                new QUIButton({
                    Elm   : Elm,
                    icon  : 'fa fa-eye',
                    action: 'show',
                    events: {
                        onClick: function (Btn) {
                            var Elm = Btn.getAttribute('Elm');

                            if (Btn.getAttribute('action') === 'show') {
                                Btn.setAttributes({
                                    icon  : 'fa fa-eye-slash',
                                    action: 'hide'
                                });

                                Elm.setProperty('type', 'text');
                                Elm.focus();
                                Elm.select();

                                return;
                            }

                            Btn.setAttributes({
                                icon  : 'fa fa-eye',
                                action: 'show'
                            });

                            Elm.setProperty('type', 'password');
                            Elm.blur();
                        }
                    }
                }).inject(Elm, 'after');
            }
        }
    });
});
