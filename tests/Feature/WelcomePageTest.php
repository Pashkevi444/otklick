<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class WelcomePageTest extends TestCase
{
    public function test_welcome_page_renders_inertia_component(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->has('appName')
                ->has('laravelVersion')
                ->has('phpVersion')
            );
    }
}
