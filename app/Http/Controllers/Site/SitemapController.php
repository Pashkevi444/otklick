<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Карта публичного сайта (sitemap.xml) для поисковиков. Перечисляет
 * индексируемые маркетинговые страницы; ссылку на неё указываем в robots.txt и
 * подаём в Яндекс.Вебмастер / Google Search Console.
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        /** @var list<array{loc: string, changefreq: string, priority: string}> $urls */
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('site.capabilities'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('site.pricing'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => route('site.contacts'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('site.privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('site.offer'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('site.terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('site.consent'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc>'.
                '<changefreq>'.$u['changefreq'].'</changefreq>'.
                '<priority>'.$u['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
