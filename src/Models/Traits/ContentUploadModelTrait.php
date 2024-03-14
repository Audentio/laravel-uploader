<?php

namespace Audentio\LaravelUploader\Models\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait ContentUploadModelTrait
{
    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(config('audentioUploader.uploadModel'));
    }

    public function initializeContentUploadModelTrait(): void
    {
        $this->guarded = [];
    }

    public static function bootContentUploadModelTrait(): void
    {
        static::saved(function (self $model) {
            $model->upload->rebuildContentCount();
        });

        static::deleted(function (self $model) {
            $model->upload->rebuildContentCount();
        });
    }
}