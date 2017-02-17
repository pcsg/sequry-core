/**
 * Password category map
 *
 * @module package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @requrie Ajax
 * @require Locale
 * @require css!package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map.css
 *
 * @event onCategorySelect [catId, catTitle, this] - fires if the user selects a password category
 */
define('package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',

    'qui/controls/windows/Confirm',
    'qui/controls/sitemap/Map',
    'qui/controls/sitemap/Item',
    'qui/controls/contextmenu/Item',
    'qui/controls/contextmenu/Seperator',

    'Ajax',
    'Locale',

    'css!package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map.css'

], function (QUI, QUIControl, QUIButton, QUILoader, QUIConfirm, QUISiteMap, QUISiteMapItem,
             QUIContextMenuItem, QUIContextMenuSeparator, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'pcsg/grouppasswordmanager';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/pcsg/grouppasswordmanager/bin/controls/categories/public/Map',

        Binds: [
            '$onInject',
            '$buildCategoryTree',
            '$addCategoryDialog',
            '$deleteCategoryDialog',
            '$renameCategoryDialog',
            'getCategory',
            'refresh',
            'deselectAll'
        ],

        options: {
            editMode: true   // if set to true, categories can be managed via context menu (C[R]UD)
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject : this.$onInject,
                onRefresh: this.$onRefresh,
                onCreate : this.$onCreate,
                onResize : this.$onResize
            });

            this.Loader            = new QUILoader();
            this.$selectedCatId    = null;
            this.$TreeElm          = null;
            this.$CategoryMap      = null;
            this.$FlatCategoryTree = {};
            this.$loaded           = false;
        },

        /**
         * Create DOM Element
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'pcsg-gpm-passwords-categories-map',
                html   : '<div class="pcsg-gpm-passwords-categories-map-info"></div>' +
                '<div class="pcsg-gpm-passwords-categories-map-tree"></div>'
            });

            this.$TreeElm = this.$Elm.getElement(
                '.pcsg-gpm-passwords-categories-map-tree'
            );

            return this.$Elm;
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * Get specific category data
         *
         * @param {number} catId - category ID
         * @return {Promise}
         */
        getCategory: function (catId) {
            var self = this;

            var FuncGetCat = function () {
                if (catId in self.$FlatCategoryTree) {
                    return self.$FlatCategoryTree[catId];
                } else {
                    return false;
                }
            };

            return new Promise(function (resolve) {
                if (self.$loaded) {
                    resolve(FuncGetCat());
                    return;
                }

                self.refresh().then(function () {
                    resolve(FuncGetCat());
                });
            });
        },

        /**
         * Refresh category list
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            this.$TreeElm.set('html', '');

            this.Loader.show();

            return new Promise(function (resolve) {
                self.$getCategories().then(function (categories) {
                    self.$CategoryMap = new QUISiteMap({
                        multible: !self.getAttribute('editMode')
                    }).inject(self.$TreeElm);

                    // Special category "All"
                    var ItemAll = new QUISiteMapItem({
                        title      : QUILocale.get(lg, 'controls.categories.map.category.all'),
                        text       : QUILocale.get(lg, 'controls.categories.map.category.all'),
                        icon       : 'fa fa-diamond',
                        contextmenu: true,
                        hasChildren: categories.length,
                        dragable   : false,
                        events     : {
                            onClick      : function () {
                                self.fireEvent('categorySelect', [
                                    false,
                                    //Item.getAttribute('title'),
                                    self
                                ]);

                                self.$selectedCatId = false;
                            },
                            onContextMenu: function (Item, event) {
                                event.stop();

                                Item.getContextMenu()
                                    .setTitle(Item.getAttribute('title'))
                                    .setPosition(
                                        event.page.x,
                                        event.page.y
                                    )
                                    .show();
                            }
                        }
                    });

                    self.$addCategoryItemContextMenu(ItemAll);
                    self.$CategoryMap.appendChild(ItemAll);

                    self.$buildCategoryTree(categories, ItemAll);
                    self.$CategoryMap.openAll();

                    //ItemAll.highlight();

                    self.Loader.hide();
                    self.$loaded = true;

                    resolve();
                });
            });
        },

        /**
         * Build password category tree
         *
         * @param {array} children
         * @param {Object} ParentItem - qui/controls/sitemap/Item
         */
        $buildCategoryTree: function (children, ParentItem) {
            var self = this;

            var FuncItemOnClick = function (Item) {
                self.fireEvent('categorySelect', [
                    Item.getAttribute('id'),
                    //Item.getAttribute('title'),
                    self
                ]);

                self.$selectedCatId = Item.getAttribute('id');
            };

            for (var i = 0, len = children.length; i < len; i++) {
                var Child = children[i];

                this.$FlatCategoryTree[Child.id] = {
                    id   : Child.id,
                    title: Child.title
                };

                var MapItem = new QUISiteMapItem({
                    id         : Child.id,
                    title      : Child.title,
                    text       : Child.title,
                    icon       : 'fa fa-book',
                    contextmenu: this.getAttribute('editMode'),
                    hasChildren: Child.children.length,
                    dragable   : false,
                    events     : {
                        onClick      : FuncItemOnClick,
                        onContextMenu: function (Item, event) {
                            event.stop();

                            Item.getContextMenu()
                                .setTitle(Item.getAttribute('title'))
                                .setPosition(
                                    event.page.x,
                                    event.page.y
                                )
                                .show();
                        }
                    }
                });

                if (!ParentItem) {
                    self.$CategoryMap.appendChild(MapItem);
                } else {
                    ParentItem.appendChild(MapItem);
                }

                if (this.getAttribute('editMode')) {
                    this.$addCategoryItemContextMenu(MapItem);
                }

                if (Child.children.length) {
                    self.$buildCategoryTree(Child.children, MapItem);
                }
            }
        },

        /**
         * Opens context menu for a category Item
         *
         * @param {Object} Item - qui/controls/sitemap/Item
         */
        $addCategoryItemContextMenu: function (Item) {
            var self        = this;
            var ContextMenu = Item.getContextMenu();

            ContextMenu.clearChildren().appendChild(
                new QUIContextMenuItem({
                    name  : 'category-create',
                    text  : QUILocale.get(lg, 'controls.categories.map.create.btn.create.text'),
                    icon  : 'fa fa-book',
                    events: {
                        onClick: function () {
                            self.$addCategoryDialog(Item);
                        }
                    }
                })
            );

            if (Item.getAttribute('id')) {
                ContextMenu.appendChild(
                    new QUIContextMenuItem({
                        name  : 'category-rename',
                        text  : QUILocale.get(lg, 'controls.categories.map.create.btn.rename.text'),
                        icon  : 'fa fa-edit',
                        events: {
                            onClick: function () {
                                self.$renameCategoryDialog(Item);
                            }
                        }
                    })
                ).appendChild(
                    new QUIContextMenuSeparator()
                ).appendChild(
                    new QUIContextMenuItem({
                        name  : 'category-delete',
                        text  : QUILocale.get(lg, 'controls.categories.map.create.btn.delete.text'),
                        icon  : 'fa fa-trash',
                        events: {
                            onClick: function () {
                                self.$deleteCategoryDialog(Item);
                            }
                        }
                    })
                );
            }

            ContextMenu.setAttribute('title', Item.getAttribute('text'));

            ContextMenu.addEvents({
                onShow: function () {
                    Item.highlight();

                    ContextMenu.resize();
                    ContextMenu.focus();
                },
                onBlur: function () {
                    Item.deHighlight();
                }
            });
        },

        /**
         * Get all categories
         *
         * @return {Promise}
         */
        $getCategories: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_getList',
                    resolve, {
                        'package': 'pcsg/grouppasswordmanager',
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Open add category dialog
         *
         * @param {Object} Item - qui/controls/sitemap/Item
         */
        $addCategoryDialog: function (Item) {
            var self = this;

            this.Loader.show();

            var FuncSubmit = function () {
                var Input = Popup.getContent().getElement('input');
                var title = Input.value.trim();

                if (title === '') {
                    Input.value = '';
                    Input.focus();
                    return;
                }

                Popup.Loader.show();

                self.$addCategory(title, Item.getAttribute('id')).then(function (success) {
                    Popup.Loader.hide();

                    if (!success) {
                        return;
                    }

                    Popup.close();
                    self.refresh();
                });
            };

            // open popup
            var Popup = new QUIConfirm({
                'class'    : 'pcsg-gpm-passwords-categories-create',
                'maxHeight': 225,
                maxWidth   : 400,
                'autoclose': true,

                'title'   : QUILocale.get(lg, 'controls.categories.map.create.title'),
                'texticon': 'fa fa-book',
                'icon'    : 'fa fa-book',

                events: {
                    onOpen  : function () {
                        var Content = Popup.getContent();

                        var infoText = QUILocale.get(lg, 'controls.categories.map.create.info', {
                            catId   : Item.getAttribute('id'),
                            catTitle: Item.getAttribute('title')
                        });

                        if (!Item.getAttribute('id')) {
                            infoText = QUILocale.get(lg, 'controls.categories.map.create.info.all', {
                                catTitle: Item.getAttribute('title')
                            });
                        }

                        Content.set(
                            'html',
                            '<p>' + infoText + '</p>' +
                            '<label>' +
                            '<span>' + QUILocale.get(lg, 'controls.categories.map.create.label') + '</span>' +
                            '<input type="text"/>' +
                            '</label>'
                        );

                        var Input = Content.getElement('input');

                        Input.addEvents({
                            keyup: function (event) {
                                if (event.code === 13) {
                                    FuncSubmit();
                                    this.blur();
                                }
                            }
                        });

                        Input.focus();
                    },
                    onSubmit: FuncSubmit,
                    onClose : function () {
                        self.Loader.hide();
                    }
                }
            });

            Popup.open();
        },

        /**
         * Rename category dialog
         *
         * @param {Object} Item - qui/controls/sitemap/Item
         */
        $renameCategoryDialog: function (Item) {
            var self = this;

            this.Loader.show();

            var FuncSubmit = function () {
                var Input = Popup.getContent().getElement('input');
                var title = Input.value.trim();

                if (title === '') {
                    Input.value = '';
                    Input.focus();
                    return;
                }

                Popup.Loader.show();

                self.$renameCategory(
                    Item.getAttribute('id'),
                    title
                ).then(function (success) {
                    Popup.Loader.hide();

                    if (!success) {
                        return;
                    }

                    Popup.close();
                    self.refresh();
                });
            };

            // open popup
            var Popup = new QUIConfirm({
                'class'    : 'pcsg-gpm-passwords-categories-rename',
                'maxHeight': 200,
                maxWidth   : 350,
                'autoclose': true,

                'title'   : QUILocale.get(lg, 'controls.categories.map.rename.title'),
                'texticon': 'fa fa-edit',
                'icon'    : 'fa fa-edit',

                events: {
                    onOpen  : function () {
                        var Content = Popup.getContent();

                        Content.set(
                            'html',
                            '<label>' +
                            '<span>' + QUILocale.get(lg, 'controls.categories.map.rename.label') + '</span>' +
                            '<input type="text"/>' +
                            '</label>'
                        );

                        var Input = Content.getElement('input');

                        Input.value = Item.getAttribute('title');

                        Input.addEvents({
                            keyup: function (event) {
                                if (event.code === 13) {
                                    FuncSubmit();
                                    this.blur();
                                }
                            }
                        });

                        Input.focus();
                        Input.select();
                    },
                    onSubmit: FuncSubmit,
                    onClose : function () {
                        self.Loader.hide();
                    }
                }
            });

            Popup.open();
        },

        /**
         * Open delete category dialog
         *
         * @param {Object} Item - qui/controls/sitemap/Item
         */
        $deleteCategoryDialog: function (Item) {
            var self = this;

            this.Loader.show();

            // open popup
            var Popup = new QUIConfirm({
                'class'    : 'pcsg-gpm-passwords-categories-delete',
                'maxHeight': 250,
                maxWidth   : 400,
                'autoclose': true,

                'title'   : QUILocale.get(lg, 'controls.categories.map.delete.title'),
                'texticon': 'fa fa-trash',
                'icon'    : 'fa fa-trash',

                events: {
                    onOpen  : function () {
                        var Content = Popup.getContent();

                        Content.set(
                            'html',
                            QUILocale.get(lg, 'controls.categories.map.delete.info', {
                                catId   : Item.getAttribute('id'),
                                catTitle: Item.getAttribute('title')
                            })
                        );
                    },
                    onSubmit: function () {
                        Popup.Loader.show();

                        self.$deleteCategory(Item.getAttribute('id')).then(function (success) {
                            Popup.Loader.hide();

                            if (!success) {
                                return;
                            }

                            Popup.close();
                            self.refresh();
                        });
                    },
                    onClose : function () {
                        self.Loader.hide();
                    }
                }
            });

            Popup.open();
        },

        /**
         * Create new password category
         *
         * @param {number} [parentId] - Parent ID of new category (if omitted create root category)
         * @param {string} title - new category title
         * @return {Promise}
         */
        $addCategory: function (title, parentId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_create',
                    resolve, {
                        'package': 'pcsg/grouppasswordmanager',
                        parentId : parentId,
                        title    : title,
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Rename password category
         *
         * @param {number} id - category id
         * @param {string} title - new category title
         * @return {Promise}
         */
        $renameCategory: function (id, title) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_rename',
                    resolve, {
                        'package': 'pcsg/grouppasswordmanager',
                        id       : id,
                        title    : title,
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Delete password category
         *
         * @param {integer} id
         * @return {Promise}
         */
        $deleteCategory: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post(
                    'package_pcsg_grouppasswordmanager_ajax_passwords_categories_public_delete',
                    resolve, {
                        'package': 'pcsg/grouppasswordmanager',
                        id       : id,
                        onError  : reject
                    }
                );
            });
        },

        /**
         * Get IDs of all selected categories
         *
         * @return {Array}
         */
        getSelectedCategoryIds: function () {
            var selectedChildren = this.$CategoryMap.getSelectedChildren();
            var categoryIds      = [];

            for (var i = 0, len = selectedChildren.length; i < len; i++) {
                var Item = selectedChildren[i];

                var id = Item.getAttribute('id');

                if (id) {
                    categoryIds.push(id);
                }
            }

            return categoryIds;
        },

        /**
         * Deselects all categories
         */
        deselectAll: function () {
            this.$CategoryMap.deselectAllChildren();
        }
    });
});