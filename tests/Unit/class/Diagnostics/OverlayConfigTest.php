<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Diagnostics;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Diagnostics\OverlayConfig;

/**
 * @covers \XoopsModules\Cssholmes\Diagnostics\OverlayConfig
 */
final class OverlayConfigTest extends TestCase
{
    public function testEmptyRequestDisablesOverlay(): void
    {
        $config = OverlayConfig::fromRequest('');

        $this->assertFalse($config->enabled());
        $this->assertSame([], $config->profiles());
    }

    public function testBooleanRequestFallsBackToHtml5(): void
    {
        $config = OverlayConfig::fromRequest('true');

        $this->assertTrue($config->enabled());
        $this->assertSame(['html5'], $config->profiles());
        $this->assertSame(['assets/css/profiles/html5.css'], $config->profileStylesheetPaths());
    }

    public function testOffRequestDisablesOverlay(): void
    {
        $config = OverlayConfig::fromRequest('off');

        $this->assertFalse($config->enabled());
        $this->assertSame([], $config->profiles());
    }

    public function testAliasesAreNormalizedAndDeduplicated(): void
    {
        $config = OverlayConfig::fromRequest('theme,widget,theme,layout');

        $this->assertSame(['xtf-theme', 'xtf-widget', 'layout'], $config->profiles());
    }

    public function testAllRequestEnablesEveryProfile(): void
    {
        $config = OverlayConfig::fromRequest('all');

        $this->assertSame(
            ['html5', 'xtf-theme', 'xtf-widget', 'a11y', 'layout'],
            $config->profiles()
        );
    }

    public function testAllProfilesReturnsSupportedOrder(): void
    {
        $this->assertSame(
            ['html5', 'xtf-theme', 'xtf-widget', 'a11y', 'layout'],
            OverlayConfig::allProfiles()
        );
    }
}
