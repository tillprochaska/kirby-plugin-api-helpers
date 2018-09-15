<?php

namespace TillProchaska\ApiHelpers;

class KirbyTestCase extends \PHPUnit\Framework\TestCase {

    public function kirby() {
        $base = dirname(__DIR__);
        return new \Kirby([
            'roots' => [
                'base'     => $base,
                'content'  => $base . '/fixtures/content',
            ]
        ]);
    }
}