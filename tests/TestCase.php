<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Add fromString macro to UploadedFile for test convenience
        if (! UploadedFile::hasMacro('fromString')) {
            UploadedFile::macro('fromString', function (string $content, string $filename, ?string $mimeType = null) {
                $tempPath = tempnam(sys_get_temp_dir(), 'upload_') . '_' . $filename;
                file_put_contents($tempPath, $content);

                return new UploadedFile(
                    $tempPath,
                    $filename,
                    $mimeType,
                    null,
                    true
                );
            });
        }
    }
}
