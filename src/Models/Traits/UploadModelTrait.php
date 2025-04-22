<?php

namespace Audentio\LaravelUploader\Models\Traits;

use Audentio\LaravelBase\Utils\ContentTypeUtil;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

trait UploadModelTrait
{
    public function contentUploads(): HasMany
    {
        return $this->hasMany(config('audentioUploader.contentUploadModel'));
    }

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
                $url = $this->getStorageUrl($variant);
                $variantData[] = array_merge($variants['data'][$mapped], [
                    'variant' => $variant,
                    'url' => $url,
                ]);
            }
        }

        if (empty($variantData)) {
            return [
                array_merge($variants['data']['original'], [
                    'variant' => 'original',
                    'url' => $this->getStorageUrl('original')
                ])
            ];
        }

        return $variantData;
    }

    public function getVariantsForGraphQL(): array
    {
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

        $url = Storage::url($urlPath);
        if (str_contains($url, '?')) {
            $url .= '&';
        } else {
            $url .= '?';
        }
        $url .= '_v=' . md5($this->updated_at->timestamp);

        return $url;
    }

    public function isAttached(): bool
    {
        return $this->contentUploads()->exists();
    }

    public function rebuildContentCount(bool $save = true): void
    {
        $this->content_count = $this->contentUploads()->count();

        if ($save) {
            $this->save();
        }
    }

    public function initializeUploadModelTrait(): void
    {
        $this->casts['colors'] = 'array';
        $this->casts['variants'] = 'array';
        $this->casts['meta'] = 'array';
        $this->guarded = [];
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
