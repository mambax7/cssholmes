<?php declare(strict_types=1);

/**
 * Cssholmes module
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright           2000-2026 XOOPS Project (https://xoops.org)
 * @license            GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since               2.3.0
 * @author              kris <https://www.xoofoo.org>
 **/

use Xmf\Module\Admin;
use Xmf\Request;
use XoopsModules\Cssholmes\Analyzer\AccessibilityAnalyzer;
use XoopsModules\Cssholmes\Analyzer\AnalysisContext;
use XoopsModules\Cssholmes\Analyzer\DesignTokenAnalyzer;
use XoopsModules\Cssholmes\Analyzer\Severity;
use XoopsModules\Cssholmes\Analyzer\TemplateAnalyzer;
use XoopsModules\Cssholmes\Analyzer\ThemeCatalog;
use XoopsModules\Cssholmes\Analyzer\ThemeManifestAnalyzer;
use XoopsModules\Cssholmes\Analyzer\WidgetOutputAnalyzer;
use XoopsModules\Cssholmes\Workbench\PatchDraftBuilder;
use XoopsModules\Cssholmes\Workbench\PatchApplier;
use XoopsModules\Cssholmes\Workbench\ImportStore;
use XoopsModules\Cssholmes\Workbench\PatchSuggester;

require_once __DIR__ . '/admin_header.php';
xoops_cp_header();

$adminObject = Admin::getInstance();
$adminObject->displayNavigation(basename(__FILE__));
$adminObject->displayIndex();

$queryKey = (string)$helper->getConfig('holmes_query_key');
if ('' === trim($queryKey)) {
    $queryKey = 'holmes';
}

