<?php

namespace Audentio\LaravelUploader;

use Audentio\LaravelBase\Utils\ContentTypeUtil;
use Audentio\LaravelUploader\Http\Controllers\UploadController;
use Audentio\LaravelUploader\Models\Interfaces\UploadContentInterface;

class LaravelUploader
{
    protected static $runsMigrations = true;
    const IMAGES = ['image/jpeg', 'image/gif', 'image/png'];
    const FILES = [
        'image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'text/plain', 'audio/mp4', 'audio/mpeg',
        'audio/wav', 'audio/x-ms-wma', 'video/mp4', 'video/avi', 'video/mpeg', 'video/x-ms-wmv', 'video/quicktime',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template', 'application/x-gzip',
        'application/pdf', 'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.presentationml.template',
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow', 'application/rtf',
        'application/x-tar', 'application/zip', 'application/x-compressed', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template'
    ];

    public static function routes()
    {
        \Route::post(config('audentioUploader.uploadRoute'), [UploadController::class, 'store']);
        \Route::get(config('audentioUploader.uploadRoute') . '/{upload}', [UploadController::class, 'show']);
    }

    public static function ignoreMigrations(bool $ignore = true): void
    {
        self::$runsMigrations = !$ignore;
    }

    public static function runsMigrations(): bool
    {
        return self::$runsMigrations;
    }
}
