<?php

namespace Tests\Unit;

use App\Controllers\TurtleTestController;
use App\Core\Router;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/fixtures/route_controller.php';
        TurtleTestController::reset();
    }

    public function testNamedParameterIsPassedToController(): void
    {
        $router = new Router();
        $router->get('/users/{id}', 'TurtleTestController@show');

        $router->dispatch('GET', '/users/42');

        self::assertSame([['show', '42']], TurtleTestController::$calls);
    }

    public function testQueryStringIsIgnoredWhenMatching(): void
    {
        $router = new Router();
        $router->get('/users/{id}', 'TurtleTestController@show');

        $router->dispatch('GET', '/users/42?tab=history&x=1');

        self::assertSame([['show', '42']], TurtleTestController::$calls);
    }

    public function testTrailingSlashIsTolerated(): void
    {
        $router = new Router();
        $router->get('/users/{id}', 'TurtleTestController@show');

        $router->dispatch('GET', '/users/42/');

        self::assertSame([['show', '42']], TurtleTestController::$calls);
    }

    public function testRouteWithoutParametersCallsController(): void
    {
        $router = new Router();
        $router->post('/users', 'TurtleTestController@index');

        $router->dispatch('POST', '/users');

        self::assertSame([['index']], TurtleTestController::$calls);
    }

    public function testMethodMismatchRendersNotFound(): void
    {
        $router = new Router();
        $router->post('/users', 'TurtleTestController@index');

        ob_start();
        $router->dispatch('GET', '/users');
        $output = ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertStringContainsString('Page Not Found', $output);
        self::assertSame([], TurtleTestController::$calls);
    }

    public function testHandlerMustUseControllerAtMethodFormat(): void
    {
        $router = new Router();
        $router->get('/broken', 'NoSeparatorHere');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must be in 'Controller@method' format");

        $router->dispatch('GET', '/broken');
    }

    public function testMissingControllerClassThrows(): void
    {
        $router = new Router();
        $router->get('/broken', 'ThisControllerDoesNotExist@index');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('not found');

        $router->dispatch('GET', '/broken');
    }
}
