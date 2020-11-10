<?php

namespace Audentio\LaravelUploader\Images;

use GuzzleHttp\Client;
use Intervention\Image\Constraint;
use Intervention\Image\ImageManagerStatic as Image;

class ImageManipulator
{
    protected $imagePath;

    protected $dimensions;

    public function __construct($imagePath)
    {
        $this->imagePath = $imagePath;
    }

    public function getDimensions()
    {
        if (!$this->dimensions) {
            $this->dimensions = getimagesize($this->imagePath);
        }

        return [
            $this->dimensions[0],
            $this->dimensions[1],
        ];
    }

    public function getWidth()
    {
        return $this->getDimensions()[0];
    }

    public function getHeight()
    {
        return $this->getDimensions()[1];
    }

    public function optimizeImage()
    {
        if (empty(config('services.kraken.apiKey')) || empty(config('services.kraken.apiSecret'))) {
            return false;
        }
        $kraken = new \Kraken(config('services.kraken.apiKey'), config('services.kraken.apiSecret'));
        try {
            $data = $kraken->upload([
                'file' => $this->imagePath,
                'wait' => true,
            ]);
        } catch (\Exception $e) {
            $data = false;
        }

        if (!empty($data['success']) && $data['success']) {
            $client = new Client();
            try {
                $client->request('GET', $data['kraked_url'], ['sink' => $this->imagePath]);
            } catch (\Exception $e) {
                return false;
            }

            return [
                'original_size' => $data['original_size'],
                'new_size' => $data['kraked_size'],
                'saved' => $data['saved_bytes'],
            ];
        }

        return false;
    }

    public function createThumbnail($type, $width, $height)
    {
        switch ($type) {
            case 'fill':
                return $this->createThumbnailToFill($width, $height);
                break;
            case 'fit':
            default:
                return $this->createThumbnailToFit($width, $height);
                break;
        }
    }

    public function createThumbnailToFill($width, $height)
    {
        $tempPath = tempnam('/tmp', '');

        $image = $this->imageObj();
        $image->fit($width, $height, function(Constraint $constraint) {
            $constraint->upsize();
        });
        $image->save($tempPath);

        $data = [
            'temp_path' => $tempPath,
            'file_size' => $image->filesize(),
            'file_type' => $image->mime(),
            'file_hash' => md5_file($tempPath),
            'width' => $image->width(),
            'height' => $image->height(),
        ];

        return $data;
    }

    public function createThumbnailToFit($width, $height)
    {
        $tempPath = tempnam('/tmp', '');

        $image = $this->imageObj();
        $image->resize($width, $height, function(Constraint $constraint) {
            $constraint->upsize();
            $constraint->aspectRatio();
        });
        $image->save($tempPath);

        $data = [
            'temp_path' => $tempPath,
            'file_size' => $image->filesize(),
            'file_type' => $image->mime(),
            'file_hash' => md5_file($tempPath),
            'width' => $image->width(),
            'height' => $image->height(),
        ];

        return $data;
    }

    protected function imageObj()
    {
        Image::configure(['driver' => 'gd']);

        return Image::make($this->imagePath);
    }
}
