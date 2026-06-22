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

    public function test_landing_html_has_server_rendered_seo_meta(): void
    {
        // Без SSR робот видит только серверный HTML — мета/заголовок/разметка
        // ДОЛЖНЫ быть прямо в нём (а не выставляться клиентским Inertia-Head).
        $res = $this->get('/');

        $res->assertOk();
        $res->assertSee('цифровой администратор', false); // <title>
        $res->assertSee('<meta name="description"', false);
        $res->assertSee('AI-администратор для салонов', false); // текст description
        $res->assertSee('<link rel="canonical"', false);
        $res->assertSee('property="og:title"', false);
        $res->assertSee('SoftwareApplication', false); // расширенный JSON-LD @graph
        $res->assertSee('"@type":"WebSite"', false);
    }

    public function test_sitemap_xml_lists_public_pages(): void
    {
        $res = $this->get('/sitemap.xml');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $res->assertSee('<urlset', false);
        $res->assertSee('/vozmozhnosti', false);
        $res->assertSee('/tarify', false);
        $res->assertSee('/contacts', false);
        $res->assertSee('/privacy', false);
    }

    public function test_capabilities_page_renders(): void
    {
        $this->get('/vozmozhnosti')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Site/Capabilities')->has('site')->has('loginUrl'));
    }

    public function test_pricing_page_renders(): void
    {
        $this->get('/tarify')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Site/Pricing')->has('site')->has('loginUrl'));
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
