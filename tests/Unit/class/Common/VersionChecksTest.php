<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Common;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Common\VersionChecks;

/**
 * Class VersionChecksTest.
 *
 * @copyright XOOPS Project (https://xoops.org)
 * @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author mamba <mambax7@gmail.com>
 *
 * @covers \XoopsModules\Cssholmes\Common\VersionChecks
 */
final class VersionChecksTest extends TestCase
{
    private VersionChecks $versionChecks;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @todo Correctly instantiate tested object to use it. */
        $this->versionChecks = $this->getMockBuilder(VersionChecks::class)
            ->setConstructorArgs([])
            ->getMockForTrait();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->versionChecks);
    }

    public function testCheckVerXoops(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testCheckVerPhp(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testCheckVerModule(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }
}
