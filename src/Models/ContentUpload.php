<?php

namespace Audentio\LaravelUploader\Models;

use App\Models\User;
use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelBase\Foundation\AbstractPivot;
use Audentio\LaravelUploader\Models\Interfaces\ContentUploadModelInterfaces;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use Audentio\LaravelUploader\Models\Traits\ContentUploadModelTrait;
use Audentio\LaravelUploader\Models\Traits\UploadModelTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentUpload extends AbstractPivot implements ContentUploadModelInterfaces
{
    use ContentUploadModelTrait;
}