$themeCatalog = new ThemeCatalog();
$patchApplier = new PatchApplier();
$patchDraftBuilder = new PatchDraftBuilder();
$patchSuggester = new PatchSuggester();
$importStore = new ImportStore(\dirname(__DIR__));
$importErrors = [];
$importPayload = '';
$importSummary = null;
$importSuggestions = [];
$importDrafts = [];
$workbenchNotice = '';
$widgetScanName = '';
$widgetScanHtml = '';
$widgetFindings = [];
$widgetFindingCounts = [
    Severity::Error->value => 0,
    Severity::Warning->value => 0,
    Severity::Info->value => 0,
];
$selectedHistoryId = trim(Request::getString('history_import', '', 'GET'));
$historyStatusFilter = strtolower(trim(Request::getString('history_status', 'all', 'GET')));
if (!in_array($historyStatusFilter, ['all', 'pending', 'accepted', 'rejected'], true)) {
    $historyStatusFilter = 'all';
}
$normalizeReviewChanges = static function (array $changes): array {
    $normalizedChanges = [];
    foreach ($changes as $change) {
        if (!is_array($change)) {
            continue;
        }

        $normalizedChanges[] = [
            'kind' => is_string($change['kind'] ?? null) ? trim((string)$change['kind']) : 'unknown',
            'selector' => is_string($change['selector'] ?? null) ? trim((string)$change['selector']) : '',
            'widget' => is_string($change['widget'] ?? null) ? trim((string)$change['widget']) : '',
            'slot' => is_string($change['slot'] ?? null) ? trim((string)$change['slot']) : '',
            'before' => is_string($change['before'] ?? null) ? (string)$change['before'] : '',
            'after' => is_string($change['after'] ?? null) ? (string)$change['after'] : '',
            'summary' => is_string($change['summary'] ?? null) ? trim((string)$change['summary']) : '',
            'style_property' => is_string($change['style_property'] ?? null) ? trim((string)$change['style_property']) : '',
            'token_key' => is_string($change['token_key'] ?? null) ? trim((string)$change['token_key']) : '',
            'token_properties' => is_array($change['token_properties'] ?? null) ? array_values(array_filter($change['token_properties'], 'is_string')) : [],
            'inspection' => is_array($change['inspection'] ?? null) ? $change['inspection'] : [],
            'status' => 'pending',
        ];
    }

    return $normalizedChanges;
};
$buildKindCounts = static function (array $changes): array {
    $kindCounts = [];
    foreach ($changes as $change) {
        $kind = is_string($change['kind'] ?? null) ? trim((string)$change['kind']) : 'unknown';
        $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
    }

    return $kindCounts;
};
$detectThemeScope = static function (ThemeCatalog $catalog, string $themeId): string {
    foreach (['site', 'admin'] as $scope) {
        if (null !== $catalog->find($scope, $themeId)) {
            return $scope;
        }
    }

    return '';
};
$buildThemeConfigSummary = static function (array $decoded, ThemeCatalog $catalog) use ($detectThemeScope): array {
    $placements = is_array($decoded['placements'] ?? null) ? $decoded['placements'] : [];
    $preferences = is_array($decoded['preferences'] ?? null) ? $decoded['preferences'] : [];
    $tokenOverrides = is_array($decoded['token_overrides'] ?? null) ? $decoded['token_overrides'] : [];
    $slotCounts = [];
    $placementCount = 0;
    foreach ($placements as $slotName => $slotPlacements) {
        if (!is_string($slotName) || !is_array($slotPlacements)) {
            continue;
        }

        $validPlacements = array_values(array_filter($slotPlacements, 'is_array'));
        $slotCounts[$slotName] = count($validPlacements);
        $placementCount += count($validPlacements);
    }

    $themeId = is_string($decoded['theme_id'] ?? null) ? trim((string)$decoded['theme_id']) : '';

    return [
        'payload_type' => 'theme_config_export',
        'payload_label' => 'XTF theme config export',
        'scope' => $detectThemeScope($catalog, $themeId),
        'theme' => $themeId,
        'generated_at' => is_string($decoded['exported_at'] ?? null) ? trim((string)$decoded['exported_at']) : '',
        'changes' => [],
        'kind_counts' => [],
        'config_summary' => [
            'theme_id' => $themeId,
            'theme_name' => is_string($decoded['name'] ?? null) ? trim((string)$decoded['name']) : '',
            'version' => is_string($decoded['version'] ?? null) ? trim((string)$decoded['version']) : '',
            'schema_version' => is_scalar($decoded['schema_version'] ?? null) ? (string)$decoded['schema_version'] : '',
            'placement_count' => $placementCount,
            'slot_counts' => $slotCounts,
            'preference_keys' => array_values(array_filter(array_map('strval', array_keys($preferences)), static fn (string $key): bool => '' !== trim($key))),
            'token_override_count' => count(array_values(array_filter($tokenOverrides, 'is_array'))),
        ],
    ];
};
if ('POST' === strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'))) {
    $operation = Request::getString('op', '', 'POST');
    if (!$GLOBALS['xoopsSecurity']->check()) {
        $importErrors[] = implode(' ', $GLOBALS['xoopsSecurity']->getErrors());
    } elseif ('import_export' === $operation) {
        $importPayload = trim(Request::getText('export_payload', '', 'POST'));
        if ('' === $importPayload) {
            $importErrors[] = 'Export payload is empty.';
        } else {
            try {
                $decoded = json_decode($importPayload, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('Payload must decode to an object.');
                }

                if (is_array($decoded['changes'] ?? null)) {
                    $normalizedChanges = $normalizeReviewChanges($decoded['changes']);
                    $importSummary = [
                        'payload_type' => 'review_export',
                        'payload_label' => 'cssHolmes review export',
                        'scope' => is_string($decoded['scope'] ?? null) ? trim((string)$decoded['scope']) : '',
                        'theme' => is_string($decoded['theme'] ?? null) ? trim((string)$decoded['theme']) : '',
                        'generated_at' => is_string($decoded['generated_at'] ?? null) ? trim((string)$decoded['generated_at']) : '',
                        'changes' => $normalizedChanges,
                        'kind_counts' => $buildKindCounts($normalizedChanges),
                        'config_summary' => [],
                    ];
                } elseif (isset($decoded['schema_version'], $decoded['theme_id']) && is_array($decoded['placements'] ?? null)) {
                    $importSummary = $buildThemeConfigSummary($decoded, $themeCatalog);
                } else {
                    throw new \RuntimeException('Unsupported payload. Use a cssHolmes review export or an XTF theme config export.');
                }

                $savedImport = $importStore->saveImport($importSummary, $importPayload);
                $selectedHistoryId = (string)($savedImport['id'] ?? '');
                if ('theme_config_export' === (string)($importSummary['payload_type'] ?? '')) {
                    $workbenchNotice = 'Theme config export stored in the cssHolmes workbench history.';
                    $importSuggestions = [];
                    $importDrafts = [];
                } else {
                    $workbenchNotice = 'Import stored in the cssHolmes workbench history.';
                    $importSuggestions = $patchSuggester->suggest(
                        $themeCatalog->find((string)$importSummary['scope'], (string)$importSummary['theme']),
                        is_array($importSummary['changes'] ?? null) ? $importSummary['changes'] : []
                    );
                    $importDrafts = $patchDraftBuilder->build(
                        $themeCatalog->find((string)$importSummary['scope'], (string)$importSummary['theme']),
                        is_array($importSummary['changes'] ?? null) ? $importSummary['changes'] : [],
                        $importSuggestions
                    );
                }
            } catch (\Throwable $throwable) {
                $importErrors[] = 'Invalid export payload: ' . $throwable->getMessage();
            }
        }
    } elseif ('change_status' === $operation) {
        $selectedHistoryId = Request::getString('import_id', '', 'POST');
        $updated = $importStore->updateChangeStatus(
            $selectedHistoryId,
            (int)Request::getString('change_index', '0', 'POST'),
            Request::getString('status', 'pending', 'POST')
        );
        $workbenchNotice = $updated ? 'Change status updated.' : 'Unable to update change status.';
    } elseif ('apply_change' === $operation) {
        $selectedHistoryId = Request::getString('import_id', '', 'POST');
        $changeIndex = (int)Request::getString('change_index', '0', 'POST');
        $confirmedApply = '1' === Request::getString('confirm_apply', '0', 'POST');
        $selectedImport = $importStore->find($selectedHistoryId);

        if (!$confirmedApply) {
            $importErrors[] = 'Apply confirmation is required before writing files.';
        } elseif (null === $selectedImport) {
            $importErrors[] = 'Unable to find the selected import.';
        } else {
            $selectedChanges = is_array($selectedImport['changes'] ?? null) ? $selectedImport['changes'] : [];
            $selectedChange = is_array($selectedChanges[$changeIndex] ?? null) ? $selectedChanges[$changeIndex] : null;

            if (null === $selectedChange) {
                $importErrors[] = 'Unable to find the selected change.';
            } else {
                $status = is_string($selectedChange['status'] ?? null) ? strtolower(trim((string)$selectedChange['status'])) : 'pending';
                if ('accepted' !== $status) {
                    $importErrors[] = 'Only accepted changes can be applied directly.';
                } else {
                    $theme = $themeCatalog->find(
                        is_string($selectedImport['scope'] ?? null) ? (string)$selectedImport['scope'] : '',
                        is_string($selectedImport['theme'] ?? null) ? (string)$selectedImport['theme'] : ''
                    );
                    $suggestions = $patchSuggester->suggest($theme, [$selectedChange]);
                    $result = $patchApplier->apply($theme, $selectedChange, is_array($suggestions[0] ?? null) ? $suggestions[0] : []);

                    if ($result['success']) {
                        $importStore->markChangeApplied(
                            $selectedHistoryId,
                            $changeIndex,
                            (string)$result['target'],
                            (string)$result['message'],
                            is_array($result['rollback'] ?? null) ? $result['rollback'] : []
                        );
                        $workbenchNotice = (string)$result['message'];
                    } else {
                        $importErrors[] = (string)$result['message'];
                    }
                }
            }
        }
    } elseif ('rollback_change' === $operation) {
        $selectedHistoryId = Request::getString('import_id', '', 'POST');
        $changeIndex = (int)Request::getString('change_index', '0', 'POST');
        $confirmedRollback = '1' === Request::getString('confirm_rollback', '0', 'POST');
        $selectedImport = $importStore->find($selectedHistoryId);

        if (!$confirmedRollback) {
            $importErrors[] = 'Rollback confirmation is required before restoring a previous file state.';
        } elseif (null === $selectedImport) {
            $importErrors[] = 'Unable to find the selected import.';
        } else {
            $selectedChanges = is_array($selectedImport['changes'] ?? null) ? $selectedImport['changes'] : [];
            $selectedChange = is_array($selectedChanges[$changeIndex] ?? null) ? $selectedChanges[$changeIndex] : null;

            if (null === $selectedChange) {
                $importErrors[] = 'Unable to find the selected change.';
            } else {
                $appliedMeta = is_array($selectedChange['applied_meta'] ?? null) ? $selectedChange['applied_meta'] : [];
                if ([] === $appliedMeta) {
                    $importErrors[] = 'Rollback metadata is missing for this change.';
                } else {
                    $theme = $themeCatalog->find(
                        is_string($selectedImport['scope'] ?? null) ? (string)$selectedImport['scope'] : '',
                        is_string($selectedImport['theme'] ?? null) ? (string)$selectedImport['theme'] : ''
                    );
                    $result = $patchApplier->rollback($theme, $selectedChange, $appliedMeta);

                    if ($result['success']) {
                        $importStore->markChangeRolledBack($selectedHistoryId, $changeIndex, (string)$result['message']);
                        $workbenchNotice = (string)$result['message'];
                    } else {
                        $importErrors[] = (string)$result['message'];
                    }
                }
            }
        }
    } elseif ('scan_widget_html' === $operation) {
        $widgetScanName = trim(Request::getString('widget_name', 'sample-widget', 'POST'));
        $widgetScanHtml = trim(Request::getText('widget_html', '', 'POST'));

        if ('' === $widgetScanName) {
            $widgetScanName = 'sample-widget';
        }

        if ('' === $widgetScanHtml) {
            $importErrors[] = 'Widget HTML is empty.';
        } else {
            $widgetAnalyzer = new WidgetOutputAnalyzer();
            $widgetFindings = $widgetAnalyzer->analyze(new AnalysisContext($widgetScanHtml, null, $widgetScanName));
            foreach ($widgetFindings as $widgetFinding) {
                $widgetFindingCounts[$widgetFinding->severity->value]++;
            }
        }
    }
}

$savedImports = $importStore->all();
$statusCountsForChanges = static function (array $changes): array {
    $counts = [
        'pending' => 0,
        'accepted' => 0,
        'rejected' => 0,
    ];

    foreach ($changes as $change) {
        if (!is_array($change)) {
            continue;
        }

        $status = is_string($change['status'] ?? null) ? strtolower(trim((string)$change['status'])) : 'pending';
        if (!isset($counts[$status])) {
            $status = 'pending';
        }

        $counts[$status]++;
    }

    return $counts;
};
$inspectionSummaryForChange = static function (array $change): string {
    $summary = is_string($change['summary'] ?? null) ? trim((string)$change['summary']) : '';
    $inspection = is_array($change['inspection'] ?? null) ? $change['inspection'] : [];
    $details = [];

    foreach (['size' => 'Size', 'position' => 'Pos', 'margin' => 'Margin', 'padding' => 'Padding', 'font' => 'Font', 'color' => 'Color'] as $key => $label) {
        if (is_string($inspection[$key] ?? null) && '' !== trim((string)$inspection[$key])) {
            $details[] = $label . ': ' . trim((string)$inspection[$key]);
        }
    }
    if (is_array($inspection['tokens'] ?? null) && [] !== $inspection['tokens']) {
        $details[] = 'Tokens: ' . implode(', ', array_map('strval', $inspection['tokens']));
    }

    $detailText = implode(' | ', $details);
    if ('' !== $summary && '' !== $detailText) {
        return $summary . "\n" . $detailText;
    }

    return '' !== $summary ? $summary : $detailText;
};
$historyTotals = [
    'imports' => count($savedImports),
    'changes' => 0,
    'pending' => 0,
    'accepted' => 0,
    'rejected' => 0,
];
foreach ($savedImports as $savedImport) {
    $savedChanges = is_array($savedImport['changes'] ?? null) ? $savedImport['changes'] : [];
    $historyTotals['changes'] += count($savedChanges);
    $statusCounts = $statusCountsForChanges($savedChanges);
    $historyTotals['pending'] += $statusCounts['pending'];
    $historyTotals['accepted'] += $statusCounts['accepted'];
    $historyTotals['rejected'] += $statusCounts['rejected'];
}

