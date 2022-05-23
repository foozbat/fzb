<?php
/* 
	file:         database.class.php
	type:         Class Definition
	written by:   Aaron Bishop
	description:  
        This class is a wrapper for PDO to reduce boilerplate and provide a cleaner, more Perl DBI-like interface.
    usage:
        Instantiate with $inputs = new Database('type', 'hostname','username','password','database');
        Define inputs with 
        Access inputs with $inputs['myinput']
*/

namespace Fzb;

use Fzb;
use Exception;

class AuthException extends Exception { }

class Auth extends Fzb\DataObject
{
    public function __construct()
    {
        parent::__construct();
        //
    }
}