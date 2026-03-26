<?php declare(strict_types=1);

namespace XoopsModules\Cssholmes\Analyzer;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
/**
 *
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      XOOPS Development Team, Mamba <mambax7@gmail.com>
 */

final readonly class AnalysisRunner
{
    /** @param AnalyzerInterface[] $analyzers */
    public function __construct(private array $analyzers)
    {
    }

    /** @return Finding[] */
    public function analyze(AnalysisContext $context): array
    {
        $findings = [];
        foreach ($this->analyzers as $analyzer) {
            foreach ($analyzer->analyze($context) as $finding) {
                $findings[] = $finding;
            }
        }

        usort(
            $findings,
            static function (Finding $left, Finding $right): int {
                $severityComparison = $left->severity->weight() <=> $right->severity->weight();
                if (0 !== $severityComparison) {
                    return $severityComparison;
                }

                $ruleComparison = strcmp($left->ruleId, $right->ruleId);
                if (0 !== $ruleComparison) {
                    return $ruleComparison;
                }

                return strcmp($left->target, $right->target);
            }
        );

        return $findings;
    }
}
