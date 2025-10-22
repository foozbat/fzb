<?php

use PHPUnit\Framework\TestCase;
use Fzb\Router;
use Fzb\RouterException;

class RouterTest extends TestCase
{
    private $backupServer;

    protected function setUp(): void
    {
        $this->backupServer = $_SERVER;

        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->backupServer;
        
        // Reset singleton manually
        $ref = new ReflectionClass(Router::class);
        $instance = $ref->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null);
    }

    public function testSingletonInstantiation()
    {
        $router1 = new Router();
        $this->assertInstanceOf(Router::class, $router1);

        $this->expectException(RouterException::class);
        new Router(); // Second instantiation should fail
    }

    public function testGetInstanceReturnsSingleton()
    {
        $router = Router::get_instance();
        $this->assertInstanceOf(Router::class, $router);

        $instance2 = Router::get_instance();
        $this->assertSame($router, $instance2);
    }

    public function testAddGetPostPutDeleteRoutes()
    {
        $router = new Router();

        $router->get('/get-route', function () {});
        $router->post('/post-route', function () {});
        $router->put('/put-route', function () {});
        $router->delete('/delete-route', function () {});

        $routes = $router->get_routes();
        $this->assertArrayHasKey('/get-route', $routes);
        $this->assertArrayHasKey('/post-route', $routes);
        $this->assertArrayHasKey('/put-route', $routes);
        $this->assertArrayHasKey('/delete-route', $routes);

        $this->assertContains('GET', $routes['/get-route']['method']);
        $this->assertContains('POST', $routes['/post-route']['method']);
        $this->assertContains('PUT', $routes['/put-route']['method']);
        $this->assertContains('DELETE', $routes['/delete-route']['method']);
    }

    public function testAddRouteWithoutCallbackThrowsException()
    {
        $router = new Router();

        $this->expectException(RouterException::class);
        $router->add('GET', '/test-route', null);
    }

    public function testRouteMatchCallsCallback()
    {
        $_SERVER['REQUEST_URI'] = '/hello/42';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router();

        $called = false;

        $router->get('/hello/{id}', function () use (&$called) {
            $called = true;
        });

        $result = $router->route();

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testRouteNotFoundReturnsFalse()
    {
        $_SERVER['REQUEST_URI'] = '/no-match';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router();
        $result = $router->route();

        $this->assertFalse($result);
    }

    public function testRouteParameterParsing()
    {
        $_SERVER['REQUEST_URI'] = '/user/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $router = new Router();

        $router->get('/user/{id}', function () {});

        $router->route();

        $pathVars = $router->get_path_vars();
        $this->assertArrayHasKey('id', $pathVars);
        $this->assertEquals('123', $pathVars['id']);
    }

    public function testPrefixUsage()
    {
        $router = new Router();
        $router->use_prefix('/api');

        $router->get('/test', function () {});
        $routes = $router->get_routes();

        $this->assertArrayHasKey('/api/test', $routes);
    }

    public function testRequestMethodHelpers()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $router = new Router();

        $this->assertTrue($router->is_post());
        $this->assertFalse($router->is_get());
    }

    public function testControllerDoesNotExistWithoutDir()
    {
        $router = new Router();

        $this->assertFalse($router->controller_exists());
        $this->assertNull($router->get_controller_path());
    }

    public function testRedefiningRouteThrowsException()
    {
        $router = new Router();

        $router->get('/same', function () {});

        $this->expectException(RouterException::class);
        $router->get('/same', function () {});
    }

    public function testGetAppBasePath()
    {
        $_SERVER['SCRIPT_NAME'] = '/myapp/public/index.php';
        $router = new Router();
        $this->assertEquals('/myapp/public', $router->get_app_base_path());
    }
}
