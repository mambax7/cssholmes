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

final readonly class AnalysisContext
{
    public string $themePath;
    public ?string $themeId;
    public ?string $widgetName;

    public function __construct(string $themePath, ?string $themeId = null, ?string $widgetName = null)
    {
        $this->themePath = rtrim($themePath, '/\\');
        $this->themeId = $themeId;
        $this->widgetName = $widgetName;
    }
}
