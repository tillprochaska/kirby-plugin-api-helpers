<?php

namespace TillProchaska\ApiHelpers;

final class SchemaCase extends KirbyTestCase {

    public function setUp() {
        $this->kirby();
    }

    public function testCanBeCreatedWithSchemaArray(): void {
        $productSchema = new Schema(['title' => 'string']);
        $this->assertInstanceOf(Schema::class, $productSchema);
    }

    public function testCanBeCreatedWithoutArgument(): void {
        $productSchema = new Schema(null);
        $this->assertInstanceOf(Schema::class, $productSchema);

        $productSchema = new Schema();
        $this->assertInstanceOf(Schema::class, $productSchema);
    }

    public function testCannotBeCreatedWithInvalidSchema(): void {
        $this->expectException('TypeError');
        $productSchema = new Schema('InvalidSchema');
    }

    public function testReturnsCorrectSchemaFields(): void {
        $productSchema = new Schema(['title' => 'string']);
        $this->assertSame(['title'], $productSchema->fields());
    }

    public function testReturnsCorrectFieldKeysEvenIfTransformerOmitted(): void {
        $productSchema = new Schema(['title']);
        $this->assertSame(['title'], $productSchema->fields());
    }

    public function tesReturnsCorrectTransformer(): void {
        $transformer = function() { return '$ 1,000,000'; };
        $productSchema = new Schema(['price' => $transformer]);

        $this->assertEquals(
            $transformer->call(null),
            $productSchema->transformer('price')->call(null)
        );
    }

    public function testReturnsCorrectDefaultTransformer(): void {
        $title = page('products/product-a')->title();
        $price = page('products/product-a')->price();

        $productSchema = new Schema([
            'title' => 'string',
            'price' => 'float',
        ]);

        $this->assertEquals(
            Schema::$transformers['string']->call($this, $title),
            $productSchema->transformer('title')->call($this, $title)
        );

        $this->assertEquals(
            Schema::$transformers['float']->call($this, $price),
            $productSchema->transformer('price')->call($this, $price)
        );
    }

    public function testReturnsDefaultStringTransformerIfTransformerOmitted(): void {
        $string = Schema::$transformers['string'];
        $productSchema = new Schema(['title']);
        $this->assertEquals($string, $productSchema->transformer('title'));
    }

    public function testThrowsExceptionIfDefaultTransformerUnknown(): void {
        $this->expectException('Exception');
        $productSchema = new Schema(['title' => 'invalidTransformer']);
        $productSchema->transformer('title');
    }


    /**
     * String Transformer
     */
    public function testTransformsFieldIntoString(): void {
        $title = page('products/product-a')->title();
        $transformer = Schema::$transformers['string'];

        $title = $transformer($title);
        $this->assertInternalType('string', $title);
        $this->assertEquals('Product A', $title);
    }

    /**
     * Integer Transformer
     */
    public function testTransformsFieldIntoInteger(): void {
        $rating = page('products/product-a')->rating();
        $transformer = Schema::$transformers['integer'];
        
        $rating = $transformer($rating);
        $this->assertInternalType('integer', $rating);
        $this->assertEquals(5, $rating);
    }

    /**
     * Float Transformer
     */
    public function testTransformsFieldIntoFloat(): void {
        $price = page('products/product-a')->price();
        $transformer = Schema::$transformers['float'];
        
        $price = $transformer($price);
        $this->assertInternalType('float', $price);
        $this->assertEquals(99.99, $price);
    }

    /**
     * Split Transformer
     */
    public function testSplitsFieldIntoArray(): void {
        $categories = page('products/product-a')->categories();
        $transformer = Schema::$transformers['split'];

        $categories = $transformer($categories);
        $this->assertInternalType('array', $categories);
        $this->assertEquals(['category-a', 'category-b'], $categories);
    }

    public function testSplitsFieldsIntoArrayUsingCustomDelimiter(): void {
        $categories = page('products/product-c')->categories();
        $transformer = Schema::$transformers['split'];

        $categories = $transformer($categories, '|');
        $this->assertInternalType('array', $categories);
        $this->assertEquals(['category-c', 'category-d'], $categories);
    }

    /**
     * Page Transformer
     */
    public function testTransformsPageReferenceIntoArrayRepresentation(): void {
        $manufacturer = page('products/product-a')->manufacturer();
        $transformer = Schema::$transformers['page'];

        $manufacturer = $transformer($manufacturer);
        $this->assertInternalType('array', $manufacturer);
        $this->assertEquals(['slug' => 'brand-a'], $manufacturer);
    }

    public function testTransformsPageReferenceIntoArrayRepresentationUsingCustomSchema(): void {
        $manufacturer = page('products/product-a')->manufacturer();
        $transformer = Schema::$transformers['page'];

        $manufacturer = $transformer($manufacturer, ['title']);
        $this->assertEquals(['slug' => 'brand-a', 'title' => 'Brand A'], $manufacturer);
    }

    public function testReturnsNullIfPageReferenceCannotBeResolved(): void {
        $manufacturer = page('products/product-b')->manufacturer();
        $transformer = Schema::$transformers['page'];

        $manufacturer = $transformer($manufacturer);
        $this->assertNull($manufacturer);
    }

    /**
     * Collection Transformer
     */
    public function testTransformsPageReferencesIntoArrayRepresentation(): void {
        $products = page('manufacturers/brand-a')->products();
        $transformer = Schema::$transformers['collection'];

        $products = $transformer($products);
        $this->assertEquals([
            ['slug' => 'product-a'],
            ['slug' => 'product-b'],
        ], $products);   
    }

    public function testTransformsPageReferencesIntoArrayRepresentationUsingCustomSchema(): void {
        $products = page('manufacturers/brand-a')->products();
        $transformer = Schema::$transformers['collection'];

        $products = $transformer($products, ['title']);
        $this->assertEquals([
            ['slug' => 'product-a', 'title' => 'Product A'],
            ['slug' => 'product-b', 'title' => 'Product B'],
        ], $products);   
    }

    public function testRemoveFalsyValuesIfPageReferenceCannotBeResolved(): void {
        $products = page('manufacturers/brand-b')->products();
        $transformer = Schema::$transformers['collection'];

        $products = $transformer($products);
        $this->assertEquals([['slug' => 'product-c']], $products);
    }

}