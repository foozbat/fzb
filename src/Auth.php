<?php
/**
 * Class Auth
 * 
 * @todo implement class
 */

declare(strict_types=1);

namespace Fzb;

use Fzb;
use Exception;

class AuthException extends Exception { }

class Auth extends Fzb\Model
{
    public function __construct()
    {
        parent::__construct();
        //
    }
}