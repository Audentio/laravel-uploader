<?php

namespace Audentio\LaravelUploader\Http\Controllers\Traits;

use Audentio\LaravelBase\Foundation\AbstractModel;
use Audentio\LaravelBase\Utils\ContentTypeUtil;
use Audentio\LaravelUploader\LaravelUploader;
use Audentio\LaravelUploader\Models\Interfaces\UploadContentInterface;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use Audentio\LaravelUploader\Models\Traits\UploadModelTrait;
use Audentio\LaravelUploader\Resources\UploadData;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

trait UploadControllerTrait
{
    public function uploadControllerAction(Request $request)
    {
        $uploadData = $this->getUploadData($request, $uploadErrors);
        if (!$uploadData) {
            return $this->handleUploadErrors(400, 'validation', $uploadErrors);
        }

        /** @var UploadModelTrait $upload */
        $upload = $uploadData->store();

        return $this->handleUploadSuccess($upload);
    }

    protected function getUploadData(Request $request, ?array &$errors = null): ?UploadData
    {
        $validator = \Validator::make($request->all(), [
            'content_type' => ['required', function($attribute, $value, $fail) use ($request) {
                if (!$this->validateContentTypeForUpload($value, $error)) {
                    if (is_array($error)) {
                        $error = implode("\n", $error);
                    }
                    $fail($error);
                }
            }],
            'content_field' => ['required', function($attribute, $value, $fail) use ($request) {
                $contentType = $request->get('content_type');
                if (!$this->validateContentTypeForUpload($contentType)) {
                    // Error message is already sent on the content_type field. This just prevents the rest from
                    // running if we already know that's not valid.
                    return;
                }

                if (!$this->validateContentFieldForUpload($contentType, $value, $error)) {
                    if (is_array($error)) {
                        $error = implode("\n", $error);
                    }
                    $fail($error);
                }
            }],
            'upload' => ['required', 'file', function($attribute, $value, $fail) use ($request) {
                $contentType = $request->get('content_type');
                $contentField = $request->get('content_field');

                if (!$this->validateContentTypeForUpload($contentType)) {
                    return;
                }

                if (!$this->validateContentFieldForUpload($contentType, $contentField)) {
                    return;
                }

                if (!$this->validateFileForUpload($contentType, $contentField, $value, $error)) {
                    if (is_array($error)) {
                        $error = implode("\n", $error);
                    }
                    $fail($error);
                }
            }],
        ]);

        if ($validator->errors()->count()) {
            $errors = $validator->errors()->toArray();
            return null;
        }

        return new UploadData(
            $request->get('content_type'),
            $request->get('content_field'),
            $request->file('upload')
        );
    }

    protected function validateFileForUpload(string $contentType, string $contentField, UploadedFile $upload, ?array &$errors = []): bool
    {
        $modelClass = ContentTypeUtil::getModelClassNameForContentType($contentType);
        $contentType = config('contentTypes')[$modelClass];

        /** @var UploadContentInterface|AbstractModel $instance */
        $instance = new $modelClass;

        $config = $instance->getUploaderConfig()[$contentField];

        $return = true;

        if (!in_array($upload->getMimeType(), $config['allowed_types'])) {
            $errors[] = __('Invalid file type. Allowed types: ' . implode(', ', $config['allowed_types']));
            $return = false;
        }

        if ($upload->getSize() > $config['max_size']) {
            $errors[] = __('File is too large.');
            $return = false;
        }

        return $return;
    }

    protected function validateContentFieldForUpload(string $contentType, string $contentField, ?string &$error = null): bool
    {
        $modelClass = ContentTypeUtil::getModelClassNameForContentType($contentType);
        $contentType = config('contentTypes')[$modelClass];

        /** @var UploadContentInterface|AbstractModel $instance */
        $instance = new $modelClass;

        if (!in_array($contentField, $instance->getUploaderFields())) {
            $error = __('Disallowed content field');
            return false;
        }

        return true;
    }

    protected function validateContentTypeForUpload(string $contentType, ?string &$error = null): bool
    {
        $contentType = ContentTypeUtil::getModelClassNameForContentType($contentType);
        if (!array_key_exists($contentType, config('contentTypes'))) {
            $error = __('Invalid content type');
            return false;
        }

        try {
            $instance = new $contentType;

            if (!$instance instanceof UploadContentInterface) {
                $error = __('Disallowed content type');
                return false;
            }
        } catch (\Exception $e) {
            $error = __('Unexpected error');
            return false;
        }

        return true;
    }

    protected abstract function handleUploadErrors(int $status, string $errorType, array $errors);
    protected abstract function handleUploadSuccess(UploadModelInterface $upload);
}
