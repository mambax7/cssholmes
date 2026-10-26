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
 * @license             GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              kris <https://www.xoofoo.org>
 * @author              XOOPS Development Team, Mamba <mambax7@gmail.com> 
 **/

// Guided theme designer.
define('_MD_CSSHOLMES_DESIGNER_TITLE', 'Theme designer');
define('_MD_CSSHOLMES_DESIGNER_OPEN', 'Design theme');
define('_MD_CSSHOLMES_DESIGNER_CLOSE', 'Close');
define('_MD_CSSHOLMES_DESIGNER_THEME', 'Theme');
define('_MD_CSSHOLMES_DESIGNER_PICK', 'Pick element');
define('_MD_CSSHOLMES_DESIGNER_CANCEL_PICK', 'Cancel picking');
define('_MD_CSSHOLMES_DESIGNER_PICK_HINT', 'Click an element, or focus it and press Enter. Escape cancels.');
define('_MD_CSSHOLMES_DESIGNER_EMPTY', 'Pick an element to edit its theme styles.');
define('_MD_CSSHOLMES_DESIGNER_SELECTOR', 'Selector');
define('_MD_CSSHOLMES_DESIGNER_SELECTED', 'Selected element');
define('_MD_CSSHOLMES_DESIGNER_MATCHES', '{count} matching elements');
define('_MD_CSSHOLMES_DESIGNER_SCOPE_NOTE', 'Changes apply to every matching element in this theme.');
define('_MD_CSSHOLMES_DESIGNER_INVALID_SELECTOR', 'Use up to four tag, class or ID selectors joined by spaces or >. Gallery controls cannot be selected.');
define('_MD_CSSHOLMES_DESIGNER_NO_MATCHES', 'This selector has no editable matches on this page.');
define('_MD_CSSHOLMES_DESIGNER_INVALID_VALUE', 'Enter a supported CSS value, or leave blank to remove the override.');
define('_MD_CSSHOLMES_DESIGNER_INVALID_CONTROLS', 'Correct the highlighted value before continuing.');
define('_MD_CSSHOLMES_DESIGNER_RULE_LIST', 'Saved and draft rules');
define('_MD_CSSHOLMES_DESIGNER_CHOOSE_RULE', 'Choose a rule');
define('_MD_CSSHOLMES_DESIGNER_MODE', 'Color mode');
define('_MD_CSSHOLMES_DESIGNER_BREAKPOINT', 'Screen size');
define('_MD_CSSHOLMES_DESIGNER_STATE', 'Element state');
define('_MD_CSSHOLMES_DESIGNER_ALL', 'All');
define('_MD_CSSHOLMES_DESIGNER_LIGHT', 'Light');
define('_MD_CSSHOLMES_DESIGNER_DARK', 'Dark');
define('_MD_CSSHOLMES_DESIGNER_MOBILE', 'Mobile (up to 575px)');
define('_MD_CSSHOLMES_DESIGNER_TABLET', 'Tablet (576–991px)');
define('_MD_CSSHOLMES_DESIGNER_DESKTOP', 'Desktop (992px and up)');
define('_MD_CSSHOLMES_DESIGNER_NORMAL', 'Normal');
define('_MD_CSSHOLMES_DESIGNER_HOVER', 'Hover');
define('_MD_CSSHOLMES_DESIGNER_FOCUS', 'Keyboard focus');
define('_MD_CSSHOLMES_DESIGNER_PREVIEW_NOTE', 'Preview follows the page color mode, current screen size and actual hover or focus state.');
define('_MD_CSSHOLMES_DESIGNER_TYPOGRAPHY', 'Typography');
define('_MD_CSSHOLMES_DESIGNER_COLORS', 'Colors');
define('_MD_CSSHOLMES_DESIGNER_SPACING', 'Spacing');
define('_MD_CSSHOLMES_DESIGNER_SIZE', 'Size');
define('_MD_CSSHOLMES_DESIGNER_BORDER', 'Border');
define('_MD_CSSHOLMES_DESIGNER_LAYOUT', 'Layout');
define('_MD_CSSHOLMES_DESIGNER_COLOR', 'Text color');
define('_MD_CSSHOLMES_DESIGNER_BACKGROUND_COLOR', 'Background color');
define('_MD_CSSHOLMES_DESIGNER_BORDER_COLOR', 'Border color');
define('_MD_CSSHOLMES_DESIGNER_FONT_SIZE', 'Font size');
define('_MD_CSSHOLMES_DESIGNER_LINE_HEIGHT', 'Line height');
define('_MD_CSSHOLMES_DESIGNER_LETTER_SPACING', 'Letter spacing');
define('_MD_CSSHOLMES_DESIGNER_FONT_WEIGHT', 'Font weight');
define('_MD_CSSHOLMES_DESIGNER_FONT_FAMILY', 'Font family');
define('_MD_CSSHOLMES_DESIGNER_TEXT_ALIGN', 'Text alignment');
define('_MD_CSSHOLMES_DESIGNER_TEXT_TRANSFORM', 'Text case');
define('_MD_CSSHOLMES_DESIGNER_TEXT_DECORATION', 'Text decoration');
define('_MD_CSSHOLMES_DESIGNER_FONT_STYLE', 'Font style');
define('_MD_CSSHOLMES_DESIGNER_WIDTH', 'Width');
define('_MD_CSSHOLMES_DESIGNER_HEIGHT', 'Height');
define('_MD_CSSHOLMES_DESIGNER_MAX_WIDTH', 'Maximum width');
define('_MD_CSSHOLMES_DESIGNER_MIN_WIDTH', 'Minimum width');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_TOP', 'Top margin');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_RIGHT', 'Right margin');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_BOTTOM', 'Bottom margin');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_LEFT', 'Left margin');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_INLINE_START', 'Start margin');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_INLINE_END', 'End margin');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_BLOCK_START', 'Block start margin');
define('_MD_CSSHOLMES_DESIGNER_MARGIN_BLOCK_END', 'Block end margin');
define('_MD_CSSHOLMES_DESIGNER_PADDING_TOP', 'Top padding');
define('_MD_CSSHOLMES_DESIGNER_PADDING_RIGHT', 'Right padding');
define('_MD_CSSHOLMES_DESIGNER_PADDING_BOTTOM', 'Bottom padding');
define('_MD_CSSHOLMES_DESIGNER_PADDING_LEFT', 'Left padding');
define('_MD_CSSHOLMES_DESIGNER_PADDING_INLINE_START', 'Start padding');
define('_MD_CSSHOLMES_DESIGNER_PADDING_INLINE_END', 'End padding');
define('_MD_CSSHOLMES_DESIGNER_PADDING_BLOCK_START', 'Block start padding');
define('_MD_CSSHOLMES_DESIGNER_PADDING_BLOCK_END', 'Block end padding');
define('_MD_CSSHOLMES_DESIGNER_GAP', 'Gap');
define('_MD_CSSHOLMES_DESIGNER_BORDER_WIDTH', 'Border width');
define('_MD_CSSHOLMES_DESIGNER_BORDER_RADIUS', 'Corner radius');
define('_MD_CSSHOLMES_DESIGNER_BORDER_STYLE', 'Border style');
define('_MD_CSSHOLMES_DESIGNER_DISPLAY', 'Display');
define('_MD_CSSHOLMES_DESIGNER_ALIGN_ITEMS', 'Align items');
define('_MD_CSSHOLMES_DESIGNER_JUSTIFY_CONTENT', 'Justify content');
define('_MD_CSSHOLMES_DESIGNER_DEFAULT_VALUE', 'Theme default');
define('_MD_CSSHOLMES_DESIGNER_COMPUTED', 'Current: {value}');
define('_MD_CSSHOLMES_DESIGNER_COLOR_PICKER', '{label} picker');
define('_MD_CSSHOLMES_DESIGNER_UNDO', 'Undo');
define('_MD_CSSHOLMES_DESIGNER_REDO', 'Redo');
define('_MD_CSSHOLMES_DESIGNER_RESET', 'Reset selected');
define('_MD_CSSHOLMES_DESIGNER_SAVE', 'Save to theme');
define('_MD_CSSHOLMES_DESIGNER_DISCARD', 'Discard draft');
define('_MD_CSSHOLMES_DESIGNER_RESTORE', 'Restore previous save');
define('_MD_CSSHOLMES_DESIGNER_RESTORE_CONFIRM', 'Restore the previous saved theme configuration? This replaces the current saved styles and any draft.');
define('_MD_CSSHOLMES_DESIGNER_MORE_ACTIONS', 'More actions');
define('_MD_CSSHOLMES_DESIGNER_RELOAD', 'Reload saved settings');
define('_MD_CSSHOLMES_DESIGNER_RELOAD_CONFIRM', 'Discard this draft and load the latest saved theme settings?');
define('_MD_CSSHOLMES_DESIGNER_EXPORT_CSS', 'Export CSS');
define('_MD_CSSHOLMES_DESIGNER_EXPORT_JSON', 'Export JSON');
define('_MD_CSSHOLMES_DESIGNER_LOADING', 'Loading theme styles…');
define('_MD_CSSHOLMES_DESIGNER_READY', 'Ready. Changes preview here until you save.');
define('_MD_CSSHOLMES_DESIGNER_UNSAVED', 'Unsaved theme changes.');
define('_MD_CSSHOLMES_DESIGNER_SAVED', 'Saved to theme.');
define('_MD_CSSHOLMES_DESIGNER_DISCARDED', 'Draft discarded.');
define('_MD_CSSHOLMES_DESIGNER_RESTORED', 'Previous save restored.');
define('_MD_CSSHOLMES_DESIGNER_SAVING', 'Saving…');
define('_MD_CSSHOLMES_DESIGNER_RESTORING', 'Restoring…');
define('_MD_CSSHOLMES_DESIGNER_REQUEST_FAILED', 'Could not load or save theme styles. Your draft is retained.');
define('_MD_CSSHOLMES_DESIGNER_PREVIEW_ONLY', 'Preview only. Saving requires workbench permission.');
define('_MD_CSSHOLMES_DESIGNER_CHANGES', '{count} style rules');
define('_MD_CSSHOLMES_DESIGNER_UNSUPPORTED', 'This browser cannot open the theme designer.');
define('_MD_CSSHOLMES_DESIGNER_TARGET_COMMENT_TITLES', 'Comment titles');
define('_MD_CSSHOLMES_DESIGNER_TARGET_COMMENT_BODY', 'Comment text');
define('_MD_CSSHOLMES_DESIGNER_TARGET_ARTICLE_TITLES', 'Article titles');
define('_MD_CSSHOLMES_DESIGNER_TARGET_ARTICLE_BODY', 'Article text');
define('_MD_CSSHOLMES_DESIGNER_TARGET_BUTTON', 'Buttons');
define('_MD_CSSHOLMES_DESIGNER_TARGET_INPUT', 'Form fields');
define('_MD_CSSHOLMES_DESIGNER_TARGET_LABEL', 'Form labels');
define('_MD_CSSHOLMES_DESIGNER_TARGET_TABLE', 'Tables');
define('_MD_CSSHOLMES_DESIGNER_TARGET_TABLE_HEADER', 'Table headings');
define('_MD_CSSHOLMES_DESIGNER_TARGET_MESSAGE', 'Messages');
define('_MD_CSSHOLMES_DESIGNER_TARGET_QUOTE', 'Quotations');
define('_MD_CSSHOLMES_DESIGNER_TARGET_LINK', 'Links');
define('_MD_CSSHOLMES_DESIGNER_LIBRARY', 'Library');
define('_MD_CSSHOLMES_DESIGNER_STYLE_SETS', 'Style sets');
define('_MD_CSSHOLMES_DESIGNER_CHOOSE_SET', 'Choose a set or palette');
define('_MD_CSSHOLMES_DESIGNER_CURRENT_THEME_SETS', 'Current theme');
define('_MD_CSSHOLMES_DESIGNER_OTHER_THEME_SETS', 'Other themes');
define('_MD_CSSHOLMES_DESIGNER_STARTER_PALETTES', 'Starter palettes');
define('_MD_CSSHOLMES_DESIGNER_SET_NAME', 'Set name');
define('_MD_CSSHOLMES_DESIGNER_SAVE_NEW_SET', 'Save as new set');
define('_MD_CSSHOLMES_DESIGNER_UPDATE_SET', 'Update selected set');
define('_MD_CSSHOLMES_DESIGNER_PREVIEW_SET', 'Preview selected');
define('_MD_CSSHOLMES_DESIGNER_LIKE', 'Like');
define('_MD_CSSHOLMES_DESIGNER_UNLIKE', 'Unlike');
define('_MD_CSSHOLMES_DESIGNER_SET_DETAILS', '{author} · {count} likes');
define('_MD_CSSHOLMES_DESIGNER_LIBRARY_NOTE', 'Library saves do not change the theme. Preview first, then use Save to theme to publish.');
define('_MD_CSSHOLMES_DESIGNER_SET_SAVED', 'Set saved in library. Theme unchanged.');
define('_MD_CSSHOLMES_DESIGNER_SET_PREVIEWED', 'Set previewed. Use Save to theme to publish.');
define('_MD_CSSHOLMES_DESIGNER_REPLACE_DRAFT_CONFIRM', 'Discard the current draft, including invalid values, and preview this selection?');
define('_MD_CSSHOLMES_DESIGNER_TRANSFER_SETS', 'Import / export');
define('_MD_CSSHOLMES_DESIGNER_IMPORT_FILE', 'Import set JSON');
define('_MD_CSSHOLMES_DESIGNER_PREVIEW_IMPORT', 'Preview import');
define('_MD_CSSHOLMES_DESIGNER_SAVE_IMPORT', 'Save imported set');
define('_MD_CSSHOLMES_DESIGNER_EXPORT_PORTABLE', 'Export portable set');
define('_MD_CSSHOLMES_DESIGNER_IMPORT_SIZE', 'Choose a JSON file no larger than 128 KiB.');
define('_MD_CSSHOLMES_DESIGNER_IMPORT_INVALID', 'Choose a file containing valid JSON.');
define('_MD_CSSHOLMES_DESIGNER_IMPORT_PREVIEWED', 'Import previewed. Save imported set adds it to the library only.');
define('_MD_CSSHOLMES_DESIGNER_SET_NAME_REQUIRED', 'Enter a set name.');
define('_MD_CSSHOLMES_DESIGNER_ORIGINAL_THEME', 'Original theme');
define('_MD_CSSHOLMES_DESIGNER_COMPARE_ORIGINAL', 'Compare original');
define('_MD_CSSHOLMES_DESIGNER_PREVIEW_ORIGINAL', 'Preview original defaults');
define('_MD_CSSHOLMES_DESIGNER_RESET_ORIGINAL', 'Reset theme to original');
define('_MD_CSSHOLMES_DESIGNER_RESET_ORIGINAL_CONFIRM', 'Remove all published designer overrides and discard this draft? This restores the untouched theme. Library sets are kept.');
define('_MD_CSSHOLMES_DESIGNER_ORIGINAL_PREVIEWED', 'Original theme previewed. Use Save to theme to publish.');
define('_MD_CSSHOLMES_DESIGNER_ORIGINAL_RESET', 'Theme reset to original. Library sets kept.');
define('_MD_CSSHOLMES_DESIGNER_PALETTE_CONTRAST', '{name}: {ratio}:1 · {result}');
define('_MD_CSSHOLMES_DESIGNER_CONTRAST_PASS', 'Pass');
define('_MD_CSSHOLMES_DESIGNER_CONTRAST_FAIL', 'Fail');
