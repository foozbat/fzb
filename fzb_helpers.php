<?php
namespace Fzb;

use Exception;

function myhelper() : void
{
    print("I'M A HELPER");
}

function fzb_self() : string
{
    
}

// DEPENDENCY INJECTION

function fzb_get_config(): ?Config
{
    return $GLOBALS['FZB_SETTINGS_OBJECT'] ?? null;
}

function fzb_get_database(): ?Database
{
    return $GLOBALS['FZB_DATABASE_OBJECT'] ?? null;
}

function fzb_get_router(): ?Router
{
    return $GLOBALS['FZB_ROUTER_OBJECT'] ?? null;
}