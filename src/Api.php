<?php

namespace TillProchaska\ApiHelpers;

require_once 'Page.php';
require_once 'Collection.php';

use \Kirby\Http\Response;
use \Kirby\Cms\Page as KirbyPage;
use \Kirby\Cms\Pages as KirbyPages;

class Api {

    private $base;
    private $routes;
    private $schema;

    /**
     * Creates a new Api instance
     *
     * @param string $base Root path
     */
    public function __construct(string $base = '') {
        $this->base = trim($base, '/');
        $this->routes = [];
        $this->schemas = [];
    }

    /**
     * Sets or gets a schema
     *
     * @param string $name Unique schema identifier
     * @param array $schema Schema array
     * @return array|Api
     */
    public function schema(string $name, array $schema = null) {
        if(!$schema) {
            return $this->schemas[$name] ?? null;
        } else {
            $this->schemas[$name] = $schema;
            return $this;
        }
    }

    /**
     * Add a new route
     *
     * @param string $pattern Route pattern
     * @param string $method HTTP method
     * @param \Closure $action Request handler
     * @return Api
     */
    public function route(string $pattern, string $method, \Closure $action): Api {
        $this->routes[] = [
            'pattern' => $pattern,
            'method' => $method,
            'action' => $action,
        ];

        return $this;
    }

    /**
     * Returns an array of normalized routes that can be passed to
     * the `\Kirby\Cms\Router` class.
     *
     * @return array Array of normalized routes
     */
    public function routes(): array {
        $self = $this;
        $routes = [];

        foreach($this->routes as $route) {
            $action = function(string ...$arguments) use($self, $route) {
                $data = call_user_func_array($route['action'], $arguments);
                return $self->response($data);
            };

            $routes[] = [
                'pattern' => $this->base . '/' . trim($route['pattern'], '/'),
                'method' => $route['method'],
                'action' => $action,
            ];
        }

        $routes[] = [
            'pattern' => [ $this->base, $this->base . '/(:any)' ],
            'method' => 'ALL',
            'action' => function() use($self) {
                return $self::errorResponse(404);
            }
        ];

        return $routes;
    }

    /**
     * Takes an array with a `page` or `collection` key specified,
     * alternatively any array, and returns a Kirby `Response` object
     * with a JSON representation of the given data.
     *
     * @param array $data Response body
     */
    public function response(?array $data): Response {
        $status = $data['status'] ?? null;
        $schema = $data['schema'] ?? null;

        if(array_key_exists('page', $data)) {
            return $this->pageResponse($data['page'], $schema, $status);
        }

        if(array_key_exists('collection', $data)) {
            return $this->collectionResponse($data['collection'], $schema, $status);
        }

        return Response::json($data);
    }

    /**
     * Returns a Kirby `Response` object with a JSON representation
     * of the given Kirby `Page` object.
     *
     * @param Page $page Page object
     * @param string|array $schema Schema array or identifier
     * @param integer $code HTTP status code
     */
    public function pageResponse(?KirbyPage $page, $schema = null, ?int $code): Response {
       
        if(is_string($schema)) {
            $schema = $this->schema($schema);
        }

        if(!$page) {
            return self::errorResponse(404);
        }

        $data = new Page($page, $schema);
        $data = $data->toArray();
        return self::successResponse($data, $code);

    }

    /**
     * Returns a Kirby `Response` object with a JSON representation
     * of the given collection of Kirby `Page` objects.
     *
     * @param Pages $collection Collection of pages
     * @param string|array $schema Schema array or identifier
     * @param integer $code HTTP status code
     */
    public function collectionResponse(?KirbyPages $collection, $schema = null, ?int $code): Response {
        
        if(is_string($schema)) {
            $schema = $this->schema($schema);
        }

        if(!$collection) {
            return self::errorResponse(404);
        }

        $data = new Collection($collection, $schema);
        $data = $data->toArray();
        return self::successResponse($data);

    }

    /**
     * Returns a JSON formatted success response
     *
     * @param array $data Response data
     * @param integer $code HTTP status code
     */
    public static function successResponse(array $data, ?int $code = null): Response {
        if(!$code) $code = 200;

        return Response::json([
            'status' => 'ok',
            'code' => $code,
            'data' => $data,
        ], $code);
    }

    /**
     * Returns a JSON formatted error response
     *
     * @param integer $code HTTP status code
     * @param string $message Error message
     */
    public static function errorResponse(?int $code = null, ?string $message = null): Response {
        if(!$code) $code = 500;

        $messages = [
            200 => 'Success',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal Server Error',
        ];

        return Response::json([
            'status' => 'error',
            'code' => $code,
            'message' => $message ?? $messages[$code] ?? '',
        ], $code);
    }

    /**
     * Add a new route for HTTP GET requests
     */
    public function get(string $pattern, \Closure $action): Api {
        return $this->route($pattern, 'GET', $action);
    }

    /**
     * Add a new route for HTTP POST requests
     */
    public function post(string $pattern, \Closure $action): Api {
        return $this->route($pattern, 'POST', $action);
    }

    /**
     * Add a new route for HTTP PUT requests
     */
    public function put(string $pattern, \Closure $action): Api {
        return $this->route($pattern, 'PUT', $action);
    }

    /**
     * Add a new route for HTTP PATCH requests
     */
    public function patch(string $pattern, \Closure $action): Api {
        return $this->route($pattern, 'PATCH', $action);
    }

    /**
     * Add a new route for HTTP DELETE requests
     */
    public function delete(string $pattern, \Closure $action): Api {
        return $this->route($pattern, 'DELETE', $action);
    }

}