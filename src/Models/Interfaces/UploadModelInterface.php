<?php

namespace Audentio\LaravelUploader\Models\Interfaces;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface UploadModelInterface
{
    public function user(): BelongsTo;
    public function getApiData(): array;
    public function getVariantData(): array;
    public function getVariantsForGraphQL(): array;
    public function getStoragePath(): ?string;
    public function getStorageFilePath(string $variant = null): ?string;
    public function isAttached(): bool;
}