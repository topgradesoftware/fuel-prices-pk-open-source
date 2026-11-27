<?php

namespace App\Contracts;

use App\Models\Article;

interface DistributionAdapter
{
    public function channel(): string;

    public function enabled(): bool;

    /**
     * @return array{status: string, payload?: array<string, mixed>, error?: string}
     */
    public function distribute(Article $article): array;
}
