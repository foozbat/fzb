<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function test_get(): void
    {
        $this->expectOutputString("world");

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = "/world";
        $_SERVER['SCRIPT_NAME'] = "/index.php";
        $_SERVER['QUERY_STRING'] = "";

        $router = new Fzb\Router();

        $router->get("/", function() {
            print "hello";
        });

        $router->get("/world", function () {
            print "world";
        });

        $router->route();
        
        $router->__destruct();
        $router = null;
    }

    public function test_post(): void
    {
        $this->expectOutputString("gotpost");

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/mypost";
        $_SERVER['SCRIPT_NAME'] = "/index.php";
        $_SERVER['QUERY_STRING'] = "";

        $router = new Fzb\Router();

        $router->post("/wrong", function () {
            print("wrong one");
        });
        $router->post("/mypost", function() {
            print("gotpost");
        });
        $router->route();

        $router->__destruct();        
        $router = null;        
    }

    public function test_singleton1(): void
    {
        $router = new Fzb\Router();

        $router1 = Fzb\Router::get_instance();
        $router2 = Fzb\Router::get_instance();;

        $router->__destruct();
        $router = null;

        $this->assertSame($router1, $router1);
    }

    public function test_singleton2(): void
    {
        $this->expectException(Fzb\RouterException::class);

        $router1 = new Fzb\Router();
        $router2 = new Fzb\Router();

        $router1->__destruct();
        $router1 = null;
    }
}