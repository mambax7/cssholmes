<?php

namespace Tests\Unit\XoopsModules\Cssholmes\Cli;

use PHPUnit\Framework\TestCase;
use XoopsModules\Cssholmes\Cli\ScanCommand;

/**
 * @covers \XoopsModules\Cssholmes\Cli\ScanCommand
 */
final class ScanCommandTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/themes';

    /**
     * @param string[] $arguments
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(array $arguments): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        $this->assertIsResource($stdout);
        $this->assertIsResource($stderr);

        $exitCode = (new ScanCommand($stdout, $stderr))->run($arguments);
        rewind($stdout);
        rewind($stderr);

        return [$exitCode, (string)stream_get_contents($stdout), (string)stream_get_contents($stderr)];
    }

    public function testThemeWithOnlyWarningsPassesByDefault(): void
    {
        [$exitCode, $output] = $this->runCommand([self::FIXTURES . '/cleantheme']);

        $this->assertSame(ScanCommand::EXIT_PASS, $exitCode);
        $this->assertStringContainsString('Result: PASS (0 errors, 1 warning,', $output);
    }

    public function testFailOnWarningFailsTheSameTheme(): void
    {
        [$exitCode, $output] = $this->runCommand(['--fail-on=warning', self::FIXTURES . '/cleantheme']);

        $this->assertSame(ScanCommand::EXIT_FINDINGS, $exitCode);
        $this->assertStringContainsString('Result: FAIL', $output);
    }

    public function testErrorsFailAndJsonIsValid(): void
    {
        [$exitCode, $output] = $this->runCommand(['--format=json', self::FIXTURES . '/smartytheme']);
        $report = json_decode($output, true);

        $this->assertSame(ScanCommand::EXIT_FINDINGS, $exitCode);
        $this->assertIsArray($report);
        $this->assertSame('fail', $report['result']);
        $this->assertSame('generic', $report['mode']);
        $this->assertSame(4, $report['counts']['error']);
        $this->assertContains('tpl/include-missing', array_column($report['findings'], 'rule_id'));
    }

    public function testFailOnNeverAlwaysPasses(): void
    {
        [$exitCode] = $this->runCommand(['--fail-on=never', self::FIXTURES . '/smartytheme']);

        $this->assertSame(ScanCommand::EXIT_PASS, $exitCode);
    }

    public function testAnalyzerSubset(): void
    {
        [$exitCode, $output] = $this->runCommand(['--analyzers=design-token', '--format=json', self::FIXTURES . '/smartytheme']);
        $report = json_decode($output, true);

        $this->assertSame(ScanCommand::EXIT_PASS, $exitCode);
        $this->assertSame(['design-token'], array_values(array_unique(array_column($report['findings'], 'analyzer'))));
    }

    public function testUsageErrors(): void
    {
        $this->assertSame(ScanCommand::EXIT_USAGE, $this->runCommand([])[0]);
        $this->assertSame(ScanCommand::EXIT_USAGE, $this->runCommand(['--fail-on=sometimes', self::FIXTURES . '/cleantheme'])[0]);
        $this->assertSame(ScanCommand::EXIT_USAGE, $this->runCommand([self::FIXTURES . '/does-not-exist'])[0]);
        $this->assertSame(ScanCommand::EXIT_USAGE, $this->runCommand([__DIR__])[0]);

        [$exitCode, $output] = $this->runCommand(['--help']);
        $this->assertSame(ScanCommand::EXIT_PASS, $exitCode);
        $this->assertStringContainsString('--fail-on', $output);
    }

    public function testBinScriptExitCode(): void
    {
        $script = dirname(__DIR__, 4) . '/bin/holmes-scan.php';
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(self::FIXTURES . '/smartytheme') . ' 2>&1', $output, $exitCode);
        $this->assertSame(1, $exitCode);

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(self::FIXTURES . '/xtftheme') . ' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode);
    }
}
