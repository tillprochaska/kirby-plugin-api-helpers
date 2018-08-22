<?php

namespace TillProchaska\ApiHelpers;
use \Exception;

function action() {
    return ['function'];
}

class Controller {
    public static function action() {
        return ['method'];
    }
}

final class ApiCase extends KirbyTestCase {

    public function setUp() {
        $this->kirby();
        $this->api = new Api('/v1/', [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function testCanBeCreated() {
        $this->assertInstanceof(Api::class, $this->api);
    }

    public function testAddCustomHeaders() {
        $expected = ['Access-Control-Allow-Origin' => '*'];

        $auto = $this->api->autoResponse([])->headers();
        $this->assertEquals($auto, $expected);

        $success = $this->api->successResponse([])->headers();
        $this->assertEquals($success, $expected);
        
        $error = $this->api->errorResponse()->headers();
        $this->assertEquals($error, $expected);
    }

    public function testAddAndGetSchema() {
        $this->assertEquals($this->api, $this->api->schema('product', [
            'title',
            'price' => 'float'
        ]));
        $this->assertEquals([
            'title',
            'price' => 'float'
        ], $this->api->schema('product'));
    }

    public function testAddRoute() {
        $routes = $this->api->route('/product/(:all)', 'GET', function($api, $slug) {
            return ['slug' => $slug];
        })->routes();

        $this->assertEquals('v1/product/(:all)', $routes[0]['pattern']);
        $this->assertEquals('GET', $routes[0]['method']);
    }

    public function testAddClosureAsAction() {
        $this->api->route('/', 'GET', function() {
            return ['closure'];
        });
        $response = json_decode($this->api->routes()[0]['action']->call($this)->body(), true);
        $this->assertEquals([
            'status' => 'ok',
            'code' => 200,
            'data' => ['closure'],
        ], $response);
    }

    public function testAddFunctionAsAction() {
        $this->api->route('/', 'GET', '\TillProchaska\ApiHelpers\action');
        $response = json_decode($this->api->routes()[0]['action']->call($this)->body(), true);
        $this->assertEquals([
            'status' => 'ok',
            'code' => 200,
            'data' => ['function'],
        ], $response);
    }

    public function testAddMethodAsAction() {
        $this->api->route('/', 'GET', '\TillProchaska\ApiHelpers\Controller::action');
        $response = json_decode($this->api->routes()[0]['action']->call($this)->body(), true);
        $this->assertEquals([
            'status' => 'ok',
            'code' => 200,
            'data' => ['method'],
        ], $response);
    }

    public function testAddRoutesInGivenOrder() {
        $routes = $this->api
            ->route('/products', 'GET', function() {})
            ->route('/product/(:all)', 'GET', function() {})
            ->routes();

        $this->assertEquals('v1/products', $routes[0]['pattern']);
        $this->assertEquals('v1/product/(:all)', $routes[1]['pattern']);
    }

    public function testAddsDefaultErrorRoute() {
        $routes = $this->api->routes();
        $response = json_decode($routes[0]['action']->call($this)->body(), true);
        $this->assertEquals([
            'status' => 'error',
            'code' => 404,
            'message' => 'Not found',
        ], $response);
    }

    public function testPassesParametersToRouteAction() {
        $routes = $this->api->route('/product/(:all)', 'GET', function($api, $slug) {
            return ['slug' => $slug];
        })->routes();
        $response = json_decode($routes[0]['action']->call($this, 'product-a')->body(), true);
        $this->assertEquals([
            'status' => 'ok',
            'code' => 200,
            'data' => ['slug' => 'product-a'],
        ], $response);
    }

    public function testConvertsPageToJsonRepresentation() {
        $response = $this->api->autoResponse([
            'page' => page('products/product-a'),
        ]);
        $this->assertEquals($this->api->successResponse([
            'slug' => 'product-a',
        ]), $response);
    }

    public function testReturnsErrorResponseIfInvalidPage() {
        $response = $this->api->autoResponse([
            'page' => page('products/invalid-product'),
        ]);
        $this->assertEquals($this->api->errorResponse(404), $response);
    }

    public function testConvertsCollectionToJsonRepresentation() {
        $response = json_decode($this->api->autoResponse([
            'collection' => page('products')->children(),
        ])->body(), true);
        $this->assertEquals(200, $response['code']);
        $this->assertEquals([
            ['slug' => 'product-a'],
            ['slug' => 'product-b'],
            ['slug' => 'product-c'],
        ], $response['data']);
    }

    public function testWrapsPlainArrayAndConvertsToJsonRepresentation() {
        $response = json_decode($this->api->autoResponse([
            'key' => 'value',
        ])->body(), true);
        $this->assertEquals([
            'status' => 'ok',
            'code' => '200',
            'data' => [
                'key' => 'value',
            ],
        ], $response);
    }

    public function testReturnsErrorResponseIfActionDoesNotReturnArray() {
        $this->expectException('TypeError');
        $response = $this->api->autoResponse('Invalid Body');
    }

    public function testPassOnResponseObjectFromRouteAction() {
        $data = new \Kirby\Cms\Response('Response Body');
        $response = $this->api->autoResponse($data);
        $this->assertEquals($data, $response);
    }

    public function testCatchErrorsInActionAndReturnErrorResponse() {
        $routes = $this->api->route('/product/(:all)', 'GET', function($api, $slug) {
            $products = ['product-a', 'product-b'];
            if(!in_array($slug, $products)) {
                throw new Exception('Could not find product.', 404);
            }
            return ['slug' => $slug];
        })->routes();

        $response = json_decode($this->api->routes()[0]['action']->call($this, 'product-a')->body(), true);
        $this->assertEquals([
            'status' => 'ok',
            'code' => 200,
            'data' => ['slug' => 'product-a'],
        ], $response);

        $response = json_decode($this->api->routes()[0]['action']->call($this, 'product-c')->body(), true);
        $this->assertEquals([
            'status' => 'error',
            'code' => 404,
            'message' => 'Could not find product.',
        ], $response);
    }

    public function testAddAndGetFilter() {
        $filter = function() {
            return 'filter';
        };

        $this->assertEquals($this->api, $this->api->filter('test', $filter));
        $this->assertEquals($filter, $this->api->filter('test'));
    }

    public function testAddPresetFilterToRoute() {
        $this->api->filter('auth', function() {
            throw new Exception('Unauthorized', 403);
        });

        $routes = $this->api->route('/secret', 'GET', function($api, $slug) {
            return ['Answer to the Ultimate Question of Life, The Universe, and Everything' => '42'];
        }, 'auth')->routes();

        $response = json_decode($routes[0]['action']->call($this)->body(), true);
        $this->assertEquals([
            'status' => 'error',
            'code' => 403,
            'message' => 'Unauthorized',
        ], $response);
    }

    public function testAddAnyCallableAsFilterToRoute() {
        $routes = $this->api->route('/secret', 'GET', function($api) {
            return ['Answer to the Ultimate Question of Life, The Universe, and Everything' => $api->answer];
        }, function($api) {
            $api->answer = 42;
        })->routes();

        $response = json_decode($routes[0]['action']->call($this)->body(), true);
        $this->assertEquals([
            'status' => 'ok',
            'code' => 200,
            'data' => [
                'Answer to the Ultimate Question of Life, The Universe, and Everything' => 42,
            ],
        ], $response);
    }

    public function testAddMultipleFiltersToRoute() {
        $this->api->filter('auth', function() {
            throw new Exception('Unauthorized', 403);
        });

        $routes = $this->api->route('/secret', 'GET', function($api, $slug) {
            return ['Answer to the Ultimate Question of Life, The Universe, and Everything' => '42'];
        }, [
            function($api) {
                $api->answer = 42;
            },
            'auth',
        ])->routes();

        $response = json_decode($routes[0]['action']->call($this)->body(), true);
        $this->assertEquals(42, $this->api->answer);
        $this->assertEquals([
            'status' => 'error',
            'code' => 403,
            'message' => 'Unauthorized',
        ], $response);
    }

}