<?php declare(strict_types=1);

namespace XoopsModules\Cssholmes\Diagnostics;

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

final class OverlayConfig
{
    /** @var array<string, string> */
    private const PROFILE_MAP = [
        'html5'      => 'assets/css/profiles/html5.css',
        'xtf-theme'  => 'assets/css/profiles/xtf-theme.css',
        'xtf-widget' => 'assets/css/profiles/xtf-widget.css',
        'a11y'       => 'assets/css/profiles/a11y.css',
        'layout'     => 'assets/css/profiles/layout.css',
    ];

    /** @param string[] $profiles */
    private function __construct(private readonly array $profiles)
    {
    }

    /** @return string[] */
    public static function allProfiles(): array
    {
        return array_keys(self::PROFILE_MAP);
    }

    public static function fromRequest(string $requestedProfiles): self
    {
        $requestedProfiles = trim($requestedProfiles);
        if ('' === $requestedProfiles) {
            return new self([]);
        }

        $normalized = strtolower($requestedProfiles);
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return new self([]);
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on', 'html'], true)) {
            return new self(['html5']);
        }

        if ('all' === $normalized) {
            return new self(array_keys(self::PROFILE_MAP));
        }

        $aliases = [
            'theme'         => 'xtf-theme',
            'widget'        => 'xtf-widget',
            'accessibility' => 'a11y',
        ];

        $profiles = [];
        foreach (preg_split('/[\s,]+/', $requestedProfiles) ?: [] as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ('' === $candidate) {
                continue;
            }

            $candidate = $aliases[$candidate] ?? $candidate;
            if (isset(self::PROFILE_MAP[$candidate])) {
                $profiles[$candidate] = $candidate;
            }
        }

        return new self(array_values($profiles));
    }

    public function enabled(): bool
    {
        return [] !== $this->profiles;
    }

    /** @return string[] */
    public function profiles(): array
    {
        return $this->profiles;
    }

    /** @return string[] */
    public function profileStylesheetPaths(): array
    {
        $paths = [];
        foreach ($this->profiles as $profile) {
            if (isset(self::PROFILE_MAP[$profile])) {
                $paths[] = self::PROFILE_MAP[$profile];
            }
        }

        return $paths;
    }
}
