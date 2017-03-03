<?php

/**
 * This file contains \Pcsg\GroupPasswordManager\Constants\Tables
 */

namespace Pcsg\GroupPasswordManager\Constants;

/**
 * Constants for different crypto operations
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Crypto
{
    /**
     * Length of the version string that is appended to every cipher
     *
     * @var int
     */
    const VERSION_LENGTH = 30;

    /**
     * The algorithm used to encrypt data sent from the frontend to the server
     *
     * @var string
     */
    const COMMUNICATION_ENCRYPTION_ALGO = 'aes-128-cbc';
}
