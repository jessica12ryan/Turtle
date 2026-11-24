<?php

namespace Tests\Unit;

use Tests\TestCase;

final class BackupArchiveTest extends TestCase
{
    public function testArchiveWithEnvIsRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'turtle_zip_');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('.env', 'APP_KEY=secret');
        $zip->addFromString('database.sql', 'SELECT 1;');
        $zip->close();

        try {
            $check = new \ZipArchive();
            $check->open($path);
            self::assertTrue(backup_archive_contains_env($check));
            $check->close();
        } finally {
            @unlink($path);
        }
    }

    public function testNormalBackupWithEnvExampleIsAccepted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'turtle_zip_');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('.env.example', 'APP_KEY=');
        $zip->addFromString('database.sql', 'SELECT 1;');
        $zip->close();

        try {
            $check = new \ZipArchive();
            $check->open($path);
            self::assertFalse(backup_archive_contains_env($check));
            $check->close();
        } finally {
            @unlink($path);
        }
    }
}
