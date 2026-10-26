<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Analyzer;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Analyzer\TemplateIncludeResolver;

/**
 * @covers \XoopsModules\Cssholmes\Analyzer\TemplateIncludeResolver
 */
final class TemplateIncludeResolverTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/themes';

    public function testThemeNameVariableIsResolved(): void
    {
        $resolver = new TemplateIncludeResolver(self::FIXTURES . '/cleantheme');
        $result = $resolver->resolve('"$theme_name/tpl/header.tpl"');

        $this->assertSame(TemplateIncludeResolver::STATUS_THEME, $result['status']);
        $this->assertSame('tpl/header.tpl', $result['path']);
        $this->assertTrue($result['exists']);
    }

    public function testCustomVariableAssignedFromFolderNameIsLearnt(): void
    {
        $resolver = new TemplateIncludeResolver(self::FIXTURES . '/smartytheme');

        $this->assertContains('tname', $resolver->themeVariables());
        $this->assertSame('tpl/header.tpl', $resolver->resolve('"$tname/tpl/header.tpl"')['path']);
        $this->assertSame('tpl/header.tpl', $resolver->resolve('"{$tname}/tpl/header.tpl"')['path']);
    }

    public function testFolderNamePatternsAreResolved(): void
    {
        $resolver = new TemplateIncludeResolver(self::FIXTURES . '/cleantheme');

        $arguments = [
            '"`$xoTheme->folderName`/tpl/header.tpl"',
            '"$xoTheme->folderName/tpl/header.tpl"',
            "\$xoTheme->folderName|cat:'/tpl/header.tpl'",
            "\$theme_name|cat:'/tpl/'|cat:'header.tpl'",
        ];
        foreach ($arguments as $argument) {
            $result = $resolver->resolve($argument);
            $this->assertSame('tpl/header.tpl', $result['path'], $argument);
            $this->assertTrue($result['exists'], $argument);
        }
    }

    public function testPlainRelativeNamesResolveInsideTheTheme(): void
    {
        $resolver = new TemplateIncludeResolver(self::FIXTURES . '/cleantheme');

        $this->assertTrue($resolver->resolve('"tpl/header.tpl"')['exists']);
        $this->assertTrue($resolver->resolve('"header.tpl"', 'tpl/other.tpl')['exists']);
        $this->assertSame('cleantheme/tpl/header.tpl', $resolver->themeName() . '/' . $resolver->resolve("'cleantheme/tpl/header.tpl'")['path']);
    }

    public function testModuleAndDynamicIncludesAreNeverMissing(): void
    {
        $resolver = new TemplateIncludeResolver(self::FIXTURES . '/cleantheme');

        $this->assertSame(TemplateIncludeResolver::STATUS_MODULE, $resolver->resolve("'db:system_block_user.tpl'")['status']);
        $this->assertSame(TemplateIncludeResolver::STATUS_DYNAMIC, $resolver->resolve('"db:system_block_$block.template"')['status']);
        $this->assertSame(TemplateIncludeResolver::STATUS_DYNAMIC, $resolver->resolve('$block.template')['status']);
        $this->assertSame(TemplateIncludeResolver::STATUS_EXTERNAL, $resolver->resolve('"file:/var/www/x.tpl"')['status']);
    }

    public function testIncludesInReportsLineAndStatus(): void
    {
        $resolver = new TemplateIncludeResolver(self::FIXTURES . '/smartytheme');
        $source = (string)file_get_contents(self::FIXTURES . '/smartytheme/theme.tpl');
        $includes = $resolver->includesIn($source, 'theme.tpl');

        $this->assertCount(3, $includes);
        $this->assertSame([8, true], [$includes[0]['line'], $includes[0]['exists']]);
        $this->assertSame([9, false, 'tpl/missing.tpl'], [$includes[1]['line'], $includes[1]['exists'], $includes[1]['path']]);
        $this->assertSame(TemplateIncludeResolver::STATUS_MODULE, $includes[2]['status']);
    }

    public function testExpandedPageInlinesThePartials(): void
    {
        $resolver = new TemplateIncludeResolver(self::FIXTURES . '/cleantheme');
        $page = $resolver->expandedPage();

        $this->assertStringContainsString('<header class="site-header">', $page);
        $this->assertStringNotContainsString('<{include', $page);
        $this->assertStringNotContainsString('<{*', $page);
    }
}
