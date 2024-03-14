<?php

return [
    'uploadModel' => \Audentio\LaravelUploader\Models\Upload::class,
    'contentUploadModel' => \Audentio\LaravelUploader\Models\ContentUpload::class,
    'uploadRoute' => '/upload',
    'uploadGraphQLType' => null,
    'useImagick' => (bool) env('AUDENTIO_UPLOADER_IMAGICK', false),

    'defaultMaxUploadSize' => 1000000, // 1 MB
];