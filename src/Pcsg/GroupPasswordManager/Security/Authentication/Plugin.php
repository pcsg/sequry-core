<?php

namespace Pcsg\GroupPasswordManager\Security\Authentication;

use Pcsg\GroupPasswordManager\Constants\Tables;
use QUI;
use Pcsg\GroupPasswordManager\Security\Interfaces\iAuthPlugin;

/**
 * This class is an internal represantion of an external authentication plugin
 */
class Plugin extends QUI\QDOM
{
    /**
     * ID of authentication plugin
     *
     * @var integer
     */
    protected $id = null;

    /**
     * External authentication plugin class
     *
     * @var iAuthPlugin
     */
    protected $AuthClass = null;

    /**
     * AuthPlugin constructor.
     *
     * @param integer $id - authentication plugin id
     * @throws QUI\Exception
     */
    public function __construct($id)
    {
        $result = QUI::getDataBase()->fetch(array(
            'from'  => Tables::AUTH_PLUGINS,
            'where' => array(
                'id' => (int)$id
            )
        ));

        if (empty($result)) {
            throw new QUI\Exception(
                'Authentication plugin #' . $id . ' not found.',
                404
            );
        }

        $data      = current($result);
        $classPath = $data['path'];

        try {
            $AuthClass = new $classPath();
        } catch (QUI\Exception $Exception) {
            throw new QUI\Exception(
                'Could not create instance of Authentication plugin #' . $id . ' class ->'
                . $Exception->getMessage()
            );
        }

        $this->AuthClass = $AuthClass;
        $this->id        = $data['id'];

        $this->setAttributes(array(
            'title'       => $data['title'],
            'description' => $data['description']
        ));
    }

    /**
     * Get ID of this plugin
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
}