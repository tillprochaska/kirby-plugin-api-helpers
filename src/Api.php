<?php

namespace TillProchaska\ApiHelpers;

require_once 'Page.php';
require_once 'Collection.php';

use \Kirby\Http\Response;
use \Kirby\Cms\Page as KirbyPage;
use \Kirby\Cms\Pages as KirbyPages;

class Api {

    private $base;
    private $headers;
    private $routes;
    private $schema;

    /**
     * Creates a new Api instance
     *
     * @param string $base Root path
     */
    public function __construct(string $base = '', array $headers = []) {
        $this->base = trim($base, '/');
        $this->headers = $headers;
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
    public function route(string $pattern, string $method, callable $action): Api {
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
                $arguments = array_merge([ $self ], $arguments);
                $data = call_user_func_array($route['action'], $arguments);
                return $self->autoResponse($data);
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
                return $self->errorResponse(404);
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
    public function autoResponse($data): Response {
        if($data instanceof Response) {
            return $data;
        }

        if(!is_array($data)) {
            throw new \TypeError('Parameter has to be array or instance of \Kirby\Response');
        }

        $status = $data['status'] ?? null;
        $schema = $data['schema'] ?? null;

        if(array_key_exists('page', $data)) {
            return $this->pageResponse($data['page'], $schema, $status);
        }

        if(array_key_exists('collection', $data)) {
            return $this->collectionResponse($data['collection'], $schema, $status);
        }

        return $this->successResponse($data);
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
            return $this->errorResponse(404);
        }

        $data = new Page($page, $schema);
        $data = $data->toArray();
        return $this->successResponse($data, $code);

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
            return $this->errorResponse(404);
        }

        $data = new Collection($collection, $schema);
        $data = $data->toArray();
        return $this->successResponse($data);

    }

    /**
     * Returns a JSON formatted success response
     *
     * @param array $data Response data
     * @param integer $code HTTP status code
     */
    public function successResponse(array $data, ?int $code = null): Response {
        if(!$code) $code = 200;

        return $this->jsonResponse([
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
    public function errorResponse(?int $code = null, ?string $message = null): Response {
        if(!$code) $code = 500;

        $messages = [
            200 => 'Success',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal Server Error',
        ];

        return $this->jsonResponse([
            'status' => 'error',
            'code' => $code,
            'message' => $message ?? $messages[$code] ?? '',
        ], $code);
    }

    /**
     * Returns a JSON formatted response
     *
     * @param array $data Response body
     * @param integer $code HTTP status code
     */
    public function jsonResponse(?array $data = null, ?int $code = null): Response {
        return new Response(
            json_encode($data),
            'application/json',
            $code ?? 200,
            $this->headers
        );
    }

    /**
     * Add a new route for HTTP GET requests
     */
    public function get(string $pattern, callable $action): Api {
        return $this->route($pattern, 'GET', $action);
    }

    /**
     * Add a new route for HTTP POST requests
     */
    public function post(string $pattern, callable $action): Api {
        return $this->route($pattern, 'POST', $action);
    }

    /**
     * Add a new route for HTTP PUT requests
     */
    public function put(string $pattern, callable $action): Api {
        return $this->route($pattern, 'PUT', $action);
    }

    /**
     * Add a new route for HTTP PATCH requests
     */
    public function patch(string $pattern, callable $action): Api {
        return $this->route($pattern, 'PATCH', $action);
    }

    /**
     * Add a new route for HTTP DELETE requests
     */
    public function delete(string $pattern, callable $action): Api {
        return $this->route($pattern, 'DELETE', $action);
    }

}