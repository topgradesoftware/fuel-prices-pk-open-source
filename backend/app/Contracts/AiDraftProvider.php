<?php

namespace App\Contracts;

use App\Models\Article;
use App\Models\ContentEvent;

interface AiDraftProvider
{
    public function name(): string;

    public function model(): string;

    /**
     * @return array{
     *   title: string,
     *   excerpt: string,
     *   body: string,
     *   tags: list<string>,
     *   faq: list<array{question:string,answer:string}>,
     *   meta?: array<string, mixed>
     * }
     */
    public function generate(Article $article, ContentEvent $event): array;
}
