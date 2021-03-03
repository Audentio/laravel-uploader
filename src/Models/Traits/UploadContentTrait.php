<?php

namespace Audentio\LaravelUploader\Models\Traits;

use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelGraphQL\GraphQL\Definitions\Type;
use Audentio\LaravelUploader\LaravelUploader;
use Audentio\LaravelUploader\Models\Interfaces\UploadContentInterface;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;

trait UploadContentTrait
{
    protected static $uploadModelInstance;
    protected $uploadsValidated = false;

    public function uploads(): MorphMany
    {
        return $this->morphMany(config('audentioUploader.uploadModel'), 'content')->orderBy('display_order');
    }

    public function getUploaderConfig(): array
    {
        $return = [];

        foreach ($this->_getUploaderConfig() as $contentField => $config) {
            $return[$contentField] = array_merge([
                'allowed_types' => LaravelUploader::IMAGES,
                'max_files' => 1,
                'max_size' => 1000000, // 1 MB
                'variants' => [],
            ], $config);
        }

        return $return;
    }

    public function getUploaderFields(): array
    {
        return array_keys($this->getUploaderConfig());
    }

    public function validateUploads(array $uploads, ?array &$errors = null): bool
    {
        $returnErrors = [];
        $attachedUploads = $this->uploads;

        if (empty($uploads)) {
            $this->uploadsValidated = true;
            return true;
        }

        $uploadConfigs = $this->getUploaderConfig();

        $return = true;
        foreach ($uploads as $contentField => $curUploads) {
            $returnErrors[$contentField] = [];

            if (!isset($uploadConfigs[$contentField])) {
                $returnErrors[$contentField][] = __('audentioUploader::uploads.errors.unregisteredField');
                $return = false;
                continue;
            }

            $uploadConfig = $uploadConfigs[$contentField];

            if (count($curUploads) > $uploadConfig['max_files']) {
                $returnErrors[$contentField][] = __('audentioUploader::uploads.errors.tooManyFiles', [
                    'maxFiles' => $uploadConfig['max_files']
                ]);
                $return = false;
                continue;
            }

            foreach ($curUploads as $upload) {
                // Skip validating this upload if already attached.
                if ($attachedUploads->count() > 0 && $attachedUploads->where('id', $upload['id'])->isNotEmpty()) {
                    continue;
                }

                /** @var UploadModelInterface|null $model */
                $model = $upload['model'];
                if (!$model) {
                    $returnErrors[$contentField][] = __('audentioUploader::uploads.errors.uploadDoesntExist');
                    $return = false;
                    continue;
                }

                if (!Auth::user() || $model->user_id !== Auth::user()->id) {
                    $returnErrors[$contentField][] = __('audentioUploader::uploads.errors.uploadDoesntExist');
                    $return = false;
                }

                if ($model->content_type !== get_class($this)) {
                    $returnErrors[$contentField][] = __('audentioUploader::uploads.errors.uploadWrongContentType');
                    $return = false;
                }

                if ($model->content_id && $model->content_id !== $this->id) {
                    $returnErrors[$contentField][] = __('audentioUploader::uploads.errors.uploadAssociatedWithContent');
                    $return = false;
                }

                if ($model->content_field !== $contentField) {
                    $returnErrors[$contentField][] = __('audentioUploader::uploads.errors.uploadWrongContentField');
                    $return = false;
                }
            }

            if (empty($returnErrors[$contentField])) {
                unset($returnErrors[$contentField]);
            }
        }

        if ($return) {
            $this->uploadsValidated = true;
        } else {
            $errors = ['uploads' => new MessageBag($returnErrors)];
        }
        return $return;
    }

