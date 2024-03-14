<?php

namespace Audentio\LaravelUploader\Models\Interfaces;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface ContentUploadModelInterfaces
{
    public function content(): MorphTo;
    public function upload(): BelongsTo;
}