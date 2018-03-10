<?php

namespace Sequry\Core\Exception;

class PermissionDeniedException extends Exception
{
    protected $code = 401;
}
