<?php

namespace TillProchaska\ApiHelpers;

final class CollectionCase extends KirbyTestCase {

    public function setUp() {
        $this->kirby();
    }

    public function testCanBeCreatedWithKirbyPages() {
        $products = page('products')->children();
        $collection = new Collection($products);
        $this->assertInstanceof(Collection::class, $collection);
    }

    public function testReturnsArrayRepresentation() {
        $products = page('products')->children();
        $collection = new Collection($products);

        $this->assertEquals([
            ['slug' => 'product-a'],
            ['slug' => 'product-b'],
            ['slug' => 'product-c'],
        ], $collection->toArray());
    }

}