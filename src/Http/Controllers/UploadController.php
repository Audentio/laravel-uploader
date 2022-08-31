<?php

namespace Audentio\LaravelUploader\Http\Controllers;

use Audentio\LaravelBase\Foundation\AbstractController;
use Audentio\LaravelBase\Foundation\Traits\ApiResponseHandlerTrait;
use Audentio\LaravelUploader\Http\Controllers\Traits\UploadControllerTrait;
use Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface;
use Audentio\LaravelUploader\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UploadController extends AbstractController
{
    use UploadControllerTrait, ApiResponseHandlerTrait;

    public function show(Request $request, $id)
    {
        if (!Auth::user()) {
            return $this->unauthorized();
        }

        $uploadModelClass = config('audentioUploader.uploadModel');

        /** @var Upload|UploadModelInterface $upload */
        $upload = $uploadModelClass::find($id);
//        if (!$upload || !$upload->content_id) {
//            return $this->notFound();
//        }

        if ($upload->user_id !== Auth::user()->id) {
            return $this->unauthorized();
        }

        return $this->success(['upload' => $upload->getApiData()]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()) {
            return $this->unauthorized();
        }

        return $this->uploadControllerAction($request);
    }

    protected function handleUploadSuccess(UploadModelInterface $upload){
        return $this->success([
            'upload' => $upload->getApiData(),
        ]);
    }

    protected function handleUploadErrors(int $status, string $errorType, array $errors)
    {
        if ($errorType === 'validation') {
            return $this->validationError($errors);
        }

        return $this->error($errorType, $errors);
    }
}