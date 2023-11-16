<?php

namespace Audentio\LaravelUploader\Images;

use ColorThief\ColorThief;
use ColorThief\Exception\NotSupportedException;
use GuzzleHttp\Client;
use Intervention\Image\Drivers\Abstract\AbstractImage;
use Intervention\Image\EncodedImage;
use Intervention\Image\ImageManager;

class ImageManipulator
{
    protected $imagePath;
    protected $hasSafeImage = null;
    protected $safeImagePath;
    protected $mimeType;

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

    public function getDominantColor(): ?string
    {
        $safeImagePath = $this->getSafeImagePath();
        if (!$safeImagePath) {
            return null;
        }

        try {
            return ColorThief::getColor($safeImagePath, outputFormat: 'hex');
        } catch (NotSupportedException $e) {
            return null;
        }
    }

    public function getColorPalette(int $colorCount = 10): ?array
    {
        $safeImagePath = $this->getSafeImagePath();
        if (!$safeImagePath) {
            return null;
        }

        $dominantColor = $this->getDominantColor();
        try {
            $additionalColors = ColorThief::getPalette($safeImagePath, $colorCount, outputFormat: 'hex');
        } catch (NotSupportedException $e) {
            $additionalColors = [];
        }
        if (!in_array($dominantColor, $additionalColors)) {
            array_unshift($additionalColors, $dominantColor);
        }

        $uniqueColors = [];
        foreach ($additionalColors as $color) {
            if (!$color) {
                continue;
            }

            if (!in_array($color, $uniqueColors)) {
                $uniqueColors[] = $color;
            }
        }

        return array_slice($uniqueColors, 0, $colorCount);
    }

    public function getWidth()
    {
        return $this->getDimensions()[0];
    }

    public function getHeight()
    {
        return $this->getDimensions()[1];
    }

    public function getMimeType(): string
    {
        if (!$this->mimeType) {
            $this->mimeType = mime_content_type($this->imagePath) ?? 'image/jpeg';
        }

        return $this->mimeType;
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
        $image->fit($width, $height);

        return $this->saveThumbnail($image, $tempPath);
    }

    public function createThumbnailToFit($width, $height)
    {
        $tempPath = tempnam('/tmp', '');

        $image = $this->imageObj();
        $image->scaleDown($width, $height);

        return $this->saveThumbnail($image, $tempPath);
    }

    public function saveThumbnail(AbstractImage $image, string $tempPath): array
    {
        $encoded = $this->encodeImage($image);

        $encoded->save($tempPath);
        
        return [
            'temp_path' => $tempPath,
            'file_size' => filesize($tempPath),

            'file_type' => $encoded->mimetype(),
            'file_hash' => md5_file($tempPath),
            'width' => $image->size()->getWidth(),
            'height' => $image->size()->getHeight(),
        ];
    }

    protected function getSafeImagePath(): ?string
    {
        if ($this->hasSafeImage === null) {
            $this->safeImagePath = tempnam('/tmp', '');
            $mimeType = $this->getMimeType();

            $image = null;
            switch ($mimeType) {
                case 'image/png':
                    $image = imagecreatefrompng($this->imagePath);
                    break;
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($this->imagePath);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($this->imagePath);
                    break;
            }

            if (!$image) {
                $this->hasSafeImage = false;
                $this->safeImagePath = null;
                return null;
            }

            imagejpeg($image, $this->safeImagePath, 100);
            imagedestroy($image);
            $this->hasSafeImage = true;
        }

        return $this->safeImagePath;
    }

    protected function encodeImage(AbstractImage $image): EncodedImage
    {
        switch ($this->getMimeType()) {
            case 'image/png':
                return $image->toPng();
            case 'image/jpeg':
                return $image->toJpeg();
            case 'image/gif':
                return $image->toGif();
        }

        throw new \LogicException('Unable to encode type: ' . $this->getMimeType());
    }

    protected function imageObj(): AbstractImage
    {
        $driver = 'gd';
        if (config('audentioUploader.useImagick')) {
            $driver = 'imagick';
        }

        $manager = new ImageManager($driver);

        return $manager->make($this->imagePath);
    }
}
