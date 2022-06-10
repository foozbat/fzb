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

// DEPENDENCY INJECTION //

function get_config(): ?Config
{
    return $GLOBALS['FZB_SETTINGS_OBJECT'] ?? null;
}

function get_database(): ?Database
{
    return $GLOBALS['FZB_DATABASE_OBJECT'] ?? null;
}

function get_router(): ?Router
{
    return $GLOBALS['FZB_ROUTER_OBJECT'] ?? null;
}

////

function register_config(Config $config): void
{
    if (!get_config()) {
        $GLOBALS['FZB_CONFIG_OBJECT'] = $config;
    }
}

function register_database(Database $database): void
{
    if (!get_database()) {
        $GLOBALS['FZB_DATABASE_OBJECT'] = $database;
    }
}

function register_router(Router $router): void
{
    if (!get_router()) {
        $GLOBALS['FZB_ROUTER_OBJECT'] = $router;
    }
}

////

function unregister_config(Config $config): void
{
    if (get_config() == $config) {
        $GLOBALS['FZB_CONFIG_OBJECT'] = null;
    }
}

function unregister_database(Database $database): void
{
    if (get_database() == $database) {
        $GLOBALS['FZB_DATABASE_OBJECT'] = null;
    }
}

function unregister_router(Router $router): void
{
    if (get_router() == $router) {
        $GLOBALS['FZB_ROUTER_OBJECT'] = null;
    }
}