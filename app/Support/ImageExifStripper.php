<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelJpeg;

class ImageExifStripper
{
    public static function strip(UploadedFile $file): UploadedFile
    {
        $mime = $file->getMimeType();

        if (! in_array($mime, ['image/jpeg', 'image/jpg'], true) || ! $file->isValid()) {
            return $file;
        }

        $temp = tempnam(sys_get_temp_dir(), 'exif-');

        if ($temp === false) {
            return $file;
        }

        try {
            $data = file_get_contents($file->getRealPath());

            if ($data === false || ! str_starts_with($data, "\xFF\xD8")) {
                unlink($temp);

                return $file;
            }

            $jpeg = new PelJpeg(new PelDataWindow($data));
            $jpeg->clearExif();

            file_put_contents($temp, $jpeg->getBytes());

            return new UploadedFile(
                $temp,
                $file->getClientOriginalName(),
                $file->getClientMimeType(),
                $file->getError(),
                true
            );
        } catch (\Throwable $e) {
            if (file_exists($temp)) {
                unlink($temp);
            }

            return $file;
        }
    }
}
