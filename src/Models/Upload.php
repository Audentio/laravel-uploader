<?php

namespace Audentio\LaravelUploader\Models;

use App\Models\User;
use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use Audentio\LaravelUploader\Models\Traits\UploadModelTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Upload extends AbstractModel implements UploadModelInterface
{
    use UploadModelTrait;
    protected $casts = ['variants' => 'json', 'meta' => 'json'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}