$selectedHistoryImport = '' !== $selectedHistoryId ? $importStore->find($selectedHistoryId) : null;
if (null === $selectedHistoryImport && [] !== $savedImports) {
    $selectedHistoryImport = $savedImports[0];
    $selectedHistoryId = (string)($selectedHistoryImport['id'] ?? '');
}

$selectedHistoryChanges = is_array($selectedHistoryImport['changes'] ?? null) ? $selectedHistoryImport['changes'] : [];
$selectedHistoryCounts = $statusCountsForChanges($selectedHistoryChanges);
$selectedHistoryTheme = null !== $selectedHistoryImport
    ? $themeCatalog->find(
        is_string($selectedHistoryImport['scope'] ?? null) ? (string)$selectedHistoryImport['scope'] : '',
        is_string($selectedHistoryImport['theme'] ?? null) ? (string)$selectedHistoryImport['theme'] : ''
    )
    : null;
$selectedHistorySuggestions = null !== $selectedHistoryImport
    ? $patchSuggester->suggest(
        $selectedHistoryTheme,
        $selectedHistoryChanges
    )
    : [];
$selectedHistoryDrafts = null !== $selectedHistoryImport
    ? $patchDraftBuilder->build(
        $selectedHistoryTheme,
        $selectedHistoryChanges,
        $selectedHistorySuggestions
    )
    : [];
$filteredHistoryChanges = array_values(array_filter(
    $selectedHistoryChanges,
    static function ($change) use ($historyStatusFilter): bool {
        if ('all' === $historyStatusFilter) {
            return true;
        }

        if (!is_array($change)) {
            return false;
        }

        $status = is_string($change['status'] ?? null) ? strtolower(trim((string)$change['status'])) : 'pending';

        return $status === $historyStatusFilter;
    }
));

$scanScope = strtolower(trim(Request::getString('scan_scope', 'admin', 'GET')));
if (!in_array($scanScope, ['admin', 'site'], true)) {
    $scanScope = 'admin';
}

$selectedThemeKey = trim(Request::getString('scan_theme', '', 'GET'));
$scopeThemes = $themeCatalog->allForScope($scanScope);

$currentThemeGuess = '';
if ('admin' === $scanScope && isset($GLOBALS['xoTheme']) && is_object($GLOBALS['xoTheme']) && isset($GLOBALS['xoTheme']->folderName)) {
    $currentThemeGuess = trim((string)$GLOBALS['xoTheme']->folderName);
}
if ('site' === $scanScope && isset($GLOBALS['xoopsConfig']['theme_set']) && is_string($GLOBALS['xoopsConfig']['theme_set'])) {
    $currentThemeGuess = trim($GLOBALS['xoopsConfig']['theme_set']);
}

if ('' === $selectedThemeKey) {
    $selectedThemeKey = $currentThemeGuess;
}

$selectedTheme = $themeCatalog->find($scanScope, $selectedThemeKey);
if (null === $selectedTheme && [] !== $scopeThemes) {
    $selectedTheme = $scopeThemes[0];
}

$findings = [];
$analyzerResults = [];
$findingCounts = [
    Severity::Error->value => 0,
    Severity::Warning->value => 0,
    Severity::Info->value => 0,
];

if (null !== $selectedTheme) {
    $analyzers = [
        new ThemeManifestAnalyzer(),
        new DesignTokenAnalyzer(),
        new TemplateAnalyzer(),
        new AccessibilityAnalyzer(),
    ];
    $context = new AnalysisContext($selectedTheme->path, $selectedTheme->key);

    foreach ($analyzers as $analyzer) {
        $result = $analyzer->analyze($context);
        $analyzerResults[$analyzer->label()] = $result;
        foreach ($result as $finding) {
            $findings[] = $finding;
        }
    }

    foreach ($findings as $finding) {
        $findingCounts[$finding->severity->value]++;
    }
}

$adminOverlayUrl = XOOPS_URL . '/modules/' . $moduleDirName . '/admin/index.php?' . $queryKey . '=xtf-theme,xtf-widget';
$siteOverlayUrl = XOOPS_URL . '/?' . $queryKey . '=xtf-theme,xtf-widget';

$examples = [
    XOOPS_URL . '/?' . $queryKey . '=html5',
    $siteOverlayUrl,
    $adminOverlayUrl,
    XOOPS_URL . '/?' . $queryKey . '=all',
];

echo '<div class="card" style="margin-top:1rem;padding:1rem 1.25rem;">';
echo '<h3 style="margin-top:0;">cssHolmes Overlay Activation</h3>';
echo '<p>Overlay diagnostics are opt-in for administrators on both the site and admin theme side. You can activate them from the query string, then keep working with the floating toolbar without re-adding the parameter on every page.</p>';
echo '<p><code>' . htmlspecialchars($queryKey, ENT_QUOTES) . '=html5,xtf-theme,xtf-widget,a11y,layout</code></p>';
echo '<ul>';
foreach ($examples as $exampleUrl) {
    $safeUrl = htmlspecialchars($exampleUrl, ENT_QUOTES);
    echo '<li><a href="' . $safeUrl . '">' . $safeUrl . '</a></li>';
}
echo '</ul>';
echo '<p>The toolbar now persists by scope in the browser, so site and admin overlays can keep different profile sets while still supporting shareable preview URLs.</p>';
echo '<p>The first VisBug-style tools are also live in the toolbar: <strong>inspect</strong>, <strong>measure</strong>, element locking on click, selector copy, keyboard shortcuts <code>I</code>, <code>M</code>, <code>T</code>, <code>P</code>, <code>U</code>, and <code>Esc</code>, plus token-aware color hints when the current XTF theme exposes matching values in <code>theme.json</code>. The toolbar can now preview local token edits on the selected element, adjust core typography properties, jump to the nearest widget or slot wrapper, make quick text edits on simple leaf text elements, copy readable or JSON inspection snapshots, keep a small undoable local change log, and <strong>export sel</strong> as a structured workbench payload with widget and slot context.</p>';
echo '<p>If no XTF manifest is detected, cssHolmes falls back to a generic XOOPS mode. In that mode the HTML5, accessibility, layout, inspection, measurement, copy, and widget HTML scan tools still work, while token-aware theme analysis and token editing stay disabled.</p>';
echo '</div>';

