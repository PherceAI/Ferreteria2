<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PwaSetupTest extends TestCase
{
    public function test_pwa_manifest_exists_and_is_valid_json(): void
    {
        $manifestPath = public_path('site.webmanifest');

        $this->assertFileExists($manifestPath);

        $content = File::get($manifestPath);
        $data = json_decode($content, true);

        $this->assertIsArray($data, 'Manifest is not valid JSON.');
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('short_name', $data);
        $this->assertArrayHasKey('display', $data);
        $this->assertEquals('standalone', $data['display']);
        $this->assertArrayHasKey('start_url', $data);
        $this->assertArrayHasKey('icons', $data);
    }

    public function test_service_worker_file_exists(): void
    {
        $swPath = public_path('sw.js');

        $this->assertFileExists($swPath, 'Service Worker sw.js is missing from the public directory.');

        $content = File::get($swPath);
        $this->assertStringContainsString('self.addEventListener', $content, 'sw.js does not seem to contain standard service worker logic.');
    }

    public function test_app_blade_includes_pwa_tags(): void
    {
        $bladePath = resource_path('views/app.blade.php');

        $this->assertFileExists($bladePath);

        $content = File::get($bladePath);

        // PWA Manifest
        $this->assertStringContainsString('<link rel="manifest" href="/site.webmanifest">', $content);

        // iOS PWA capability tags
        $this->assertStringContainsString('<meta name="apple-mobile-web-app-capable" content="yes">', $content);
        $this->assertStringContainsString('<meta name="apple-mobile-web-app-status-bar-style" content="default">', $content);

        // Android/Chrome capability
        $this->assertStringContainsString('<meta name="mobile-web-app-capable" content="yes">', $content);

        // Theme color
        $this->assertStringContainsString('<meta name="theme-color"', $content);
    }
}
