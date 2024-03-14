<?php

namespace Audentio\LaravelUploader\Models\Interfaces;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface UploadModelInterface
{
    public function contentUploads(): HasMany;
    public function user(): BelongsTo;
    public function getApiData(): array;
    public function getVariantData(): array;
    public function getVariantsForGraphQL(): array;
    public function getStoragePath(): ?string;
    public function getStorageFilePath(string $variant = null): ?string;
    public function isAttached(): bool;
    public function rebuildContentCount(bool $save = true): void;
}