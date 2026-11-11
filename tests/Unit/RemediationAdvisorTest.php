<?php

namespace Tests\Unit;

use App\Models\Finding;
use App\Services\RemediationAdvisor;
use Tests\TestCase;

class RemediationAdvisorTest extends TestCase
{
    public function test_advisor_provides_steps_for_defacement(): void
    {
        $advisor = new RemediationAdvisor;
        $finding = new Finding(['category' => 'defacement']);

        $guide = $advisor->guideFor($finding);

        $this->assertNotEmpty($guide['summary']);
        $this->assertNotEmpty($guide['steps']);
        $this->assertNotNull($guide['config_snippet']);
        $this->assertStringContainsString('chmod', $guide['config_snippet']);
    }

    public function test_advisor_provides_gambling_injection_steps(): void
    {
        $advisor = new RemediationAdvisor;
        $finding = new Finding(['category' => 'gambling-injection']);

        $guide = $advisor->guideFor($finding);

        $this->assertStringContainsString('gambling', strtolower($guide['summary']));
        $this->assertStringContainsString('Google Search Console', implode(' ', $guide['steps']));
        $this->assertStringContainsString('location', $guide['config_snippet']);
    }

    public function test_advisor_provides_security_headers_configuration(): void
    {
        $advisor = new RemediationAdvisor;
        $finding = new Finding(['category' => 'security-headers']);

        $guide = $advisor->guideFor($finding);

        $this->assertStringContainsString('Strict-Transport-Security', $guide['config_snippet']);
        $this->assertStringContainsString('X-Content-Type-Options', $guide['config_snippet']);
    }

    public function test_advisor_fallback_for_unrecognized_category(): void
    {
        $advisor = new RemediationAdvisor;
        $finding = new Finding(['category' => 'unrecognized-custom']);

        $guide = $advisor->guideFor($finding);

        $this->assertNotEmpty($guide['summary']);
        $this->assertNotEmpty($guide['steps']);
        $this->assertNull($guide['config_snippet']);
    }
}
