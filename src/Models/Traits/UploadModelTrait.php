<?php

namespace Audentio\LaravelUploader\Models\Traits;

use App\Models\User;
use Audentio\LaravelBase\Foundation\Traits\ContentTypeTrait;
use Audentio\LaravelBase\Utils\ContentTypeUtil;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

trait UploadModelTrait
{
    use ContentTypeTrait;

    public function getApiData(): array
    {
        return [
            'id' => $this->id,
            'content_type' => ContentTypeUtil::getFriendlyContentTypeName($this->content_type),
            'content_field' => $this->content_field,
            'primary_color' => $this->primary_color,
            'colors' => $this->colors,
            'variants' => $this->getVariantData(),
        ];
    }

    public function getVariantData(): array
    {
        $variants = $this->variants;
        $variantData = [];

        if (!empty($variants['map'])) {
            foreach ($variants['map'] as $variant => $mapped) {
                $variantData[] = array_merge($variants['data'][$mapped], [
                    'variant' => $variant,
                    'url' => $this->getStorageUrl($variant),
                ]);
            }
        }

        if (empty($variantData)) {
            return ['file' => $variants['data']['original']];
        }

        return $variantData;
    }

    public function getVariantsForGraphQL(): array
    {
        $variants = $this->getVariantData();

        return $this->getVariantData();
    }

    public function getDefaultStoragePath(): ?string
    {
        return 'Uploads/' . ContentTypeUtil::getFriendlyContentTypeName($this->content_type) . '/' . $this->content_field . '/' . $this->id . '/';
    }

    public function getStoragePath(): ?string
    {
        return $this->getDefaultStoragePath();
    }

    public function getStorageFileName(?string $variant = null): ?string
    {
        if (!$variant) {
            $variant = 'original';
        }

        return $variant . '_' . $this->file_name;
    }

    public function getStorageFilePath(?string $variant = null): ?string
    {
        return $this->storage_path . $this->getStorageFileName($variant);
    }

    public function getStorageUrl(?string $variant = null)
    {
        $path = $this->getStorageFilePath($variant);
        $pathParts = explode('/', $path);

        $urlPath = '';
        foreach ($pathParts as $key => $part) {
            $part = urlencode($part);
            $urlPath .= '/' . $part;
        }

        $urlPath = trim($urlPath, '/');

        return Storage::url($urlPath);
    }

    public function isAttached(): bool
    {
        return $this->content_id !== null;
    }

    public function initializeUploadModelTrait(): void
    {
        $this->casts['colors'] = 'array';
        $this->casts['variants'] = 'array';
        $this->casts['meta'] = 'array';
    }

    public static function bootUploadModelTrait(): void
    {
        // We want to do this after it's been deleted to make sure the db record is deleted even if there is some sort
        // of AWS issue that prevents the file from being removed.
        static::deleted(function(UploadModelInterface $upload) {
            try {
                Storage::deleteDirectory($upload->getStoragePath());
            } catch (\Exception $e) {}
        });
    }
}