<?php

namespace TillProchaska\ApiHelpers;
require_once 'Schema.php';
require_once 'Page.php';

use \Kirby\Cms\Pages as KirbyPages;

class Collection {

    private $collection;
    private $schema;

    /**
     * Creates a new Collection instance
     *
     * @param Pages $collection Collection of Kirby page objects
     * @param array $schema Schema array
     */
    public function __construct(KirbyPages $collection, ?array $schema = []) {
        $this->collection = $collection;
        $this->schema = $schema;
    }

    /**
     * Returns an array of associative arrays, eache representing a
     * single page of the collection.
     * 
     * @return array An array representing the collection of pages
     */
    public function toArray(): array {
        $data = [];

        foreach($this->collection as $page) {
            $page = new Page($page, $this->schema);
            $data[] = $page->toArray();
        }

        return $data;
    }

}