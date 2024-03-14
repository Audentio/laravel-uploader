<?php

namespace Audentio\LaravelUploader\Resources;

use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelBase\Utils\ContentTypeUtil;
use Audentio\LaravelUploader\Images\ImageManipulator;
use Audentio\LaravelUploader\Models\Interfaces\UploadContentInterface;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use Audentio\LaravelUploader\Models\Traits\UploadModelTrait;
use Audentio\LaravelUploader\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class UploadData
{
    protected $contentType;
    protected $contentField;

    /** @var UploadedFile */
    protected $upload;

    /** @var ImageManipulator|null */
    protected $imageManipulator;

    /**
     * @return Upload|UploadModelTrait
     *
     * Can't use the correct return type hint here since it's possible to overwrite the model used.
     */
    public function store(): AbstractModel
    {
        $upload = $this->getUpload();

        if ($this->isImage()) {
            $this->imageManipulator = new ImageManipulator($upload->getRealPath());
        }

        $modelClass = config('audentioUploader.uploadModel');

        /** @var Upload $model */
        $model = new $modelClass;
        $model->generateUniqueId();

        $model->forceFill([
            'content_type' => $this->contentType,
            'content_field' => $this->contentField,
            'user_id' => Auth::user() ? Auth::user()->id : null,
            'file_name' => $upload->getClientOriginalName(),
            'original_file_name' => $upload->getClientOriginalName(),
            'file_hash' => md5_file($upload->getRealPath()),
            'file_type' => $upload->getMimeType(),
            'file_size' => $upload->getSize(),
        ]);

        if ($this->isImage()) {
            $model->forceFill([
                'primary_color' => $this->imageManipulator?->getDominantColor(),
                'colors' => $this->imageManipulator?->getColorPalette(),
            ]);
        }

        $model->storage_path = $model->getStoragePath();

        $variants = [
            'original' => [
                'file_hash' => md5_file($upload->getRealPath()),
                'file_type' => $upload->getMimeType(),
                'file_size' => $upload->getSize(),

                'width' => $this->isImage() ? $this->imageManipulator->getWidth() : null,
                'height' => $this->isImage() ? $this->imageManipulator->getHeight() : null,
            ],
        ];

        $this->uploadFile($model, $upload->getRealPath());
        $variantMap = $this->createAndUploadVariants($model, $variants);

        $model->variants = [
            'data' => $variants,
            'map' => $variantMap,
        ];

        $model->save();

        return $model;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentField(): string
    {
        return $this->getContentField();
    }

    public function getUpload(): UploadedFile
    {
        return $this->upload;
    }

    public function isImage(): bool
    {
        if (strpos($this->upload->getMimeType(), 'image/') === 0 && strpos($this->upload->getMimeType(), 'image/svg') === false) {
            return true;
        }

        return false;
    }

    protected function uploadFile(UploadModelInterface $upload, string $filePath, ?string $variant = null): void
    {
        $storagePath = $upload->getStoragePath();
        $fileName = $upload->getStorageFileName($variant);

        \Storage::putFileAs($storagePath, $filePath, $fileName, 'public');
    }

    protected function createAndUploadVariants(UploadModelInterface $upload, array &$variants): array
    {
        $modelClass = $upload->content_type;
        /** @var UploadContentInterface|AbstractModel $modelInstance */
        $modelInstance = new $modelClass;

        $config = $modelInstance->getUploaderConfig()[$this->contentField];

        $variantMap = [];
        foreach ($config['variants'] as $id => $variant) {
            if (!$this->isImage()) {
                $variantMap[$id] = 'original';
                continue;
            }

            $variant = array_merge([
                'type' => 'fill',
                'width' => null,
                'height' => null,
            ], $variant);

            if ($variant['width'] == null || $variant['width'] == null) {
                $variantMap[$id] = 'original';
                continue;
            }

            $variantData = $this->imageManipulator->createThumbnail($variant['type'], $variant['width'], $variant['height']);
            $this->uploadFile($upload, $variantData['temp_path'], $id);

            $variants[$id] = [
                'file_hash' => $variantData['file_hash'],
                'file_type' => $variantData['file_type'],
                'file_size' => $variantData['file_size'],

                'width' => $this->isImage() ? $variantData['width'] : null,
                'height' => $this->isImage() ? $variantData['height'] : null,
            ];
            $variantMap[$id] = $id;
        }

        return $variantMap;
    }

    public function __construct(string $contentType, string $contentField, UploadedFile $upload)
    {
        $this->contentType = ContentTypeUtil::getModelClassNameForContentType($contentType);
        $this->contentField = $contentField;
        $this->upload = $upload;
    }
}