echo '<div class="card" style="margin-top:1rem;padding:1rem 1.25rem;">';
echo '<h3 style="margin-top:0;">Export Import</h3>';
echo '<p>Paste either a cssHolmes toolbar review export or an XTF Marketplace theme config export. cssHolmes will detect the payload type and route it to the right review flow.</p>';
foreach ($importErrors as $importError) {
    echo '<div class="errorMsg" style="margin:0 0 1rem 0;">' . htmlspecialchars($importError, ENT_QUOTES) . '</div>';
}
if ('' !== $workbenchNotice && [] === $importErrors) {
    echo '<div class="confirmMsg" style="margin:0 0 1rem 0;">' . htmlspecialchars($workbenchNotice, ENT_QUOTES) . '</div>';
}
if (is_array($importSummary)) {
    if ('theme_config_export' === (string)($importSummary['payload_type'] ?? '')) {
        $configSummary = is_array($importSummary['config_summary'] ?? null) ? $importSummary['config_summary'] : [];
        echo '<div class="confirmMsg" style="margin:0 0 1rem 0;">Detected an XTF theme config export for '
            . htmlspecialchars((string)($configSummary['theme_name'] ?? $importSummary['theme']), ENT_QUOTES)
            . '.</div>';
    } else {
        echo '<div class="confirmMsg" style="margin:0 0 1rem 0;">Imported '
            . (int)count($importSummary['changes'])
            . ' change(s) for '
            . htmlspecialchars((string)($importSummary['theme'] !== '' ? $importSummary['theme'] : $importSummary['scope']), ENT_QUOTES)
            . '.</div>';
    }
}
echo '<form method="post" action="">';
echo $GLOBALS['xoopsSecurity']->getTokenHTML();
echo '<input type="hidden" name="op" value="import_export">';
echo '<textarea name="export_payload" rows="12" style="width:100%;font-family:Consolas,Monaco,monospace;">'
    . htmlspecialchars($importPayload, ENT_QUOTES)
    . '</textarea>';
