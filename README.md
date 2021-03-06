# audentio/laravel-uploader

## Installing

`composer require audentio/laravel-uploader`

## Getting Started

1. Run `php artisan vendor:publish` and choose the option for the library
2. Create an `Upload` model and implement the `\Audentio\LaravelUploader\Models\Interfaces\UploadModelInterface` interface, and use the `\Audentio\LaravelUploader\Models\Traits\UploadModelTrait` trait, Create the GraphQL type/resource, and reference these in config/audentioUploader.php
3. In routes/api.php add `\Audentio\LaravelUploader\LaravelUploader::routes();` to the end to register the uploader routes

## Setting up uploads for a new content type

### Model

You'll need to make sure to implmement the `Audentio\LaravelUploader\Models\Interfaces\UploadContentInterface` interface, and use the `Audentio\LaravelUploader\Models\Traits\UploadContentTrait` trait on your model class.

Finally, you'll need to define a `_getUploaderConfig` method on your model that defines all the upload content fields and variants. For example:

```php
protected function _getUploaderConfig(): array
{
    return [
        'thumbnail' => [
            'variants' => [
                'thumb' => [
                    'width' => 528,
                    'height' => 280,
                ],
            ],
        ],
    ];
}
```

Allowed options for each content field include `allowed_types`, `max_files`, `max_size`, `variants`.

Allowed options for each variant include `type` (`fill`, or `fit`), `width`, and `height`.

### GraphQL Resource

#### Fields

In both the `getOutputFields` and `getInputFields` for your GraphQL resource you'll need to add the following:

##### Output Fields

`Model::addUploadGraphQLOutputFields($this->getGraphQLTypeName(), $fields);`

##### Input Fields

`Model::addUploadGraphQLInputFields($baseScope, $fields);`

#### Mutations

##### Setup Upload Args (Before initializing the model)

```php
$uploads = Model::setupUploadArgs($args['model']);
```

#### Validate Uploads (Before saving the model)

```php
if (!$model->validateUploads($uploads, $errors)) {
    $this->validationError($info, $errors, 'model');
}
```

#### Save Uploads (After saving the model, preferrably in the same transaction)

```php
$model->attachUploads($uploads);
```

## Uploading Files

### Get an Upload

GET `/api/upload/{id}`

### Create Upload

POST `/api/upload`

Headers:

Parameters:

* `upload` The uploaded file
* `content_type` The content type (Should typically match the GraphQL Type name)
* `content_field` The upload field name (Will be defined on the input fields for the GraphQL Type associated under `uploads`)

Response Example:

```json
{
  "success": true,
  "message": null,
  "payload": {
    "upload": {
      "id": "2e5a78f3-2dfe-4298-a73d-7de03937541a",
      "content_type": "Model",
      "content_field": "thumbnail",
      "variants": [
        {
          "file_hash": "75daeb8e884c63a7d3f3ecc3e325f606",
          "file_type": "image\/png",
          "file_size": 52375,
          "width": 276,
          "height": 146,
          "variant": "thumb",
          "url": "https:\/\/bucket-name.s3.us-west-1.amazonaws.com\/Uploads\/Model\/thumbnail\/2e5a78f3-2dfe-4298-a73d-7de03937541a\/thumb_fileName.png"
        }
      ]
    }
  }
}
```