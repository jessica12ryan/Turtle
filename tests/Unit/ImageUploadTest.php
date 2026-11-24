<?php

namespace Tests\Unit;

use Tests\TestCase;

final class ImageUploadTest extends TestCase
{
    public function testNonImageFileIsRejected(): void
    {
        self::assertNull(prepare_image_upload(['tmp_name' => __FILE__]));
    }

    public function testPngIsPassedThroughUnchanged(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'turtle_png_');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        ));

        try {
            $prepared = prepare_image_upload(['tmp_name' => $path]);
            self::assertNotNull($prepared);
            self::assertSame('image/png', $prepared['mime']);
            self::assertSame('png', $prepared['ext']);
            self::assertFalse($prepared['converted']);
            self::assertSame($path, $prepared['tmp_name']);
        } finally {
            @unlink($path);
        }
    }

    public function testHeicIsConvertedToJpeg(): void
    {
        $fixture = dirname(__DIR__) . '/fixtures/sample.heic';
        $prepared = prepare_image_upload(['tmp_name' => $fixture]);

        self::assertNotNull($prepared, 'HEIC uploads must be accepted');
        self::assertSame('image/jpeg', $prepared['mime']);
        self::assertSame('jpg', $prepared['ext']);
        self::assertTrue($prepared['converted']);
        self::assertNotSame($fixture, $prepared['tmp_name']);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($prepared['tmp_name']);
        self::assertSame('image/jpeg', $mime);
        @unlink($prepared['tmp_name']);
    }
}
