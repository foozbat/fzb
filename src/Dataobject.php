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

use Exception;

abstract class DataObject
{
    protected $db;

    function __construct()
    {
        if (isset($GLOBALS['FZB_DATABASE_OBJECT'])) {
            $this->db = $GLOBALS['FZB_DATABASE_OBJECT'];
        } else {
            throw new Exception("Fzb\Database object could not be found.  A database object must be instantiated before using this object.");
        }
    }
}