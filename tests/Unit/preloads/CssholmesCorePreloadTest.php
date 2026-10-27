<?php

namespace Tests\Unit;

use CssholmesCorePreload;
use PHPUnit\Framework\TestCase;

/**
 * Class CssholmesCorePreloadTest.
 *
 * @copyright XOOPS Project (https://xoops.org)
 * @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author kris <https://www.xoofoo.org>
 *
 * @covers \CssholmesCorePreload
 */
final class CssholmesCorePreloadTest extends TestCase
{
    private CssholmesCorePreload $cssholmesCorePreload;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @todo Correctly instantiate tested object to use it. */
        $this->cssholmesCorePreload = new CssholmesCorePreload();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->cssholmesCorePreload);
    }

    public function testEventCoreIncludeCommonEnd(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testEventCoreHeaderAddmeta(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }
}
