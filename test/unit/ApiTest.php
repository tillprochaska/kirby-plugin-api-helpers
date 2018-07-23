<?php

namespace TillProchaska\ApiHelpers;

final class ApiCase extends KirbyTestCase {

    public function setUp() {
        $this->kirby();
        $this->api = new Api('/v1/');
    }

    public function testCanBeCreated() {
        $this->assertInstanceof(Api::class, $this->api);
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
        $routes = $this->api->route('/product/(:all)', 'GET', function($slug) {
            return ['slug' => $slug];
        })->routes();

        $this->assertEquals('v1/product/(:all)', $routes[0]['pattern']);
        $this->assertEquals('GET', $routes[0]['method']);
    }

    public function testAddRoutesInGivenOrder() {
        $routes = $this->api
            ->route('/products', 'GET', function() {})
            ->route('/product/(:all)', 'GET', function($slug) {})
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
        $routes = $this->api->route('/product/(:all)', 'GET', function($slug) {
            return ['slug' => $slug];
        })->routes();
        $response = json_decode($routes[0]['action']->call($this, 'product-a')->body(), true);
        $this->assertEquals(['slug' => 'product-a'], $response);
    }

    public function testConvertsPageToJsonRepresentation() {
        $response = $this->api->response([
            'page' => page('products/product-a'),
        ]);
        $this->assertEquals($this->api->successResponse([
            'slug' => 'product-a',
        ]), $response);
    }

    public function testReturnsErrorResponseIfInvalidPage() {
        $response = $this->api->response([
            'page' => page('products/invalid-product'),
        ]);
        $this->assertEquals($this->api->errorResponse(404), $response);
    }

    public function testConvertsCollectionToJsonRepresentation() {
        $response = json_decode($this->api->response([
            'collection' => page('products')->children(),
        ])->body(), true);
        $this->assertEquals(200, $response['code']);
        $this->assertEquals([
            ['slug' => 'product-a'],
            ['slug' => 'product-b'],
            ['slug' => 'product-c'],
        ], $response['data']);
    }

    public function testConvertsPlainArrayToJsonRepresentation() {
        $response = json_decode($this->api->response([
            'key' => 'value',
        ])->body(), true);
        $this->assertEquals(['key' => 'value'], $response);
    }

    public function testReturnsErrorResponseIfActionDoesNotReturnArray() {
        $this->expectException('TypeError');
        $response = $this->api->response('Invalid Body');
    }

}