echo '<p style="margin-top:.75rem;"><button class="btn btn-primary" type="submit">Import Export JSON</button></p>';
echo '</form>';
if (is_array($importSummary)) {
    echo '<div style="margin-top:1rem;">';
    echo '<p><strong>Payload:</strong> ' . htmlspecialchars((string)($importSummary['payload_label'] ?? 'Export'), ENT_QUOTES) . '</p>';
    echo '<p><strong>Scope:</strong> ' . htmlspecialchars((string)$importSummary['scope'], ENT_QUOTES)
        . ' <strong style="margin-left:1rem;">Theme:</strong> ' . htmlspecialchars((string)$importSummary['theme'], ENT_QUOTES)
        . ' <strong style="margin-left:1rem;">Generated:</strong> ' . htmlspecialchars((string)$importSummary['generated_at'], ENT_QUOTES) . '</p>';
    if ('theme_config_export' === (string)($importSummary['payload_type'] ?? '')) {
        $configSummary = is_array($importSummary['config_summary'] ?? null) ? $importSummary['config_summary'] : [];
        $slotCounts = is_array($configSummary['slot_counts'] ?? null) ? $configSummary['slot_counts'] : [];
        $preferenceKeys = is_array($configSummary['preference_keys'] ?? null) ? $configSummary['preference_keys'] : [];
        echo '<p>This payload describes widget placements, preferences, and token overrides. It is not a cssHolmes review-change export, so there are no per-change rows to accept or apply.</p>';
        echo '<p><strong>Theme Name:</strong> ' . htmlspecialchars((string)($configSummary['theme_name'] ?? ''), ENT_QUOTES)
            . ' <strong style="margin-left:1rem;">Version:</strong> ' . htmlspecialchars((string)($configSummary['version'] ?? ''), ENT_QUOTES)
            . ' <strong style="margin-left:1rem;">Schema:</strong> ' . htmlspecialchars((string)($configSummary['schema_version'] ?? ''), ENT_QUOTES)
            . '</p>';
        echo '<p><strong>Placements:</strong> ' . (int)($configSummary['placement_count'] ?? 0)
            . ' <strong style="margin-left:1rem;">Token Overrides:</strong> ' . (int)($configSummary['token_override_count'] ?? 0)
            . '</p>';
        if ([] !== $slotCounts) {
            echo '<div style="overflow:auto;">';
            echo '<table class="outer" style="width:100%;border-collapse:collapse;">';
            echo '<thead><tr><th style="text-align:left;">Slot</th><th style="text-align:left;">Placements</th></tr></thead><tbody>';
            foreach ($slotCounts as $slotName => $slotCount) {
                echo '<tr><td>' . htmlspecialchars((string)$slotName, ENT_QUOTES) . '</td><td>' . (int)$slotCount . '</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }
        if ([] !== $preferenceKeys) {
            echo '<p><strong>Preferences:</strong> ' . htmlspecialchars(implode(', ', array_map('strval', $preferenceKeys)), ENT_QUOTES) . '</p>';
        }
    } elseif ([] !== $importSummary['kind_counts']) {
        echo '<p>';
        foreach ($importSummary['kind_counts'] as $kind => $count) {
            echo '<span style="display:inline-block;margin-right:1rem;"><strong>'
                . htmlspecialchars((string)$kind, ENT_QUOTES)
                . ':</strong> '
                . (int)$count
                . '</span>';
        }
        echo '</p>';
    }
    if ('theme_config_export' === (string)($importSummary['payload_type'] ?? '')) {
        // Theme config exports are summarized above.
    } elseif ([] === $importSummary['changes']) {
        echo '<p>No changes were present in the payload.</p>';
    } else {
        echo '<div style="overflow:auto;">';
        echo '<table class="outer" style="width:100%;border-collapse:collapse;">';
        echo '<thead><tr>'
            . '<th style="text-align:left;">Kind</th>'
            . '<th style="text-align:left;">Selector</th>'
            . '<th style="text-align:left;">Widget</th>'
            . '<th style="text-align:left;">Slot</th>'
            . '<th style="text-align:left;">Summary</th>'
            . '<th style="text-align:left;">Before</th>'
            . '<th style="text-align:left;">After</th>'
            . '</tr></thead><tbody>';
        foreach ($importSummary['changes'] as $change) {
            $inspectionSummary = $inspectionSummaryForChange($change);
            echo '<tr>'
                . '<td style="vertical-align:top;"><strong>' . htmlspecialchars((string)$change['kind'], ENT_QUOTES) . '</strong></td>'
                . '<td style="vertical-align:top;"><code>' . htmlspecialchars((string)$change['selector'], ENT_QUOTES) . '</code></td>'
                . '<td style="vertical-align:top;">' . htmlspecialchars((string)$change['widget'], ENT_QUOTES) . '</td>'
                . '<td style="vertical-align:top;">' . htmlspecialchars((string)$change['slot'], ENT_QUOTES) . '</td>'
                . '<td style="vertical-align:top;">' . nl2br(htmlspecialchars($inspectionSummary, ENT_QUOTES)) . '</td>'
                . '<td style="vertical-align:top;">' . nl2br(htmlspecialchars((string)$change['before'], ENT_QUOTES)) . '</td>'
                . '<td style="vertical-align:top;">' . nl2br(htmlspecialchars((string)$change['after'], ENT_QUOTES)) . '</td>'
                . '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }
    if ([] !== $importSuggestions) {
        echo '<div style="margin-top:1rem;">';
        echo '<h4 style="margin:.25rem 0 .5rem 0;">Patch Suggestions</h4>';
        echo '<ul style="margin:0;padding-left:1.25rem;">';
        foreach ($importSuggestions as $suggestion) {
            echo '<li><strong>' . htmlspecialchars((string)$suggestion['title'], ENT_QUOTES) . ':</strong> '
                . htmlspecialchars((string)$suggestion['detail'], ENT_QUOTES);
            if ([] !== ($suggestion['targets'] ?? [])) {
                echo '<br><span style="color:#475569;">Likely files: '
                    . htmlspecialchars(implode(', ', array_map('strval', $suggestion['targets'])), ENT_QUOTES)
                    . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    if ([] !== $importDrafts) {
        echo '<div style="margin-top:1rem;">';
        echo '<h4 style="margin:.25rem 0 .5rem 0;">Patch Drafts</h4>';
        foreach ($importDrafts as $draft) {
            echo '<details style="margin-top:.65rem;">';
            echo '<summary><strong>' . htmlspecialchars((string)($draft['title'] ?? 'Draft'), ENT_QUOTES) . '</strong>';
            if ('' !== trim((string)($draft['target'] ?? ''))) {
                echo ' <span style="color:#475569;">[' . htmlspecialchars((string)$draft['target'], ENT_QUOTES) . ']</span>';
            }
            echo '</summary>';
            echo '<pre style="margin-top:.75rem;padding:.75rem;border-radius:.75rem;background:#0f172a;color:#e2e8f0;white-space:pre-wrap;overflow:auto;">'
                . htmlspecialchars((string)($draft['content'] ?? ''), ENT_QUOTES)
                . '</pre>';
            echo '</details>';
        }
        echo '</div>';
    }
    echo '</div>';
}
echo '</div>';

echo '<div class="card" style="margin-top:1rem;padding:1rem 1.25rem;">';
echo '<h3 style="margin-top:0;">Workbench History</h3>';
if ([] === $savedImports) {
    echo '<p>No saved imports yet.</p>';
} else {
    echo '<p>'
        . '<span style="display:inline-block;margin-right:1rem;"><strong>Imports:</strong> ' . (int)$historyTotals['imports'] . '</span>'
        . '<span style="display:inline-block;margin-right:1rem;"><strong>Changes:</strong> ' . (int)$historyTotals['changes'] . '</span>'
        . '<span style="display:inline-block;margin-right:1rem;color:#b45309;"><strong>Pending:</strong> ' . (int)$historyTotals['pending'] . '</span>'
        . '<span style="display:inline-block;margin-right:1rem;color:#166534;"><strong>Accepted:</strong> ' . (int)$historyTotals['accepted'] . '</span>'
        . '<span style="display:inline-block;color:#b91c1c;"><strong>Rejected:</strong> ' . (int)$historyTotals['rejected'] . '</span>'
        . '</p>';

    echo '<div style="display:grid;grid-template-columns:minmax(18rem,24rem) minmax(0,1fr);gap:1rem;align-items:start;">';
    echo '<div>';
    echo '<h4 style="margin:.25rem 0 .5rem 0;">Saved Imports</h4>';
    foreach (array_slice($savedImports, 0, 10) as $savedImport) {
        $savedChanges = is_array($savedImport['changes'] ?? null) ? $savedImport['changes'] : [];
        $savedCounts = $statusCountsForChanges($savedChanges);
        $isCurrent = (string)($savedImport['id'] ?? '') === $selectedHistoryId;
        $savedPayloadType = is_string($savedImport['payload_type'] ?? null) ? (string)$savedImport['payload_type'] : 'review_export';
        $savedConfigSummary = is_array($savedImport['config_summary'] ?? null) ? $savedImport['config_summary'] : [];
        echo '<div style="border:1px solid ' . ($isCurrent ? '#1d4ed8' : '#dbe3ef') . ';border-radius:.75rem;padding:.85rem;margin-top:.75rem;background:' . ($isCurrent ? '#eff6ff' : '#fff') . ';">';
        echo '<p style="margin:0 0 .35rem 0;"><strong>' . htmlspecialchars((string)($savedImport['theme'] ?? ''), ENT_QUOTES) . '</strong></p>';
        echo '<p style="margin:0 0 .35rem 0;color:#475569;">' . htmlspecialchars((string)($savedImport['payload_label'] ?? 'Export'), ENT_QUOTES) . '</p>';
        echo '<p style="margin:0 0 .35rem 0;"><code>' . htmlspecialchars((string)($savedImport['id'] ?? ''), ENT_QUOTES) . '</code></p>';
        echo '<p style="margin:0 0 .5rem 0;color:#475569;">Saved ' . htmlspecialchars((string)($savedImport['created_at'] ?? ''), ENT_QUOTES) . '</p>';
        if ('theme_config_export' === $savedPayloadType) {
            echo '<p style="margin:0 0 .5rem 0;">'
                . '<span style="display:inline-block;margin-right:.6rem;"><strong>Placements:</strong> ' . (int)($savedConfigSummary['placement_count'] ?? 0) . '</span>'
                . '<span style="display:inline-block;margin-right:.6rem;"><strong>Slots:</strong> ' . count(is_array($savedConfigSummary['slot_counts'] ?? null) ? $savedConfigSummary['slot_counts'] : []) . '</span>'
                . '<span style="display:inline-block;"><strong>Token Overrides:</strong> ' . (int)($savedConfigSummary['token_override_count'] ?? 0) . '</span>'
                . '</p>';
        } else {
            echo '<p style="margin:0 0 .5rem 0;">'
                . '<span style="display:inline-block;margin-right:.6rem;"><strong>Total:</strong> ' . count($savedChanges) . '</span>'
                . '<span style="display:inline-block;margin-right:.6rem;color:#b45309;"><strong>P:</strong> ' . (int)$savedCounts['pending'] . '</span>'
                . '<span style="display:inline-block;margin-right:.6rem;color:#166534;"><strong>A:</strong> ' . (int)$savedCounts['accepted'] . '</span>'
                . '<span style="display:inline-block;color:#b91c1c;"><strong>R:</strong> ' . (int)$savedCounts['rejected'] . '</span>'
                . '</p>';
        }
        echo '<p style="margin:0;"><a href="?history_import=' . rawurlencode((string)($savedImport['id'] ?? '')) . '&amp;history_status=' . rawurlencode($historyStatusFilter) . '">Open review</a></p>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div>';
    if (null === $selectedHistoryImport) {
        echo '<p>Select a saved import to review its changes.</p>';
    } else {
        $selectedPayloadType = is_string($selectedHistoryImport['payload_type'] ?? null) ? (string)$selectedHistoryImport['payload_type'] : 'review_export';
        $selectedConfigSummary = is_array($selectedHistoryImport['config_summary'] ?? null) ? $selectedHistoryImport['config_summary'] : [];
        echo '<div style="border:1px solid #dbe3ef;border-radius:.75rem;padding:1rem;">';
        echo '<h4 style="margin-top:0;">Import Review</h4>';
        echo '<p style="margin-top:0;"><strong>ID:</strong> <code>' . htmlspecialchars((string)$selectedHistoryImport['id'], ENT_QUOTES) . '</code>'
            . ' <strong style="margin-left:1rem;">Theme:</strong> ' . htmlspecialchars((string)($selectedHistoryImport['theme'] ?? ''), ENT_QUOTES)
            . ' <strong style="margin-left:1rem;">Scope:</strong> ' . htmlspecialchars((string)($selectedHistoryImport['scope'] ?? ''), ENT_QUOTES)
            . '</p>';
        echo '<p><strong>Payload:</strong> ' . htmlspecialchars((string)($selectedHistoryImport['payload_label'] ?? 'Export'), ENT_QUOTES) . '</p>';
        if ('theme_config_export' === $selectedPayloadType) {
            $selectedSlotCounts = is_array($selectedConfigSummary['slot_counts'] ?? null) ? $selectedConfigSummary['slot_counts'] : [];
            $selectedPreferenceKeys = is_array($selectedConfigSummary['preference_keys'] ?? null) ? $selectedConfigSummary['preference_keys'] : [];
            echo '<p>This import is an XTF theme config export. It captures Marketplace placements and theme preferences, not cssHolmes change rows.</p>';
            echo '<p><strong>Theme Name:</strong> ' . htmlspecialchars((string)($selectedConfigSummary['theme_name'] ?? ''), ENT_QUOTES)
                . ' <strong style="margin-left:1rem;">Version:</strong> ' . htmlspecialchars((string)($selectedConfigSummary['version'] ?? ''), ENT_QUOTES)
                . ' <strong style="margin-left:1rem;">Placements:</strong> ' . (int)($selectedConfigSummary['placement_count'] ?? 0)
                . '</p>';
            if ([] !== $selectedSlotCounts) {
                echo '<div style="overflow:auto;margin-top:1rem;">';
                echo '<table class="outer" style="width:100%;border-collapse:collapse;">';
                echo '<thead><tr><th style="text-align:left;">Slot</th><th style="text-align:left;">Placements</th></tr></thead><tbody>';
                foreach ($selectedSlotCounts as $slotName => $slotCount) {
                    echo '<tr><td>' . htmlspecialchars((string)$slotName, ENT_QUOTES) . '</td><td>' . (int)$slotCount . '</td></tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }
            if ([] !== $selectedPreferenceKeys) {
                echo '<p style="margin-top:1rem;"><strong>Preferences:</strong> ' . htmlspecialchars(implode(', ', array_map('strval', $selectedPreferenceKeys)), ENT_QUOTES) . '</p>';
            }
            echo '<p style="margin-top:1rem;color:#475569;">Use Marketplace import/export for applying this payload. cssHolmes stores it here so you can inspect the structure and compare it with review exports.</p>';
        } else {
            echo '<p>'
                . '<span style="display:inline-block;margin-right:1rem;color:#b45309;"><strong>Pending:</strong> ' . (int)$selectedHistoryCounts['pending'] . '</span>'
                . '<span style="display:inline-block;margin-right:1rem;color:#166534;"><strong>Accepted:</strong> ' . (int)$selectedHistoryCounts['accepted'] . '</span>'
                . '<span style="display:inline-block;color:#b91c1c;"><strong>Rejected:</strong> ' . (int)$selectedHistoryCounts['rejected'] . '</span>'
                . '</p>';
            echo '<form method="get" action="" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;margin-bottom:1rem;">';
            echo '<input type="hidden" name="history_import" value="' . htmlspecialchars((string)$selectedHistoryImport['id'], ENT_QUOTES) . '">';
            echo '<div><label for="history_status"><strong>Status Filter</strong></label><br>';
            echo '<select id="history_status" name="history_status">';
            foreach (['all' => 'All changes', 'pending' => 'Pending only', 'accepted' => 'Accepted only', 'rejected' => 'Rejected only'] as $filterValue => $filterLabel) {
                $selected = $historyStatusFilter === $filterValue ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($filterValue, ENT_QUOTES) . '"' . $selected . '>' . htmlspecialchars($filterLabel, ENT_QUOTES) . '</option>';
            }
            echo '</select></div>';
            echo '<div><button class="btn btn-primary" type="submit">Apply Filter</button></div>';
            echo '</form>';
        }
        if ('theme_config_export' !== $selectedPayloadType && [] === $filteredHistoryChanges) {
            echo '<p>No changes match the current filter.</p>';
        } elseif ('theme_config_export' !== $selectedPayloadType) {
            echo '<div style="overflow:auto;">';
            echo '<table class="outer" style="width:100%;border-collapse:collapse;">';
            echo '<thead><tr>'
                . '<th style="text-align:left;">Kind</th>'
                . '<th style="text-align:left;">Selector</th>'
                . '<th style="text-align:left;">Widget</th>'
                . '<th style="text-align:left;">Slot</th>'
                . '<th style="text-align:left;">Summary</th>'
                . '<th style="text-align:left;">Before</th>'
                . '<th style="text-align:left;">After</th>'
                . '<th style="text-align:left;">Status</th>'
                . '<th style="text-align:left;">Action</th>'
                . '</tr></thead><tbody>';
            foreach ($selectedHistoryChanges as $changeIndex => $savedChange) {
                $status = is_string($savedChange['status'] ?? null) ? strtolower((string)$savedChange['status']) : 'pending';
                if ('all' !== $historyStatusFilter && $status !== $historyStatusFilter) {
                    continue;
                }

                $statusColor = match ($status) {
                    'accepted' => '#166534',
                    'rejected' => '#b91c1c',
                    default => '#b45309',
                };
                $appliedAt = is_string($savedChange['applied_at'] ?? null) ? trim((string)$savedChange['applied_at']) : '';
                $appliedTarget = is_string($savedChange['applied_target'] ?? null) ? trim((string)$savedChange['applied_target']) : '';
                $appliedMessage = is_string($savedChange['applied_message'] ?? null) ? trim((string)$savedChange['applied_message']) : '';
                $rolledBackAt = is_string($savedChange['rolled_back_at'] ?? null) ? trim((string)$savedChange['rolled_back_at']) : '';
                $rolledBackMessage = is_string($savedChange['rolled_back_message'] ?? null) ? trim((string)$savedChange['rolled_back_message']) : '';
                $changeKind = is_string($savedChange['kind'] ?? null) ? strtolower(trim((string)$savedChange['kind'])) : '';
                $canApply = 'accepted' === $status && in_array($changeKind, ['text', 'token', 'color', 'style', 'layout', 'measure'], true);
                $changeSuggestion = is_array($selectedHistorySuggestions[$changeIndex] ?? null) ? $selectedHistorySuggestions[$changeIndex] : [];
                $applyPreview = $canApply ? $patchApplier->preview($selectedHistoryTheme, $savedChange, $changeSuggestion) : null;
                $canApplyNow = $canApply && is_array($applyPreview) && true === ($applyPreview['can_apply'] ?? false);
                $inspectionSummary = $inspectionSummaryForChange($savedChange);
                echo '<tr>'
                    . '<td style="vertical-align:top;"><strong>' . htmlspecialchars((string)($savedChange['kind'] ?? ''), ENT_QUOTES) . '</strong></td>'
                    . '<td style="vertical-align:top;"><code>' . htmlspecialchars((string)($savedChange['selector'] ?? ''), ENT_QUOTES) . '</code></td>'
                    . '<td style="vertical-align:top;">' . htmlspecialchars((string)($savedChange['widget'] ?? ''), ENT_QUOTES) . '</td>'
                    . '<td style="vertical-align:top;">' . htmlspecialchars((string)($savedChange['slot'] ?? ''), ENT_QUOTES) . '</td>'
                    . '<td style="vertical-align:top;">' . nl2br(htmlspecialchars($inspectionSummary, ENT_QUOTES)) . '</td>'
                    . '<td style="vertical-align:top;">' . nl2br(htmlspecialchars((string)($savedChange['before'] ?? ''), ENT_QUOTES)) . '</td>'
                    . '<td style="vertical-align:top;">' . nl2br(htmlspecialchars((string)($savedChange['after'] ?? ''), ENT_QUOTES)) . '</td>'
                    . '<td style="vertical-align:top;color:' . $statusColor . ';">' . htmlspecialchars($status, ENT_QUOTES) . '</td>'
                    . '<td style="vertical-align:top;">'
                    . '<form method="post" action="" style="display:flex;gap:.5rem;flex-wrap:wrap;">'
                    . $GLOBALS['xoopsSecurity']->getTokenHTML()
                    . '<input type="hidden" name="op" value="change_status">'
                    . '<input type="hidden" name="import_id" value="' . htmlspecialchars((string)$selectedHistoryImport['id'], ENT_QUOTES) . '">'
                    . '<input type="hidden" name="change_index" value="' . (int)$changeIndex . '">'
                    . '<button class="btn btn-primary" type="submit" name="status" value="accepted">Accept</button>'
                    . '<button class="btn" type="submit" name="status" value="rejected">Reject</button>'
                    . '<button class="btn" type="submit" name="status" value="pending">Reset</button>'
                    . '</form>'
                    . (is_array($applyPreview) && true === ($applyPreview['supported'] ?? false)
                        ? '<details style="margin-top:.5rem;">'
                        . '<summary><strong>Preview Apply</strong></summary>'
                        . '<div style="margin-top:.5rem;font-size:12px;color:#475569;">'
                        . htmlspecialchars((string)($applyPreview['message'] ?? ''), ENT_QUOTES)
                        . ('' !== trim((string)($applyPreview['target'] ?? ''))
                            ? '<br><strong>Target:</strong> <code>' . htmlspecialchars((string)$applyPreview['target'], ENT_QUOTES) . '</code>'
                            : '')
                        . '</div>'
                        . '<div style="margin-top:.5rem;">'
                        . '<div style="font-size:12px;font-weight:700;color:#475569;">Current</div>'
                        . '<pre style="margin:.25rem 0 0;padding:.75rem;border-radius:.75rem;background:#0f172a;color:#e2e8f0;white-space:pre-wrap;overflow:auto;">'
                        . htmlspecialchars((string)($applyPreview['current'] ?? ''), ENT_QUOTES)
                        . '</pre>'
                        . '</div>'
                        . '<div style="margin-top:.5rem;">'
                        . '<div style="font-size:12px;font-weight:700;color:#475569;">Proposed</div>'
                        . '<pre style="margin:.25rem 0 0;padding:.75rem;border-radius:.75rem;background:#052e16;color:#dcfce7;white-space:pre-wrap;overflow:auto;">'
                        . htmlspecialchars((string)($applyPreview['proposed'] ?? ''), ENT_QUOTES)
                        . '</pre>'
                        . '</div>'
                        . '</details>'
                        : '')
                    . ($canApplyNow
                        ? '<form method="post" action="" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">'
                        . $GLOBALS['xoopsSecurity']->getTokenHTML()
                        . '<input type="hidden" name="op" value="apply_change">'
                        . '<input type="hidden" name="import_id" value="' . htmlspecialchars((string)$selectedHistoryImport['id'], ENT_QUOTES) . '">'
                        . '<input type="hidden" name="change_index" value="' . (int)$changeIndex . '">'
                        . '<label style="display:flex;align-items:center;gap:.35rem;font-size:12px;color:#475569;">'
                        . '<input type="checkbox" name="confirm_apply" value="1">'
                        . 'I reviewed the preview'
                        . '</label>'
                        . '<button class="btn btn-primary" type="submit">' . ('' !== $appliedAt ? 'Reapply' : 'Apply After Preview') . '</button>'
                        . '</form>'
                        : '')
                    . ($canApply && !$canApplyNow
                        ? '<div style="margin-top:.5rem;font-size:12px;color:#92400e;">Apply is disabled until the preview reports a safe, unambiguous target.</div>'
                        : '')
                    . ('' !== $appliedAt
                        ? '<form method="post" action="" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">'
                        . $GLOBALS['xoopsSecurity']->getTokenHTML()
                        . '<input type="hidden" name="op" value="rollback_change">'
                        . '<input type="hidden" name="import_id" value="' . htmlspecialchars((string)$selectedHistoryImport['id'], ENT_QUOTES) . '">'
                        . '<input type="hidden" name="change_index" value="' . (int)$changeIndex . '">'
                        . '<label style="display:flex;align-items:center;gap:.35rem;font-size:12px;color:#475569;">'
                        . '<input type="checkbox" name="confirm_rollback" value="1">'
                        . 'Restore previous file state'
                        . '</label>'
                        . '<button class="btn" type="submit">Rollback</button>'
                        . '</form>'
                        : '')
                    . ('' !== $appliedAt
                        ? '<div style="margin-top:.5rem;font-size:12px;color:#166534;">Applied '
                        . htmlspecialchars($appliedAt, ENT_QUOTES)
                        . ('' !== $appliedTarget ? '<br><code>' . htmlspecialchars($appliedTarget, ENT_QUOTES) . '</code>' : '')
                        . ('' !== $appliedMessage ? '<br>' . htmlspecialchars($appliedMessage, ENT_QUOTES) : '')
                        . '</div>'
                        : '')
                    . ('' !== $rolledBackAt
                        ? '<div style="margin-top:.5rem;font-size:12px;color:#92400e;">Rolled back '
                        . htmlspecialchars($rolledBackAt, ENT_QUOTES)
                        . ('' !== $rolledBackMessage ? '<br>' . htmlspecialchars($rolledBackMessage, ENT_QUOTES) : '')
                        . '</div>'
                        : '')
                    . '</td>'
                    . '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }

        if (is_string($selectedHistoryImport['raw_payload'] ?? null) && '' !== trim((string)$selectedHistoryImport['raw_payload'])) {
            echo '<details style="margin-top:1rem;">';
            echo '<summary><strong>Raw Export JSON</strong></summary>';
            echo '<pre style="margin-top:.75rem;padding:.75rem;border-radius:.75rem;background:#0f172a;color:#e2e8f0;white-space:pre-wrap;overflow:auto;">'
                . htmlspecialchars((string)$selectedHistoryImport['raw_payload'], ENT_QUOTES)
                . '</pre>';
            echo '</details>';
        }
        if ('theme_config_export' !== $selectedPayloadType && [] !== $selectedHistorySuggestions) {
            echo '<div style="margin-top:1rem;">';
            echo '<h5 style="margin:.25rem 0 .5rem 0;">Likely Patch Targets</h5>';
            echo '<ul style="margin:0;padding-left:1.25rem;">';
            foreach ($selectedHistorySuggestions as $historySuggestion) {
                echo '<li><strong>' . htmlspecialchars((string)$historySuggestion['title'], ENT_QUOTES) . ':</strong> '
                    . htmlspecialchars((string)$historySuggestion['detail'], ENT_QUOTES);
                if ([] !== ($historySuggestion['targets'] ?? [])) {
                    echo '<br><span style="color:#475569;">Likely files: '
                        . htmlspecialchars(implode(', ', array_map('strval', $historySuggestion['targets'])), ENT_QUOTES)
                        . '</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        if ('theme_config_export' !== $selectedPayloadType && [] !== $selectedHistoryDrafts) {
            echo '<div style="margin-top:1rem;">';
            echo '<h5 style="margin:.25rem 0 .5rem 0;">Patch Drafts</h5>';
            foreach ($selectedHistoryDrafts as $historyDraft) {
                echo '<details style="margin-top:.65rem;">';
                echo '<summary><strong>' . htmlspecialchars((string)($historyDraft['title'] ?? 'Draft'), ENT_QUOTES) . '</strong>';
                if ('' !== trim((string)($historyDraft['target'] ?? ''))) {
                    echo ' <span style="color:#475569;">[' . htmlspecialchars((string)$historyDraft['target'], ENT_QUOTES) . ']</span>';
                }
                echo '</summary>';
                echo '<pre style="margin-top:.75rem;padding:.75rem;border-radius:.75rem;background:#0f172a;color:#e2e8f0;white-space:pre-wrap;overflow:auto;">'
                    . htmlspecialchars((string)($historyDraft['content'] ?? ''), ENT_QUOTES)
                    . '</pre>';
                echo '</details>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}
echo '</div>';

echo '<div class="card" style="margin-top:1rem;padding:1rem 1.25rem;">';
echo '<h3 style="margin-top:0;">Phase 2 Workbench</h3>';
echo '<p>The server-side analyzer workbench now runs multiple Phase 2 checks against both frontend and admin XTF themes: manifest validation, design token usage, template structure, and accessibility-oriented review. The widget scan below applies widget-output rules directly to pasted HTML fragments.</p>';
echo '<form method="get" action="" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;">';
echo '<div><label for="scan_scope"><strong>Scope</strong></label><br>';
echo '<select id="scan_scope" name="scan_scope" onchange="this.form.submit()">';
foreach (['admin' => 'Admin Themes', 'site' => 'Frontend Themes'] as $scopeValue => $scopeLabel) {
    $selected = $scanScope === $scopeValue ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($scopeValue, ENT_QUOTES) . '"' . $selected . '>' . htmlspecialchars($scopeLabel, ENT_QUOTES) . '</option>';
}
echo '</select></div>';

echo '<div><label for="scan_theme"><strong>Theme</strong></label><br>';
echo '<select id="scan_theme" name="scan_theme" style="min-width:24rem;">';
foreach ($scopeThemes as $themeOption) {
    $selected = null !== $selectedTheme && $selectedTheme->key === $themeOption->key ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($themeOption->key, ENT_QUOTES) . '"' . $selected . '>'
        . htmlspecialchars($themeOption->label, ENT_QUOTES)
        . '</option>';
}
echo '</select></div>';
echo '<div><button class="btn btn-primary" type="submit">Scan Theme</button></div>';
echo '</form>';

if (null === $selectedTheme) {
    echo '<div style="margin-top:1rem;padding:1rem 1.25rem;border:1px solid #dbe3ef;border-radius:.85rem;background:#f8fafc;">';
    echo '<p style="margin:0 0 .5rem 0;"><strong>Generic XOOPS mode:</strong> no XTF themes with a <code>theme.json</code> manifest were found for this scope.</p>';
    echo '<p style="margin:0;">Theme token analysis is unavailable here, but the overlay still supports HTML5, accessibility, layout, inspect, measure, copy, export, and widget HTML scan workflows.</p>';
    echo '</div>';
} else {
    echo '<div style="margin-top:1rem;">';
    echo '<p><strong>Selected:</strong> ' . htmlspecialchars($selectedTheme->label, ENT_QUOTES) . '<br>';
    echo '<strong>Path:</strong> <code>' . htmlspecialchars($selectedTheme->path, ENT_QUOTES) . '</code></p>';
    echo '<p>'
        . '<span style="display:inline-block;margin-right:1rem;color:#b91c1c;"><strong>Errors:</strong> ' . (int)$findingCounts[Severity::Error->value] . '</span>'
        . '<span style="display:inline-block;margin-right:1rem;color:#b45309;"><strong>Warnings:</strong> ' . (int)$findingCounts[Severity::Warning->value] . '</span>'
        . '<span style="display:inline-block;color:#1d4ed8;"><strong>Info:</strong> ' . (int)$findingCounts[Severity::Info->value] . '</span>'
        . '</p>';

    if ([] === $findings) {
        echo '<p>No analyzer findings for this theme.</p>';
    } else {
        foreach ($analyzerResults as $analyzerLabel => $analyzerFindings) {
            if ([] === $analyzerFindings) {
                continue;
            }

            echo '<div style="margin-top:1.25rem;">';
            echo '<h4 style="margin:.25rem 0 .5rem 0;">' . htmlspecialchars($analyzerLabel, ENT_QUOTES) . '</h4>';
            echo '<div style="overflow:auto;">';
            echo '<table class="outer" style="width:100%;border-collapse:collapse;">';
            echo '<thead><tr>'
                . '<th style="text-align:left;">Severity</th>'
                . '<th style="text-align:left;">Rule</th>'
                . '<th style="text-align:left;">Message</th>'
                . '<th style="text-align:left;">Target</th>'
                . '<th style="text-align:left;">Suggestion</th>'
                . '</tr></thead><tbody>';

            foreach ($analyzerFindings as $finding) {
                $severityColor = match ($finding->severity) {
                    Severity::Error => '#b91c1c',
                    Severity::Warning => '#b45309',
                    Severity::Info => '#1d4ed8',
                };

                $target = htmlspecialchars($finding->target, ENT_QUOTES);
                if (null !== $finding->line) {
                    $target .= ':' . $finding->line;
                }

                echo '<tr>'
                    . '<td style="vertical-align:top;color:' . $severityColor . ';"><strong>' . htmlspecialchars(strtoupper($finding->severity->value), ENT_QUOTES) . '</strong></td>'
                    . '<td style="vertical-align:top;"><code>' . htmlspecialchars($finding->ruleId, ENT_QUOTES) . '</code></td>'
                    . '<td style="vertical-align:top;">' . htmlspecialchars($finding->message, ENT_QUOTES) . '</td>'
                    . '<td style="vertical-align:top;"><code>' . $target . '</code></td>'
                    . '<td style="vertical-align:top;">' . htmlspecialchars((string)$finding->suggestion, ENT_QUOTES) . '</td>'
                    . '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
}
echo '</div>';

echo '<div class="card" style="margin-top:1rem;padding:1rem 1.25rem;">';
echo '<h3 style="margin-top:0;">Widget Output Scan</h3>';
echo '<p>Paste rendered widget HTML to run the widget-output analyzer without waiting for a full page scan.</p>';
echo '<form method="post" action="">';
echo $GLOBALS['xoopsSecurity']->getTokenHTML();
echo '<input type="hidden" name="op" value="scan_widget_html">';
echo '<p><label for="widget_name"><strong>Widget Name</strong></label><br>';
echo '<input id="widget_name" name="widget_name" type="text" value="' . htmlspecialchars($widgetScanName, ENT_QUOTES) . '" style="min-width:24rem;"></p>';
echo '<p><label for="widget_html"><strong>Rendered HTML</strong></label><br>';
echo '<textarea id="widget_html" name="widget_html" rows="10" style="width:100%;font-family:Consolas,Monaco,monospace;">'
    . htmlspecialchars($widgetScanHtml, ENT_QUOTES)
    . '</textarea></p>';
echo '<p><button class="btn btn-primary" type="submit">Scan Widget HTML</button></p>';
echo '</form>';
if ([] !== $widgetFindings) {
    echo '<p>'
        . '<span style="display:inline-block;margin-right:1rem;color:#b91c1c;"><strong>Errors:</strong> ' . (int)$widgetFindingCounts[Severity::Error->value] . '</span>'
        . '<span style="display:inline-block;margin-right:1rem;color:#b45309;"><strong>Warnings:</strong> ' . (int)$widgetFindingCounts[Severity::Warning->value] . '</span>'
        . '<span style="display:inline-block;color:#1d4ed8;"><strong>Info:</strong> ' . (int)$widgetFindingCounts[Severity::Info->value] . '</span>'
        . '</p>';
    echo '<div style="overflow:auto;">';
    echo '<table class="outer" style="width:100%;border-collapse:collapse;">';
    echo '<thead><tr>'
        . '<th style="text-align:left;">Severity</th>'
        . '<th style="text-align:left;">Rule</th>'
        . '<th style="text-align:left;">Message</th>'
        . '<th style="text-align:left;">Suggestion</th>'
        . '</tr></thead><tbody>';
    foreach ($widgetFindings as $widgetFinding) {
        $severityColor = match ($widgetFinding->severity) {
            Severity::Error => '#b91c1c',
            Severity::Warning => '#b45309',
            Severity::Info => '#1d4ed8',
        };
        echo '<tr>'
            . '<td style="vertical-align:top;color:' . $severityColor . ';"><strong>' . htmlspecialchars(strtoupper($widgetFinding->severity->value), ENT_QUOTES) . '</strong></td>'
            . '<td style="vertical-align:top;"><code>' . htmlspecialchars($widgetFinding->ruleId, ENT_QUOTES) . '</code></td>'
            . '<td style="vertical-align:top;">' . htmlspecialchars($widgetFinding->message, ENT_QUOTES) . '</td>'
            . '<td style="vertical-align:top;">' . htmlspecialchars((string)$widgetFinding->suggestion, ENT_QUOTES) . '</td>'
            . '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
} elseif ('' !== $widgetScanHtml && [] === $importErrors) {
    echo '<p>No widget-output findings for this HTML snippet.</p>';
}
echo '</div>';

//require_once XOOPS_ROOT_PATH . '/modules/' . $xoopsModule->getVar('dirname') . '/class/menu.php';
//
//$menu = new \XoopsModules\Cssholmes\Menu();
//$menu->addItem('about', _AM_CSSHOLMES_MANAGER_ABOUT, 'about.php');
//$xoopsTpl->assign('cssholmes_menu', $menu->_items);
//
//$admin = new \XoopsModules\Cssholmes\Menu();
//$admin->addItem('update', _AM_CSSHOLMES_MANAGER_UPDATE, '../../system/admin.php?fct=modulesadmin&op=update&module=cssholmes');
//$admin->addItem('xoofoo', _AM_CSSHOLMES_MANAGER_PREFERENCES, 'https://www.xoofoo.org');
//$xoopsTpl->assign($xoopsModule->getVar('dirname') . '_admin', $admin->_items);
//
//$xoopsTpl->assign('module_dirname', $xoopsModule->getVar('dirname'));
//
//$xoopsTpl->display('db:admin/' . $xoopsModule->getVar('dirname') . '_admin_index.tpl');

require_once __DIR__ . '/admin_footer.php';
