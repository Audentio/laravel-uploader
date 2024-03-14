<?php

namespace Audentio\LaravelUploader\Models\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

interface UploadContentInterface
{
    public function uploads(): MorphToMany;

    public function getUploaderFields(): array;
    public function getUploaderConfig(): array;

    public function validateUploads(array $uploads, ?array &$errors = null): bool;
    public function attachUploads(array $uploads): void;

    public static function setupUploadArgs(array &$args): array;
}