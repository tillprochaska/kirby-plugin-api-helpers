<?php

namespace TillProchaska\ApiHelpers;
require_once 'Schema.php';

use \Kirby\Cms\Page as KirbyPage;

class Page {

    private $page;
    private $schema;

    /**
     * Creates a new Page instance
     *
     * @param Page $page Kirby page object
     * @param array $schema Schema array
     */
    public function __construct(KirbyPage $page, ?array $schema = []) {
        $this->page   = $page;
        $this->schema = new Schema($schema);
    }

    /**
     * Returns an associative array representing the given `Page` object
     * and including the fields passed via the `$fields` array.
     * 
     * @return array An associative array representing the page
     */
    public function toArray(): array {
        $data = [
            'slug' => $this->page->slug(),
        ];

        foreach($this->schema->fields() as $field) {
            $value = $this->page->content()->get($field);
            $transformer = $this->schema->transformer($field);
            $data[$field] = call_user_func($transformer, $value);
        }

        return $data;
    }

}