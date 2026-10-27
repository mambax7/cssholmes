<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Common;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Common\SysUtility;

/**
 * Class SysUtilityTest.
 *
 * @covers \XoopsModules\Cssholmes\Common\SysUtility
 */
final class SysUtilityTest extends TestCase
{
    private SysUtility $sysUtility;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @todo Correctly instantiate tested object to use it. */
        $this->sysUtility = new SysUtility();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->sysUtility);
    }

    public function testTruncateHtml(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testGetEditor(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testFieldExists(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testCloneRecord(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testTableExists(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testQueryAndCheck(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }

    public function testQueryFAndCheck(): void
    {
        /** @todo This test is incomplete. */
        $this->markTestIncomplete();
    }
}
