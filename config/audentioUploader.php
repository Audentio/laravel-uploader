<?php

return [
    'uploadModel' => \Audentio\LaravelUploader\Models\Upload::class,
    'uploadRoute' => '/upload',
    'uploadGraphQLType' => null,
    'useImagick' => (bool) env('AUDENTIO_UPLOADER_IMAGICK', false),
];