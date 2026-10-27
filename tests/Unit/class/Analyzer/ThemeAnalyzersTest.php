<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Analyzer;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Analyzer\AccessibilityAnalyzer;
use XoopsModules\Cssholmes\Analyzer\AnalysisContext;
use XoopsModules\Cssholmes\Analyzer\DesignTokenAnalyzer;
use XoopsModules\Cssholmes\Analyzer\Finding;
use XoopsModules\Cssholmes\Analyzer\TemplateAnalyzer;
use XoopsModules\Cssholmes\Analyzer\ThemeCatalog;
use XoopsModules\Cssholmes\Analyzer\ThemeFiles;
use XoopsModules\Cssholmes\Analyzer\ThemeManifestAnalyzer;

/**
 * @covers \XoopsModules\Cssholmes\Analyzer\AccessibilityAnalyzer
 * @covers \XoopsModules\Cssholmes\Analyzer\DesignTokenAnalyzer
 * @covers \XoopsModules\Cssholmes\Analyzer\TemplateAnalyzer
 * @covers \XoopsModules\Cssholmes\Analyzer\ThemeCatalog
 * @covers \XoopsModules\Cssholmes\Analyzer\ThemeFiles
 * @covers \XoopsModules\Cssholmes\Analyzer\ThemeManifestAnalyzer
 */
final class ThemeAnalyzersTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/themes';

    /**
     * @param Finding[] $findings
     *
     * @return string[]
     */
    private function ruleIds(array $findings): array
    {
        return array_map(static fn (Finding $finding): string => $finding->ruleId, $findings);
    }

    public function testCoreEscapedVariablesAreNotFlagged(): void
    {
        $findings = (new TemplateAnalyzer())->analyze(new AnalysisContext(self::FIXTURES . '/cleantheme'));
        $messages = implode("\n", array_map(static fn (Finding $finding): string => $finding->message, $findings));

        $this->assertStringNotContainsString('xoops_sitename', $messages);
        $this->assertStringNotContainsString('xoops_slogan', $messages);
        $this->assertStringContainsString('xoops_pagetitle', $messages);
    }

    public function testMissingIncludeIsReportedWithLine(): void
    {
        $findings = (new TemplateAnalyzer())->analyze(new AnalysisContext(self::FIXTURES . '/smartytheme'));
        $missing = array_values(array_filter($findings, static fn (Finding $finding): bool => 'tpl/include-missing' === $finding->ruleId));

        $this->assertCount(1, $missing);
        $this->assertSame(['theme.tpl', 9], [$missing[0]->target, $missing[0]->line]);
    }

    public function testAccessibilityFollowsIncludesAndIgnoresSmartyAttributes(): void
    {
        $clean = (new AccessibilityAnalyzer())->analyze(new AnalysisContext(self::FIXTURES . '/cleantheme'));
        $this->assertNotContains('a11y/img-no-alt', $this->ruleIds($clean));
        $this->assertNotContains('a11y/skip-link', $this->ruleIds($clean));
        $this->assertNotContains('a11y/no-main-landmark', $this->ruleIds($clean));

        $broken = (new AccessibilityAnalyzer())->analyze(new AnalysisContext(self::FIXTURES . '/smartytheme'));
        $images = array_values(array_filter($broken, static fn (Finding $finding): bool => 'a11y/img-no-alt' === $finding->ruleId));
        $this->assertCount(1, $images, 'Only the image without alt is reported, not the one with Smarty attributes.');
        $this->assertSame(['tpl/header.tpl', 3], [$images[0]->target, $images[0]->line]);
        $this->assertContains('a11y/no-lang', $this->ruleIds($broken));
        $this->assertContains('a11y/empty-link', $this->ruleIds($broken));
    }

    public function testGenericThemeSkipsManifestChecks(): void
    {
        $findings = (new ThemeManifestAnalyzer())->analyze(new AnalysisContext(self::FIXTURES . '/cleantheme'));

        $this->assertSame(['manifest/generic-theme'], $this->ruleIds($findings));
    }

    public function testGenericTokenCheckLearnsThePrefix(): void
    {
        $this->assertSame('st', ThemeFiles::learnTokenPrefix(ThemeFiles::stylesheets(self::FIXTURES . '/smartytheme')));

        $findings = (new DesignTokenAnalyzer())->analyze(new AnalysisContext(self::FIXTURES . '/smartytheme'));
        $this->assertSame(['token/undefined-ref'], $this->ruleIds($findings));
        $this->assertStringContainsString('--st-border', $findings[0]->message);

        $this->assertSame([], (new DesignTokenAnalyzer())->analyze(new AnalysisContext(self::FIXTURES . '/cleantheme')));
    }

    public function testXtfThemeStillGetsManifestChecks(): void
    {
        $context = new AnalysisContext(self::FIXTURES . '/xtftheme');

        $this->assertSame([], (new ThemeManifestAnalyzer())->analyze($context));
        $this->assertSame([], (new DesignTokenAnalyzer())->analyze($context));
        $this->assertSame([], (new TemplateAnalyzer())->analyze($context));
    }

    public function testCatalogListsGenericThemes(): void
    {
        $catalog = new ThemeCatalog(self::FIXTURES, sys_get_temp_dir() . '/cssholmes-no-admin-themes');
        $keys = array_map(static fn ($theme): string => $theme->key, $catalog->allForScope('site'));

        $this->assertSame(['cleantheme', 'smartytheme', 'xtftheme'], $keys);
        $this->assertTrue($catalog->find('site', 'cleantheme')?->generic);
        $this->assertSame('Clean Theme [cleantheme] (generic)', $catalog->find('site', 'cleantheme')?->label);
        $this->assertFalse($catalog->find('site', 'xtftheme')?->generic);
    }
}