    public function attachUploads(array $uploads): void
    {
        if (!$this->uploadsValidated) {
            throw new \LogicException('Uploads cannot be attached until they have been validated.');
        }

        $newUploadIds = [];

        foreach ($uploads as $contentField => $curUploads) {
            $displayOrder = 0;
            $newUploadIds[$contentField] = [];

            foreach ($curUploads as $upload) {
                $displayOrder++;
                /** @var AbstractModel|UploadModelInterface|null $model */
                $model = $upload['model'];

                if ($model) {
                    $newUploadIds[$contentField][] = $model->id;
                    $model->content_id = $this->id;
                    $model->display_order = $displayOrder;
                    $model->save();
                }
            }
        }

        $this->load('uploads');
        $uploads = $this->uploads;

        foreach ($newUploadIds as $contentField => $uploadIds) {
            $contentFieldUploads = $uploads->where('content_field', $contentField);

            /** @var AbstractModel|UploadModelInterface $upload */
            foreach ($contentFieldUploads as $upload) {
                if (!in_array($upload->id, $uploadIds)) {
                    $upload->delete();
                }
            }
        }

        $this->load('uploads');
    }

    public static function setupUploadArgs(array &$args): array
    {
        if (!array_key_exists('uploads', $args)) {
            return [];
        }

        $uploads = $args['uploads'];
        unset($args['uploads']);

        $uploadIds = [];
        foreach ($uploads as $contentField => $uploadId) {
            if (is_array($uploadId)) {
                foreach ($uploadId as $sUploadId) {
                    $uploadIds[] = $sUploadId;
                }
                continue;
            }

            $uploadIds[] = $uploadId;
        }

        $uploadModelClass = config('audentioUploader.uploadModel');
        /** @var Collection|UploadModelInterface[]|AbstractModel[] $uploadModels */
        $uploadModels = $uploadModelClass::find($uploadIds);

        $returnData = [];
        foreach ($uploads as $contentField => $uploadId) {
            $isArray = true;
            if (!is_array($uploadId)) {
                $uploadId = [$uploadId];
                $isArray = false;
            }

            $returnData[$contentField] = [];

            foreach ($uploadId as $curUploadId) {
                if (!$curUploadId) {
                    continue;
                }
                $upload = $uploadModels->find($curUploadId);

                $returnData[$contentField][] = [
                    'id' => $curUploadId,
                    'model' => $upload,
                ];
            }
        }

        return $returnData;
    }

    public static function addUploadGraphQLInputFields(string $scope, array &$fields): void
    {
        $instance = self::getUploadContentModelInstance();

        $uploadFields = [];

        foreach ($instance->getUploaderConfig() as $contentField => $config) {
            $type = Type::id();
            if ($config['max_files'] !== 1) {
                $type = Type::listOf(Type::id());
            }

            $uploadFields[$contentField] = ['type' => $type];
        }

        if (!empty($uploadFields)) {
            $fields['uploads'] = new InputObjectType([
                'name' => $scope . 'Uploads',
                'fields' => $uploadFields
            ]);
        }
    }

    public static function addUploadGraphQLOutputFields(string $scope, array &$fields): void
    {
        $instance = self::getUploadContentModelInstance();

        $uploadFileds = [];

        foreach ($instance->getUploaderConfig() as $contentField => $config) {
            $type = \GraphQL::type(config('audentioUploader.uploadGraphQLType'));
            $isArray = false;
            if ($config['max_files'] !== 1) {
                $type = Type::listOf($type);
                $isArray = true;
            }

            $fields[$contentField] = [
                'type' => $type,
                'resolve' => function(UploadContentInterface $root, $args) use ($contentField, $isArray) {
                    /** @var AbstractModel|UploadContentInterface $root */

                    /** @var Collection|UploadModelInterface[] $uploads */
                    $uploads = $root->uploads->where('content_field', $contentField);

                    if (!$isArray) {
                        return $uploads->first();
                    }

                    return $uploads;
                }
            ];
        }
    }

    public static function getUploadContentModelInstance(): UploadContentInterface
    {
        if (!self::$uploadModelInstance) {
            $modelClass = get_called_class();
            /** @var UploadContentInterface|AbstractModel $instance */
            self::$uploadModelInstance = new $modelClass;
        }

        return self::$uploadModelInstance;
    }

    public static function bootUploadContentTrait(): void
    {
        static::deleting(function(UploadContentInterface $model) {
            try {
                $model->uploads()->delete();
            } catch (\Exception $e) {}
        });
    }

    protected abstract function _getUploaderConfig(): array;
}