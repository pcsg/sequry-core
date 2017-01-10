<?php

/**
 * This file contains Pcsg\GroupPasswordManager\Security\Authentication\Cache
 */
namespace Pcsg\GroupPasswordManager\Security\Authentication;

use QUI;
use Stash;

/**
 * Class Cache
 *
 * Basic cache for authentication purposes
 */
class Cache extends QUI\QDOM
{
    /**
     * Cache stash for search cache
     *
     * @var Stash\Pool
     */
    protected static $Stash = null;

    /**
     * Set data to product search cache
     *
     * @param string $name
     * @param mixed $data
     * @param int|\DateTime|null $time -> sekunden oder datetime
     *
     * @return Stash\Item
     */
    public static function set($name, $data, $time = null)
    {
        $Item = self::getStashItem($name);

        $Item->set($data);
        $Item->setTTL($time);

        return $Item;
    }

    /**
     * Get data from product search cache
     *
     * @param string $name
     * @return string|array|object|boolean
     * @throws QUI\Cache\Exception
     */
    public static function get($name)
    {
        try {
            $Item   = self::getStashItem($name);
            $data   = $Item->get();
            $isMiss = $Item->isMiss();
        } catch (\Exception $Exception) {
            throw new QUI\Cache\Exception(
                QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.lib.cache.manager.not.exist'
                ),
                404
            );
        }

        if ($isMiss) {
            throw new QUI\Cache\Exception(
                QUI::getLocale()->get(
                    'quiqqer/system',
                    'exception.lib.cache.manager.not.exist'
                ),
                404
            );
        }

        return $data;
    }

    /**
     * Empty cache
     *
     * @param string|boolean $key (optional) - if no key given, cash is cleared completely
     */
    public static function clear($key = null)
    {
        self::getStashItem($key)->clear();
    }

    /**
     * Return a specific cache item
     *
     * @param string $key (optional) - cache name / cache key
     * @return Stash\Item
     */
    protected static function getStashItem($key = null)
    {
        if (is_null($key)) {
            $key = md5(__FILE__) . '/gpm/';
        } else {
            $key = md5(__FILE__) . '/gpm/' . $key;
        }

        return self::getStash()->getItem($key);
    }

    /**
     * Get product cache stash
     *
     * @return Stash\Pool
     * @throws QUI\Exception
     */
    protected static function getStash()
    {
        if (!is_null(self::$Stash)) {
            return self::$Stash;
        }

        $cacheDir = self::getCacheDir();

        try {
            $handlers[] = new Stash\Driver\FileSystem(array(
                'path' => $cacheDir
            ));

            $Handler = new Stash\Driver\Composite(array(
                'drivers' => $handlers
            ));

            $Stash = new Stash\Pool($Handler);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: getStash() -> ' . $Exception->getMessage()
            );

            throw new QUI\Exception(array(
                'pcsg/grouppasswordmanager',
                'exception.authentication.cache.initialize.error'
            ));
        }

        self::$Stash = $Stash;

        return self::$Stash;
    }

    /**
     * Get base cache dir
     *
     * @return string
     */
    protected static function getCacheDir()
    {
        $cacheDir = QUI::getPackage('pcsg/grouppasswordmanager')->getVarDir() . 'cache/products/search/';

        if (!file_exists($cacheDir)
            || !is_dir($cacheDir)
        ) {
            QUI\Utils\System\File::mkdir($cacheDir);
        }

        return $cacheDir;
    }
}
