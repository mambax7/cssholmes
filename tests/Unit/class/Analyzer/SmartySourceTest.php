<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Analyzer;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Analyzer\SmartySource;

/**
 * @covers \XoopsModules\Cssholmes\Analyzer\SmartySource
 */
final class SmartySourceTest extends TestCase
{
    public function testSmartyInsideAttributesNoLongerEndsTheHtmlTag(): void
    {
        $html = SmartySource::htmlView('<img src="<{$block.image}>" alt="<{$block.title}>">');

        $this->assertSame('<img src="SMARTY" alt="SMARTY">', $html);
        $this->assertSame(1, preg_match('/<img\b([^>]*)>/', $html, $match));
        $this->assertStringContainsString('alt=', $match[1]);
    }

    public function testCommentsAreRemovedAndLineBreaksKept(): void
    {
        $source = "a<{* one\n two <img src=\"x\"> *}>b\nc";

        $this->assertSame("a\nb\nc", SmartySource::stripComments($source));
        $this->assertSame("a\nb\nc", SmartySource::neutralise($source));
    }

    public function testControlTagsBecomeSpacesAndOutputTagsPlaceholders(): void
    {
        $source = '<a href="#"<{if $x}> class="on"<{/if}>><{$smarty.const.LABEL}></a>';

        $this->assertSame('<a href="#"  class="on" >SMARTY</a>', SmartySource::neutralise($source));
    }

    public function testQuotedStringsMayContainTheClosingDelimiter(): void
    {
        $source = '<p title="<{$x|default:"a}>b"}>">t</p>';

        $this->assertSame('<p title="SMARTY">t</p>', SmartySource::neutralise($source));
    }

    public function testLiteralBlocksKeepTheirContent(): void
    {
        $source = '<{literal}><style>a{color:red}</style><{/literal}><{$x}>';

        $this->assertSame('<style>a{color:red}</style>SMARTY', SmartySource::neutralise($source));
    }

    public function testHtmlViewBlanksScriptsStylesAndComments(): void
    {
        $source = "<script>\nif (a<b) { x = '<a></a>'; }\n</script><!-- <img> --><style>p{}</style>";

        $this->assertSame("<script>\n\n</script><style></style>", SmartySource::htmlView($source));
    }

    public function testDynamicAttributesAreDetectedOnlyOutsideQuotes(): void
    {
        $this->assertTrue(SmartySource::hasDynamicAttributes(' src="x" SMARTY'));
        $this->assertFalse(SmartySource::hasDynamicAttributes(' src="SMARTY" alt="SMARTY"'));
    }

    public function testLineNumbersSurviveNeutralising(): void
    {
        $source = "<{if \$a\n  && \$b}>\n<img src=\"x\">";
        $view = SmartySource::htmlView($source);
        $offset = (int)strpos($view, '<img');

        $this->assertSame(3, SmartySource::lineAt($view, $offset));
    }
}
