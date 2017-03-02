<?php

namespace Pcsg\GroupPasswordManager\Handler;

use Pcsg\GroupPasswordManager\Constants\Tables;
use Pcsg\GroupPasswordManager\Password;
use Pcsg\GroupPasswordManager\Security\Handler\CryptoActors;
use QUI;
use QUI\Permissions\Permission;

/**
 * Class Categories
 *
 * Handler for public and private password categories
 *
 * @package Pcsg\GroupPasswordManager\Handler
 */
class Categories
{
    const TBL_CATEGORIES_PUBLIC  = 'pcsg_gpm_categories_public';
    const TBL_CATEGORIES_PRIVATE = 'pcsg_gpm_categories_private';

    /**
     * List of public categories
     *
     * @var array
     */
    protected static $publicList = null;

    /**
     * List of private categories
     *
     * @var array
     */
    protected static $privateList = array();

    /**
     * Create new public category
     *
     * @param string $title - new category title
     * @param int $parentId (optional) - parent id; if omitted, create new root category
     * @return void
     */
    public static function createPublic($title, $parentId = null)
    {
        self::checkPublicPermissions();

        QUI::getDataBase()->insert(
            self::getPublicTable(),
            array(
                'title'    => $title,
                'parentId' => $parentId
            )
        );
    }

    /**
     * Rename public category
     *
     * @param int $id - category id
     * @param string $title - new category title
     * @return void
     */
    public static function renamePublic($id, $title)
    {
        self::checkPublicPermissions();

        QUI::getDataBase()->update(
            self::getPublicTable(),
            array(
                'title' => $title
            ),
            array(
                'id' => $id
            )
        );
    }

    /**
     * Delete public category
     *
     * @param int $id - category id
     * @param bool $deleteChildren (optional) - also delete child categories [default: true]
     * @return void
     */
    public static function deletePublic($id, $deleteChildren = true)
    {
        self::checkPublicPermissions();

        // change parent ids from categories that had this category as parent to grandparent
        if ($deleteChildren) {
            $childrenIds = self::getPublicCategoryChildrenIds($id);

            if (!empty($childrenIds)) {
                QUI::getDataBase()->delete(
                    self::getPublicTable(),
                    array(
                        'id' => array(
                            'type'  => 'IN',
                            'value' => $childrenIds
                        )
                    )
                );
            }
        } else {
            $parentId = self::getPublicCategoryParentId($id);

            QUI::getDataBase()->update(
                self::getPublicTable(),
                array(
                    'parentId' => $parentId ?: null
                ),
                array(
                    'parentId' => $id
                )
            );
        }

        // delete category
        QUI::getDataBase()->delete(
            self::getPublicTable(),
            array(
                'id' => $id
            )
        );
    }

    /**
     * Get public categories
     *
     * @param array $ids - category IDs
     * @return array
     *
     * @throws QUI\Exception
     */
    public static function getPublic($ids)
    {
        if (empty($ids)) {
            return array();
        }

        return QUI::getDataBase()->fetch(array(
            'from'  => self::getPublicTable(),
            'where' => array(
                'id' => array(
                    'type'  => 'IN',
                    'value' => $ids
                )
            )
        ));
    }

    /**
     * Get list of all public categories
     *
     * @return array
     */
    public static function getPublicList()
    {
        if (!is_null(self::$publicList)) {
            return self::$publicList;
        }

        $result = QUI::getDataBase()->fetch(array(
            'from' => self::getPublicTable()
        ));

        if (empty($result)) {
            return array();
        }

        $categories = array();

        foreach ($result as $row) {
            $row['children'] = array();

            if (empty($row['parentId'])) {
                $row['parentId'] = false;
            }

            $categories[] = $row;
        }

        self::$publicList = self::createCategoryTree($categories);

        return self::$publicList;
    }

    /**
     * Get list of all categories that belong to the family tree of a category (no siblings!)
     *
     * @param int $categoryId - child category
     * @return array
     */
    public static function getPublicCategoryFamilyList($categoryId)
    {
        $family = array(
            $categoryId
        );

        $parentId = self::getPublicCategoryParentId($categoryId);

        while (!empty($parentId)) {
            $family[] = $parentId;
            $parentId = self::getPublicCategoryParentId($parentId);
        }

        return $family;
    }

