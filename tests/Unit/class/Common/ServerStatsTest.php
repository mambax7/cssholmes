<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Common;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Common\ServerStats;

/**
 * Class ServerStatsTest.
 *
 * @copyright XOOPS Project (https://xoops.org)
 * @license GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author mamba <mambax7@gmail.com>
 *
 * @covers \XoopsModules\Cssholmes\Common\ServerStats
 */
final class ServerStatsTest extends TestCase
{
    private ServerStats $serverStats;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @todo Correctly instantiate tested object to use it. */
        $this->serverStats = $this->getMockBuilder(ServerStats::class)
            ->setConstructorArgs([])
            ->getMockForTrait();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->serverStats);
    }

    public function testGetServerStats(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }
}
