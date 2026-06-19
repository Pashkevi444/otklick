<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SiteHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_renders_with_seo_content_and_contacts(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Site/Home')
                ->has('site.heroTitle')
                ->where('site.phone', '89237032792')
                ->where('site.telegram', 'Pashkevi4')
                ->where('site.legalName', 'ИП Балаганский Павел Сергеевич')
                ->where('site.inn', '543807917255')
                ->where('site.ogrnip', '323547600043330')
                ->has('loginUrl'));
    }

    public function test_contacts_page_renders(): void
    {
        $this->get('/contacts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Site/Contacts')
                ->where('site.email', 'support@otcl1ck.ru'));
    }

    public function test_privacy_policy_page_renders(): void
    {
        // Публичная политика конфиденциальности (152-ФЗ) — ссылку на неё требует
        // YClients Marketplace и закон о ПДн. Должна отдавать реквизиты оператора.
        $this->get('/privacy')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Site/Privacy')
                ->where('site.legalName', 'ИП Балаганский Павел Сергеевич')
                ->where('site.inn', '543807917255')
                ->has('loginUrl'));
    }
}
