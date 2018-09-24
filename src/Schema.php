<?php

namespace TillProchaska\ApiHelpers;
require_once 'Page.php';

use \Closure;
use \Kirby\Http\Response;
use \Kirby\Cms\Field as KirbyField;

/**
 * Schemas specify the structure of the representation of
 * a page in an API response. A schema is a set of field names
 * to include in the response along with a transformer. Transformers
 * are functions that are passed an Kirby field object and return
 * a value that can easily be converted to JSON, in most cases a
 * primitive value, an array of an associative array.
 *
 * For common use cases, such as converting a raw field value into
 * an integer or transforming a page reference, a list of default
 * transformers is included. These transformers may instead be
 * referenced by their respective key, e. g. `integer` or `page`.
 *
 * If the transformer is completely omitted, the default `string`
 * transformer will be used as a fallback. For example, the following
 * two schemas are equivalent.
 * 
 *   $restaurantSchema = [
 *     'title',
 *     'description',
 *     'averageRating' => 'float',
 *   ];
 *
 *   $restaurantSchema = [
 *      'title' => 'string'
 *      'description' => 'string',
 *      'averageRating' => 'float',
 *   ];
 */
class Schema {

    public static $transformers = [];
    private $schema = [];

    public function __construct(?array $schema = []) {
        if(!$schema) $schema = [];
        $this->schema = $schema;
    }

    /**
     * Returns an array of all field names present in the schema
     *
     * @return array Array of field names
     */
    public function fields(): array {
        $fields = array_keys($this->schema);

        // In the schema array, the transformer may be completely
        // omitted. In this case, the field name is a single value
        // in the schema array, rather than an array key.
        foreach($fields as $key => $field) {
            if(is_integer($field)) {
                $fields[$key] = $this->schema[$field];
            }
        }

        return $fields;
    }

    /**
     * Returns the transformer function for the given field
     *
     * @param string $field Field name
     */
    public function transformer(string $field): Closure {
        $transformer = $this->schema[$field] ?? null;
        $arguments = [];
        $closure = null;

        // If the transformer name is ommitted for a field in the
        // schema array, fallback to the default `string` transformer.
        if(!$transformer && in_array($field, $this->schema)) {
            $transformer = 'string';
        }

        // If the schema array value is an array, the first element is
        // treated as the transformer and all following elements are
        // passed to the transformer as arguments.
        if(is_array($transformer)) {
            $arguments = array_slice($transformer, 1);
            $transformer = $transformer[0];
        }

        // If a closure is passed, it’ll be directly used as the
        // transformers. Otherwise, if a default transformer exists,
        // the matching default transformer will be used.
        if(is_object($transformer) && $transformer instanceof \Closure) {
            $transformer = $transformer;
        } else if(array_key_exists($transformer, self::$transformers)) {
            $transformer =  self::$transformers[$transformer];
        } else {
            throw new \Exception('Invalid transformer for field "' . $field . '"');
        }

        // Finally, a new closure that incorporates all transformer
        // passed via the schema array, is returned.
        return function($value) use($transformer, $arguments) {
            $arguments = array_merge([$value], $arguments);
            return call_user_func_array($transformer, $arguments);
        };
    }

}

Schema::$transformers = [

    /**
     * Transforms a field into a simple string, basically simply
     * returning the raw field value.
     */
    'string' => function(KirbyField $field): string {
        return $field->toString();
    },

    /**
     * Interprets the field value as integer.
     */
    'integer' => function(KirbyField $field): int {
        return $field->toInt();
    },

    /**
     * Interprets the field value as float.
     */
    'float' => function(KirbyField $field): float {
        return $field->toFloat();
    },

    /**
     * Splits the raw field value into an array based on the
     * given divider.
     */
    'split' => function(KirbyField $field, string $delimiter = ','): array {
        return $field->split($delimiter);
    },

    /**
     * Attempts to find a page using the field value as id, and
     * returns an array representation of the page including the
     * fields specified in the `$schema` array.
     */
    'page' => function(KirbyField $field, array $schema = []): ?array {
        $page = $field->toPage();
        if(!$page) return null;

        $page = new Page($page, $schema);
        return $page->toArray();
    },

    /**
     * Attempts to create a collection of pages based on a
     * YAML-formatted list of page ids, and returns an array
     * representation of the pages including the fields specified
     * in the `$schema` array.
     */
    'collection' => function(KirbyField $field, array $schema = []): ?array {
        $data = $field->toData('yaml');

        $pages = array_map(function($id) use($schema) {
            $page = page($id);
            if(!$page) return null;

            $page = new Page($page, $schema);
            return $page->toArray();
        }, $data);

        return array_filter($pages);
    },

];