<?php

namespace TillProchaska\ApiHelpers;

final class PageCase extends KirbyTestCase {

    public function setUp() {
        $this->kirby();
    }

    public function testCanBeCreatedWithKirbyPage(): void {
        $product = page('products/product-a');
        $page = new Page($product);
        $this->assertInstanceof(Page::class, $page);
    }

    public function testReturnsArrayRepresentation(): void {

        $product = page('products/product-a');
        $page = new Page($product, [
            'title',
            'price' => 'float',
            'rating' => 'integer',
        ]);

        $this->assertEquals([
            'slug' => 'product-a',
            'title' => 'Product A',
            'price' => 99.99,
            'rating' => 5,
        ], $page->toArray());

    }

    public function testReturnsArrayRepresentationIncludingReferencedPages(): void {

        $product = page('products/product-a');
        $page = new Page($product, [
            'title',
            'manufacturer' => ['page', [
                'title',
            ]],
        ]);

        $this->assertEquals([
            'slug' => 'product-a',
            'title' => 'Product A',
            'manufacturer' => [
                'slug' => 'brand-a',
                'title' => 'Brand A',
            ],
        ], $page->toArray());
        
    }

}