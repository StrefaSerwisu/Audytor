<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaSupportTest extends TestCase
{
    public function test_manifest_contains_installable_app_metadata(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('Audytor IT', $manifest['name']);
        $this->assertSame('/auditor', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_service_worker_contains_offline_fallback_and_cached_entrypoints(): void
    {
        $serviceWorker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString('/offline.html', $serviceWorker);
        $this->assertStringContainsString('/offline-audit.js', $serviceWorker);
        $this->assertStringContainsString('/auditor', $serviceWorker);
        $this->assertStringContainsString('/auditor/login', $serviceWorker);
        $this->assertStringContainsString('/client/login', $serviceWorker);
        $this->assertStringContainsString('networkFirst', $serviceWorker);
        $this->assertStringContainsString('audytor-it-sync', $serviceWorker);
    }

    public function test_auditor_offline_drafts_are_registered_for_audit_forms(): void
    {
        $offlineScript = file_get_contents(public_path('offline-audit.js'));
        $auditorAuditView = file_get_contents(resource_path('views/auditor/show.blade.php'));

        $this->assertStringContainsString('indexedDB', $offlineScript);
        $this->assertStringContainsString('audytor-it-offline', $offlineScript);
        $this->assertStringContainsString('data-offline-draft', $auditorAuditView);
    }

    public function test_login_screens_register_pwa_assets(): void
    {
        $this
            ->get(route('login'))
            ->assertOk()
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee('/pwa.js', false);

        $this
            ->get(route('client.login'))
            ->assertOk()
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee('/pwa.js', false);
    }
}
