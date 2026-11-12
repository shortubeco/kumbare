<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $isWebDomain = $this->isWebDomain();

        $body = $isWebDomain
            ? "User-agent: *\nDisallow: /api/\nDisallow: /sifre-sifirla\nDisallow: /panel\nDisallow: /yonetim\n\nSitemap: ".url('/sitemap.xml')."\n"
            // API alt alan adı arama motorlarında görünmemeli.
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $base = rtrim(url('/'), '/');

        $xml = Cache::remember('web.sitemap.v1.'.md5($base), now()->addHours(6), function () use ($base) {
            $urls = [];
            $add = function (string $path, ?string $lastmod = null, string $priority = '0.5') use (&$urls, $base) {
                $urls[] = '<url><loc>'.htmlspecialchars($base.$path, ENT_XML1).'</loc>'
                    .($lastmod ? '<lastmod>'.substr($lastmod, 0, 10).'</lastmod>' : '')
                    .'<priority>'.$priority.'</priority></url>';
            };

            $add('/', null, '1.0');
            $add('/restoranlar', null, '0.9');
            $add('/isletmeler-icin', null, '0.6');

            foreach (DB::select("SELECT DISTINCT c.slug FROM cities c JOIN branches b ON b.city_id = c.id
                                 JOIN businesses bu ON bu.id = b.business_id
                                 WHERE b.status = 'active' AND bu.status = 'approved' ORDER BY c.slug") as $c) {
                $add('/restoranlar/'.$c->slug, null, '0.8');
            }
            foreach (DB::select("SELECT DISTINCT c.slug AS city, d.slug AS district FROM districts d
                                 JOIN cities c ON c.id = d.city_id JOIN branches b ON b.district_id = d.id
                                 JOIN businesses bu ON bu.id = b.business_id
                                 WHERE b.status = 'active' AND bu.status = 'approved' ORDER BY c.slug, d.slug") as $d) {
                $add('/restoranlar/'.$d->city.'/'.$d->district, null, '0.6');
            }
            foreach (DB::select("SELECT slug FROM categories WHERE is_active ORDER BY sort_order") as $cat) {
                $add('/kategori/'.$cat->slug, null, '0.7');
            }
            foreach (DB::select("SELECT bu.slug AS business, b.slug AS branch, b.updated_at FROM branches b
                                 JOIN businesses bu ON bu.id = b.business_id
                                 WHERE b.status = 'active' AND bu.status = 'approved' ORDER BY b.id") as $b) {
                $add('/restoran/'.$b->business.'/'.$b->branch, (string) $b->updated_at, '0.7');
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $urls)."\n</urlset>\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function isWebDomain(): bool
    {
        $webDomain = config('app.web_domain');

        return ! $webDomain || request()->getHost() === $webDomain;
    }
}
