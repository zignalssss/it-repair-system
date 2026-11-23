<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_api_root_returns_json_without_requiring_a_view(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