    /**
     * @todo Algorithmus lieber rekursiv anstatt über DB
     *
     * Get all children IDs of a public category
     *
     * @param $categoryId
     * @return array|false - false if no children found, list of children IDs otherwise
     */
    protected static function getPublicCategoryChildrenIds($categoryId, &$list = array())
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => self::getPublicTable(),
            'where'  => array(
                'parentId' => $categoryId
            )
        ));

        if (empty($result)) {
            return array();
        }

        foreach ($result as $row) {
            $list[] = $row['id'];
            self::getPublicCategoryChildrenIds($row['id'], $list);
        }

        return $list;
    }

    /**
     * @todo Algorithmus lieber rekursiv anstatt über DB
     *
     * Get parent ID of a public category
     *
     * @param int $categoryId
     * @return false|int - false if category has no parent, id otherwise
     */
    protected static function getPublicCategoryParentId($categoryId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'parentId'
            ),
            'from'   => self::getPublicTable(),
            'where'  => array(
                'id' => $categoryId
            )
        ));

        if (empty($result)) {
            return false;
        }

        return $result[0]['parentId'];
    }

    /**
     * Create new public category
     *
     * @param string $title - new category title
     * @param int $parentId (optional) - parent id; if omitted, create new root category
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return void
     */
    public static function createPrivate($title, $parentId = null, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        self::checkPrivatePermissions($User);

        QUI::getDataBase()->insert(
            self::getPrivateTable(),
            array(
                'title'    => $title,
                'parentId' => $parentId,
                'userId'   => $User->getId()
            )
        );
    }

    /**
     * Rename public category
     *
     * @param int $id - category id
     * @param string $title - new category title
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return void
     */
    public static function renamePrivate($id, $title, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        self::checkPrivatePermissions($User);

        QUI::getDataBase()->update(
            self::getPrivateTable(),
            array(
                'title' => $title
            ),
            array(
                'id'     => $id,
                'userId' => $User->getId()
            )
        );
    }

    /**
     * Delete public category
     *
     * @param int $id - category id
     * @param bool $deleteChildren (optional) - also delete child categories [default: true]
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return void
     */
    public static function deletePrivate($id, $deleteChildren = true, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        self::checkPrivatePermissions($User);

        // change parent ids from categories that had this category as parent to grandparent
        if ($deleteChildren) {
            $childrenIds = self::getPrivateCategoryChildrenIds($id);

            if (!empty($childrenIds)) {
                QUI::getDataBase()->delete(
                    self::getPrivateTable(),
                    array(
                        'id'     => array(
                            'type'  => 'IN',
                            'value' => $childrenIds
                        ),
                        'userId' => $User->getId()
                    )
                );
            }
        } else {
            $parentId = self::getPrivateCategoryParentId($id);

            QUI::getDataBase()->update(
                self::getPrivateTable(),
                array(
                    'parentId' => $parentId ?: null
                ),
                array(
                    'parentId' => $id,
                    'userId'   => $User->getId()
                )
            );
        }

        // delete category
        QUI::getDataBase()->delete(
            self::getPrivateTable(),
            array(
                'id'     => $id,
                'userId' => $User->getId()
            )
        );
    }

    /**
     * Get private category/categories
     *
     * @param array $ids - category IDs
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return array
     *
     * @throws QUI\Exception
     */
    public static function getPrivate($ids, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        if (empty($ids)) {
            return array();
        }

        return QUI::getDataBase()->fetch(array(
            'from'  => self::getPrivateTable(),
            'where' => array(
                'id'     => array(
                    'type'  => 'IN',
                    'value' => $ids
                ),
                'userId' => $User->getId()
            )
        ));
    }

    /**
     * Get list of all private categories
     *
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return array
     */
    public static function getPrivateList($User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        if (isset(self::$privateList[$User->getId()])) {
            return self::$privateList[$User->getId()];
        }

        $result = QUI::getDataBase()->fetch(array(
            'from'  => self::getPrivateTable(),
            'where' => array(
                'userId' => $User->getId()
            )
        ));

        if (empty($result)) {
            return array();
        }

        $categories = array();

        foreach ($result as $row) {
            $row['children'] = array();

            if (empty($row['parentId'])) {
                $row['parentId'] = false;
            }

            $categories[] = $row;
        }

        self::$privateList[$User->getId()] = self::createCategoryTree($categories);

        return self::$privateList[$User->getId()];
    }

    /**
     * Get list of all categories that belong to the family tree of a category (no siblings!)
     *
     * @param int $categoryId - child category
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return array
     */
    public static function getPrivateCategoryFamilyList($categoryId, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        if (empty($categoryId)) {
            return array();
        }

        $family = array(
            $categoryId
        );

        $parentId = self::getPrivateCategoryParentId($categoryId, $User);

        while (!empty($parentId)) {
            $family[] = $parentId;
            $parentId = self::getPrivateCategoryParentId($parentId, $User);
        }

        return $family;
    }

    /**
     * @todo Algorithmus lieber rekursiv anstatt über DB
     *
     * Get all children IDs of a public category
     *
     * @param int $categoryId - category ID
     * @param array $list (optional) - mutable children id list
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return array|false - false if no children found, list of children IDs otherwise
     */
    protected static function getPrivateCategoryChildrenIds($categoryId, &$list = array(), $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => self::getPrivateTable(),
            'where'  => array(
                'parentId' => $categoryId,
                'userId'   => $User->getId()
            )
        ));

        if (empty($result)) {
            return array();
        }

        foreach ($result as $row) {
            $list[] = $row['id'];
            self::getPrivateCategoryChildrenIds($row['id'], $list, $User);
        }

        return $list;
    }

    /**
     * @todo Algorithmus lieber rekursiv anstatt über DB
     *
     * Get parent ID of a public category
     *
     * @param int $categoryId
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return false|int - false if category has no parent, id otherwise
     */
    protected static function getPrivateCategoryParentId($categoryId, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'parentId'
            ),
            'from'   => self::getPrivateTable(),
            'where'  => array(
                'id'     => $categoryId,
                'userId' => $User->getId()
            )
        ));

        if (empty($result)) {
            return false;
        }

        return $result[0]['parentId'];
    }

    /**
     * Add a password to a private category
     *
     * @param Password $Password - The Password the category is added to
     * @param array $categoryIds - category IDs
     * @param QUI\Users\User $User (optional) - category owner (if omitted = session user)
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function addPasswordToPrivateCategories(Password $Password, $categoryIds, $User = null)
    {
        if (is_null($User)) {
            $User = QUI::getUserBySession();
        }

        self::checkPrivatePermissions($User);

        $CryptoUser = CryptoActors::getCryptoUser($User->getId());

        if (!$Password->hasPasswordAccess($CryptoUser)) {
            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.handler.categories.add.private.category.no.access',
                array(
                    'userId'     => $CryptoUser->getId(),
                    'userName'   => $CryptoUser->getName(),
                    'passwordId' => $Password->getId()
                )
            ));
        }

        $family = array();

        foreach ($categoryIds as $catId) {
            $family = array_merge(
                $family,
                self::getPrivateCategoryFamilyList($catId, $User)
            );
        }

        QUI::getDataBase()->update(
            QUI::getDBTableName(Tables::USER_TO_PASSWORDS_META),
            array(
                'categories'  => empty($family) ? null : ',' . implode(',', array_unique($family)) . ',',
                'categoryIds' => empty($categoryIds) ? null : ',' . implode(',', $categoryIds) . ','
            ),
            array(
                'dataId' => $Password->getId(),
                'userId' => $User->getId()
            )
        );
    }

    /**
     * Creates a tree of categories based on parent ids
     *
     * @param array $categories - category (branch)
     * @param bool $parentId (optional) - parent id of category (branch)
     * @return array - parsed tree
     */
    protected static function createCategoryTree(&$categories, $parentId = false)
    {
        $tree = array();

        foreach ($categories as $cat) {
            if ($cat['parentId'] != $parentId) {
                continue;
            }

            $cat['children'] = self::createCategoryTree($categories, $cat['id']);
            $tree[]          = $cat;
        }

        return $tree;
    }

    /**
     * Get public categories table
     *
     * @return string
     */
    protected static function getPublicTable()
    {
        return QUI::getDBTableName(self::TBL_CATEGORIES_PUBLIC);
    }

    /**
     * Get private categories table
     *
     * @return string
     */
    protected static function getPrivateTable()
    {
        return QUI::getDBTableName(self::TBL_CATEGORIES_PRIVATE);
    }

    /**
     * Check permissions for public categories
     *
     * @throws QUI\Permissions\Exception
     */
    protected static function checkPublicPermissions()
    {
        Permission::checkPermission('gpm.categories.edit');
    }

    /**
     * Checks if the current session user is allowed to operate with private categories of another user
     *
     * @param QUI\Users\User $User - Check permission for categories of this user
     *
     * @throws QUI\Exception
     */
    protected static function checkPrivatePermissions($User)
    {
        $SessionUser = QUI::getUserBySession();

        if ((int)$SessionUser->getId() === (int)$User->getId()) {
            return;
        }

        if ($User->isSU()) {
            return;
        }

        throw new QUI\Exception(array(
            'pcsg/grouppasswordmanager',
            'exception.handler.categories.no.private.permission',
            array(
                'userName' => $User->getName(),
                'userId'   => $User->getId()
            )
        ));
    }

    /**
     * Check if a public category exists
     *
     * @param int $catId
     * @return bool
     */
    public static function publicCategoryExists($catId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => self::getPublicTable(),
            'where' => array(
                'id' => (int)$catId
            ),
            'limit' => 1
        ));

        return current(current($result)) > 0;
    }

    /**
     * Check if a public category exists
     *
     * @param int $catId
     * @return bool
     */
    public static function privateCategoryExists($catId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => self::getPrivateTable(),
            'where' => array(
                'id' => (int)$catId
            ),
            'limit' => 1
        ));

        return current(current($result)) > 0;
    }
}
