<?php

namespace Pcsg\GroupPasswordManager\Exception;

class InvalidAuthDataException extends Exception
{
    protected $code = 4001;

    protected $context = array(
        'pferd' => 'test'
    );
}
