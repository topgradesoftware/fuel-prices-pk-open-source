<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_legal_pages_are_reachable(): void
    {
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
    }
}
