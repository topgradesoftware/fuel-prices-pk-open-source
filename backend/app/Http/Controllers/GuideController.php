<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\GuideIndexRequest;
use App\Models\Article;
use App\Support\ArticleSeo;
use App\Support\Seo;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GuideController extends Controller
{
    public function index(GuideIndexRequest $request): InertiaResponse
    {
        $data = $request->validated();
        $category = $data['category'] ?? null;
        $q = trim((string) ($data['q'] ?? ''));
        $tag = trim((string) ($data['tag'] ?? ''));

        $base = Article::query()->published()->latest('published_at');

        $articles = (clone $base)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('excerpt', 'like', $term)
                        ->orWhere('body', 'like', $term);
                });
            })
            ->when($tag !== '', fn ($query) => $query->whereJsonContains('tags', $tag))
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Article $a) => $a->toCardArray());

        return Inertia::render('Guides/Index', [
            'seoMeta' => ArticleSeo::forIndex($category, $q !== '' ? $q : null),
            'articles' => $articles,
            'categories' => Article::CATEGORIES,
            'filters' => [
                'category' => $category,
                'q' => $q,
                'tag' => $tag,
            ],
            'featured' => Article::query()->published()->where('is_featured', true)->latest('published_at')->take(3)->get()->map->toCardArray(),
            'trending' => Article::query()->published()->where('is_trending', true)->latest('published_at')->take(5)->get()->map->toCardArray(),
            'popular' => Article::query()->published()->orderByDesc('view_count')->take(5)->get()->map->toCardArray(),
            'app_name' => Seo::siteName(),
        ]);
    }

    public function show(string $slug): InertiaResponse
    {
        if (! is_string($slug) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 180) {
            abort(404);
        }

        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();
        $article->increment('view_count');

        $related = Article::query()
            ->published()
            ->where('id', '!=', $article->id)
            ->where(function ($q) use ($article) {
                $q->where('category', $article->category);
                foreach ($article->tags ?? [] as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            })
            ->latest('published_at')
            ->take(4)
            ->get()
            ->map->toCardArray();

        return Inertia::render('Guides/Show', [
            'seoMeta' => ArticleSeo::forArticle($article),
            'article' => [
                ...$article->toCardArray(),
                'body_html' => $article->bodyWithAnchors(),
                'author_name' => $article->author_name,
                'faq' => $article->faq ?? [],
                'toc' => $article->toc(),
                'updated_at' => optional($article->updated_at)?->toIso8601String(),
            ],
            'related' => $related,
            'app_name' => Seo::siteName(),
        ]);
    }

    public function rss(): Response
    {
        $items = Article::query()->published()->latest('published_at')->take(40)->get();
        $site = Seo::siteName();
        $url = Seo::siteUrl();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0"><channel>'."\n";
        $xml .= '<title>'.e($site).' Guides</title>'."\n";
        $xml .= '<link>'.e($url.'/guides').'</link>'."\n";
        $xml .= '<description>Fuel price guides, tips and market notes for Pakistan.</description>'."\n";
        $xml .= '<language>en-gb</language>'."\n";

        foreach ($items as $article) {
            $link = Seo::absoluteUrl($article->urlPath());
            $xml .= '<item>'."\n";
            $xml .= '<title>'.e($article->title).'</title>'."\n";
            $xml .= '<link>'.e($link).'</link>'."\n";
            $xml .= '<guid>'.e($link).'</guid>'."\n";
            $xml .= '<pubDate>'.e(optional($article->published_at)?->toRfc2822String() ?? '').'</pubDate>'."\n";
            $xml .= '<description>'.e($article->excerpt).'</description>'."\n";
            $xml .= '<category>'.e($article->categoryLabel()).'</category>'."\n";
            $xml .= '</item>'."\n";
        }

        $xml .= '</channel></rss>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }
}
