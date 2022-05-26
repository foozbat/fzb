<?php
namespace Fzb;

function myhelper() : void
{
    print("I'M A HELPER");
}

// DEPENDENCY INJECTION

function fzb_get_config(): ?Config
{
    if (isset($GLOBALS['FZB_SETTINGS_OBJECT'])) {
        return $GLOBALS['FZB_SETTINGS_OBJECT'];
    }
    return null;
}

function fzb_get_database(): ?Database
{
    if (isset($GLOBALS['FZB_DATABASE_OBJECT'])) {
        return $GLOBALS['FZB_DATABASE_OBJECT'];
    }
    return null;
}

function fzb_get_router(): ?Router
{
    if (isset($GLOBALS['FZB_ROUTER_OBJECT'])) {
        return $GLOBALS['FZB_ROUTER_OBJECT'];
    }
    return null;
}