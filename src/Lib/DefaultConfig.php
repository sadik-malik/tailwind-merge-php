<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * Default configuration for tailwind-merge.
 * PHP port of tailwind-merge default-config.ts
 *
 * Supports Tailwind CSS v3 classes.
 */
class DefaultConfig
{
    public static function get(): array
    {
        // Validator shorthands
        $isAny             = Validators::isAny(...);
        $isAnyNonArb       = Validators::isAnyNonArbitrary(...);
        $isArb             = Validators::isArbitraryValue(...);
        $isArbVar          = Validators::isArbitraryVariable(...);
        $isArbLen          = Validators::isArbitraryLength(...);
        $isArbVarLen       = Validators::isArbitraryVariableLength(...);
        $isInt             = Validators::isInteger(...);
        $isArbNum          = Validators::isArbitraryNumber(...);
        $isArbVarNum       = Validators::isArbitraryVariableNumber(...);
        $isNum             = Validators::isNumber(...);
        $isPct             = Validators::isPercent(...);
        $isArbImg          = Validators::isArbitraryImage(...);
        $isArbVarImg       = Validators::isArbitraryVariableImage(...);
        $isArbSize         = Validators::isArbitrarySize(...);
        $isArbVarSize      = Validators::isArbitraryVariableSize(...);
        $isArbPos          = Validators::isArbitraryPosition(...);
        $isArbVarPos       = Validators::isArbitraryVariablePosition(...);
        $isArbShadow       = Validators::isArbitraryShadow(...);
        $isArbVarShadow    = Validators::isArbitraryVariableShadow(...);
        $isFrac            = Validators::isFraction(...);

        // Theme scale helpers (returns validator that checks theme values)
        $colors    = [$isAny];
        $spacing   = [$isArbLen, $isArbVarLen, $isArbVar, $isNum, $isFrac];
        $blur      = ['none', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '', $isArbLen, $isArbVar];
        $brightness = [$isNum, $isArbNum, $isArbVarNum, $isArbVar];
        $borderRadius = ['none', 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '', 'full', $isArbLen, $isArbVar];
        $borderWidth = ['', $isNum, $isArbLen, $isArbVarLen];
        $inset = ['auto', $isFrac, 'full', $isArbLen, $isArbVarLen, $isArbVar, $isNum];
        $scale = [$isNum, $isArbNum, $isArbVarNum, $isArbVar];
        $skew  = [$isNum, $isArbNum, $isArbVarNum, $isArbVar];
        $rotate = [$isNum, $isArbNum, $isArbVarNum, $isArbVar];
        $translate = [$isFrac, 'full', 'auto', $isArbLen, $isArbVarLen, $isArbVar, $isNum];
        $gradientColorStops = $colors;
        $gradientColorStopPositions = [$isPct, $isArbLen, $isArbVar];

        $overflowValues    = ['auto', 'hidden', 'clip', 'visible', 'scroll'];
        $overscrollValues  = ['auto', 'contain', 'none'];
        $lineStyles        = ['solid', 'dashed', 'dotted', 'double', 'none'];
        $blendModes        = [
            'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten',
            'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference',
            'exclusion', 'hue', 'saturation', 'color', 'luminosity',
        ];
        $textAligns = ['left', 'center', 'right', 'justify', 'start', 'end'];
        $verticalAligns = ['baseline', 'top', 'middle', 'bottom', 'text-top', 'text-bottom', 'sub', 'super', $isArb, $isArbVar];
        $positionValues = ['bottom', 'center', 'left', 'left-bottom', 'left-top', 'right', 'right-bottom', 'right-top', 'top'];

        return [
            'cacheSize' => 500,
            'prefix'    => '',   // e.g. 'tw-' for a Tailwind prefix config
            'theme' => [],
            'classGroups' => [
                // -------------------------------------------------------
                // Layout
                // -------------------------------------------------------
                'aspect' => [['aspect' => ['auto', 'square', 'video', $isFrac, $isArb, $isArbVar]]],
                'container' => [['container' => ['']]],
                'columns' => [['columns' => [$isNum, $isArbNum, $isArbVarNum, $isArbLen, $isArbVar, 'auto', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']]],
                'break-after' => [['break-after' => ['auto', 'avoid', 'all', 'avoid-page', 'page', 'left', 'right', 'column']]],
                'break-before' => [['break-before' => ['auto', 'avoid', 'all', 'avoid-page', 'page', 'left', 'right', 'column']]],
                'break-inside' => [['break-inside' => ['auto', 'avoid', 'avoid-page', 'avoid-column']]],
                'box-decoration' => [['box-decoration' => ['slice', 'clone']]],
                'box' => [['box' => ['border', 'content']]],
                'display' => [
                    'block', 'inline-block', 'inline', 'flex', 'inline-flex', 'table', 'inline-table',
                    'table-caption', 'table-cell', 'table-column', 'table-column-group', 'table-footer-group',
                    'table-header-group', 'table-row-group', 'table-row', 'flow-root', 'grid', 'inline-grid',
                    'contents', 'list-item', 'hidden',
                ],
                'sr' => ['sr-only', 'not-sr-only'],
                'float' => [['float' => ['right', 'left', 'none', 'start', 'end']]],
                'clear' => [['clear' => ['left', 'right', 'both', 'none', 'start', 'end']]],
                'isolation' => ['isolate', 'isolation-auto'],
                'object-fit' => [['object' => ['contain', 'cover', 'fill', 'none', 'scale-down']]],
                'object-position' => [['object' => [...$positionValues, $isArb, $isArbVar]]],
                'overflow' => [['overflow' => $overflowValues]],
                'overflow-x' => [['overflow-x' => $overflowValues]],
                'overflow-y' => [['overflow-y' => $overflowValues]],
                'overscroll' => [['overscroll' => $overscrollValues]],
                'overscroll-x' => [['overscroll-x' => $overscrollValues]],
                'overscroll-y' => [['overscroll-y' => $overscrollValues]],
                'position' => ['static', 'fixed', 'absolute', 'relative', 'sticky'],
                'inset' => [['inset' => $inset]],
                'inset-x' => [['inset-x' => $inset]],
                'inset-y' => [['inset-y' => $inset]],
                'start' => [['start' => $inset]],
                'end' => [['end' => $inset]],
                'top' => [['top' => $inset]],
                'right' => [['right' => $inset]],
                'bottom' => [['bottom' => $inset]],
                'left' => [['left' => $inset]],
                'visibility' => ['visible', 'invisible', 'collapse'],
                'z' => [['z' => ['auto', $isInt, $isArbNum, $isArbVar]]],

                // -------------------------------------------------------
                // Flexbox & Grid
                // -------------------------------------------------------
                'basis' => [['basis' => ['auto', $isFrac, 'full', $isArbLen, $isArbVarLen, $isNum]]],
                'flex-direction' => [['flex' => ['row', 'row-reverse', 'col', 'col-reverse']]],
                'flex-wrap' => [['flex' => ['wrap', 'wrap-reverse', 'nowrap']]],
                'flex' => [['flex' => ['1', 'auto', 'initial', 'none', $isNum, $isArbNum, $isArbVar, $isArb]]],
                'grow' => [['grow' => ['', $isNum, $isArbNum, $isArbVar]]],
                'shrink' => [['shrink' => ['', $isNum, $isArbNum, $isArbVar]]],
                'order' => [['order' => ['first', 'last', 'none', $isInt, $isArbNum, $isArbVar]]],
                'grid-cols' => [['grid-cols' => ['none', 'subgrid', $isInt, $isArbNum, $isArbVar, $isArb]]],
                'col-start-end' => [['col' => ['auto', ['span' => [$isInt, 'full', $isArbNum, $isArbVar]], $isArb, $isArbVar]]],
                'col-start' => [['col-start' => ['auto', $isInt, $isArbNum, $isArbVar]]],
                'col-end' => [['col-end' => ['auto', $isInt, $isArbNum, $isArbVar]]],
                'grid-rows' => [['grid-rows' => ['none', 'subgrid', $isInt, $isArbNum, $isArbVar, $isArb]]],
                'row-start-end' => [['row' => ['auto', ['span' => [$isInt, $isArbNum, $isArbVar]], $isArb, $isArbVar]]],
                'row-start' => [['row-start' => ['auto', $isInt, $isArbNum, $isArbVar]]],
                'row-end' => [['row-end' => ['auto', $isInt, $isArbNum, $isArbVar]]],
                'grid-flow' => [['grid-flow' => ['row', 'col', 'dense', 'row-dense', 'col-dense']]],
                'auto-cols' => [['auto-cols' => ['auto', 'min', 'max', 'fr', $isArb, $isArbVar]]],
                'auto-rows' => [['auto-rows' => ['auto', 'min', 'max', 'fr', $isArb, $isArbVar]]],
                'gap' => [['gap' => $spacing]],
                'gap-x' => [['gap-x' => $spacing]],
                'gap-y' => [['gap-y' => $spacing]],
                'justify-content' => [['justify' => ['normal', 'start', 'end', 'center', 'between', 'around', 'evenly', 'stretch']]],
                'justify-items' => [['justify-items' => ['start', 'end', 'center', 'stretch']]],
                'justify-self' => [['justify-self' => ['auto', 'start', 'end', 'center', 'stretch']]],
                'align-content' => [['content' => ['normal', 'center', 'start', 'end', 'between', 'around', 'evenly', 'baseline', 'stretch']]],
                'align-items' => [['items' => ['start', 'end', 'center', 'baseline', 'stretch']]],
                'align-self' => [['self' => ['auto', 'start', 'end', 'center', 'stretch', 'baseline']]],
                'place-content' => [['place-content' => ['center', 'start', 'end', 'between', 'around', 'evenly', 'baseline', 'stretch']]],
                'place-items' => [['place-items' => ['start', 'end', 'center', 'baseline', 'stretch']]],
                'place-self' => [['place-self' => ['auto', 'start', 'end', 'center', 'stretch']]],

                // -------------------------------------------------------
                // Spacing
                // -------------------------------------------------------
                'p' => [['p' => $spacing]],
                'px' => [['px' => $spacing]],
                'py' => [['py' => $spacing]],
                'ps' => [['ps' => $spacing]],
                'pe' => [['pe' => $spacing]],
                'pt' => [['pt' => $spacing]],
                'pr' => [['pr' => $spacing]],
                'pb' => [['pb' => $spacing]],
                'pl' => [['pl' => $spacing]],
                'm' => [['m' => ['auto', ...$spacing]]],
                'mx' => [['mx' => ['auto', ...$spacing]]],
                'my' => [['my' => ['auto', ...$spacing]]],
                'ms' => [['ms' => ['auto', ...$spacing]]],
                'me' => [['me' => ['auto', ...$spacing]]],
                'mt' => [['mt' => ['auto', ...$spacing]]],
                'mr' => [['mr' => ['auto', ...$spacing]]],
                'mb' => [['mb' => ['auto', ...$spacing]]],
                'ml' => [['ml' => ['auto', ...$spacing]]],
                'space-x' => [['space-x' => $spacing]],
                'space-x-reverse' => ['space-x-reverse'],
                'space-y' => [['space-y' => $spacing]],
                'space-y-reverse' => ['space-y-reverse'],

                // -------------------------------------------------------
                // Sizing
                // -------------------------------------------------------
                'w' => [['w' => ['auto', 'min', 'max', 'fit', 'svw', 'lvw', 'dvw', $isFrac, 'full', 'screen', $isArbLen, $isArbVarLen, $isNum]]],
                'min-w' => [['min-w' => ['min', 'max', 'fit', 'full', $isArbLen, $isArbVarLen, $isNum]]],
                'max-w' => [['max-w' => ['none', 'full', 'min', 'max', 'fit', 'prose', $isArbLen, $isArbVarLen, $isNum, ['screen' => $isAnyNonArb]]]],
                'h' => [['h' => ['auto', $isFrac, 'full', 'screen', 'svh', 'lvh', 'dvh', 'min', 'max', 'fit', $isArbLen, $isArbVarLen, $isNum]]],
                'min-h' => [['min-h' => ['min', 'max', 'fit', 'full', 'screen', 'svh', 'lvh', 'dvh', $isArbLen, $isArbVarLen, $isNum]]],
                'max-h' => [['max-h' => ['none', $isFrac, 'full', 'screen', 'svh', 'lvh', 'dvh', 'min', 'max', 'fit', $isArbLen, $isArbVarLen, $isNum]]],
                'size' => [['size' => ['auto', $isFrac, 'full', 'min', 'max', 'fit', $isArbLen, $isArbVarLen, $isNum]]],

                // -------------------------------------------------------
                // Typography
                // -------------------------------------------------------
                'font-size' => [['text' => ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl', '8xl', '9xl', $isArbLen, $isArbVarLen]]],
                'font-smoothing' => ['antialiased', 'subpixel-antialiased'],
                'font-style' => ['italic', 'not-italic'],
                // font-weight must come before font-family so named weights are resolved first
                'font-weight' => [['font' => ['thin', 'extralight', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold', 'black', $isArbNum, $isArbVarNum]]],
                'font-stretch' => [['font-stretch' => ['ultra-condensed', 'extra-condensed', 'condensed', 'semi-condensed', 'normal', 'semi-expanded', 'expanded', 'extra-expanded', 'ultra-expanded', $isPct, $isArb, $isArbVar]]],
                // font-family: named families first, then arbitrary (isAny is last resort)
                'font-family' => [['font' => ['sans', 'serif', 'mono', $isArb, $isArbVar]]],
                'fvn-normal' => ['normal-nums'],
                'fvn-ordinal' => ['ordinal'],
                'fvn-slashed-zero' => ['slashed-zero'],
                'fvn-figure' => ['lining-nums', 'oldstyle-nums'],
                'fvn-spacing' => ['proportional-nums', 'tabular-nums'],
                'fvn-fraction' => ['diagonal-fractions', 'stacked-fractions'],
                'tracking' => [['tracking' => ['tighter', 'tight', 'normal', 'wide', 'wider', 'widest', $isArb, $isArbVar]]],
                'line-clamp' => [['line-clamp' => ['none', $isInt, $isArbNum, $isArbVar]]],
                'leading' => [['leading' => ['none', 'tight', 'snug', 'normal', 'relaxed', 'loose', $isNum, $isArbLen, $isArbVarLen]]],
                'list-image' => [['list-image' => ['none', $isArb, $isArbVar]]],
                'list-style-type' => [['list' => ['none', 'disc', 'decimal', $isArb, $isArbVar]]],
                'list-style-position' => [['list' => ['inside', 'outside']]],
                'placeholder-color' => [['placeholder' => $colors]],
                'placeholder-opacity' => [['placeholder-opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'text-alignment' => [['text' => $textAligns]],
                'text-color' => [['text' => $colors]],
                'text-opacity' => [['text-opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'text-decoration' => ['underline', 'overline', 'line-through', 'no-underline'],
                'text-decoration-style' => [['decoration' => [...$lineStyles, 'wavy']]],
                'text-decoration-thickness' => [['decoration' => ['auto', 'from-font', $isNum, $isArbLen, $isArbVar]]],
                'text-decoration-color' => [['decoration' => $colors]],
                'underline-offset' => [['underline-offset' => ['auto', $isNum, $isArbLen, $isArbVar]]],
                'text-transform' => ['uppercase', 'lowercase', 'capitalize', 'normal-case'],
                // truncate is bare; text-ellipsis and text-clip use 'text-' prefix
                'text-overflow' => ['truncate', [['text' => ['ellipsis', 'clip']]]],
                'text-wrap' => [['text' => ['wrap', 'nowrap', 'balance', 'pretty']]],
                'indent' => [['indent' => $spacing]],
                'vertical-align' => [['align' => $verticalAligns]],
                'whitespace' => [['whitespace' => ['normal', 'nowrap', 'pre', 'pre-line', 'pre-wrap', 'break-spaces']]],
                'break' => [['break' => ['normal', 'words', 'all', 'keep']]],
                'hyphens' => [['hyphens' => ['none', 'manual', 'auto']]],
                'content' => [['content' => ['none', $isArb, $isArbVar]]],

                // -------------------------------------------------------
                // Backgrounds
                // -------------------------------------------------------
                'bg-attachment' => [['bg' => ['fixed', 'local', 'scroll']]],
                'bg-clip' => [['bg-clip' => ['border', 'padding', 'content', 'text']]],
                'bg-opacity' => [['bg-opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'bg-origin' => [['bg-origin' => ['border', 'padding', 'content']]],
                'bg-position' => [['bg' => [...$positionValues, $isArbPos, $isArbVarPos]]],
                'bg-repeat' => [['bg' => ['no-repeat', ['repeat' => ['', 'x', 'y', 'round', 'space']]]]],
                'bg-size' => [['bg' => ['auto', 'cover', 'contain', $isArbSize, $isArbVarSize]]],
                'bg-image' => [['bg' => ['none', ['gradient-to' => ['t', 'tr', 'r', 'br', 'b', 'bl', 'l', 'tl']], $isArbImg, $isArbVarImg]]],
                'bg-color' => [['bg' => $colors]],
                'gradient-from-pos' => [['from' => $gradientColorStopPositions]],
                'gradient-via-pos' => [['via' => $gradientColorStopPositions]],
                'gradient-to-pos' => [['to' => $gradientColorStopPositions]],
                'gradient-from' => [['from' => $gradientColorStops]],
                'gradient-via' => [['via' => $gradientColorStops]],
                'gradient-to' => [['to' => $gradientColorStops]],

                // -------------------------------------------------------
                // Borders
                // -------------------------------------------------------
                'rounded' => [['rounded' => $borderRadius]],
                'rounded-s' => [['rounded-s' => $borderRadius]],
                'rounded-e' => [['rounded-e' => $borderRadius]],
                'rounded-t' => [['rounded-t' => $borderRadius]],
                'rounded-r' => [['rounded-r' => $borderRadius]],
                'rounded-b' => [['rounded-b' => $borderRadius]],
                'rounded-l' => [['rounded-l' => $borderRadius]],
                'rounded-ss' => [['rounded-ss' => $borderRadius]],
                'rounded-se' => [['rounded-se' => $borderRadius]],
                'rounded-ee' => [['rounded-ee' => $borderRadius]],
                'rounded-es' => [['rounded-es' => $borderRadius]],
                'rounded-tl' => [['rounded-tl' => $borderRadius]],
                'rounded-tr' => [['rounded-tr' => $borderRadius]],
                'rounded-br' => [['rounded-br' => $borderRadius]],
                'rounded-bl' => [['rounded-bl' => $borderRadius]],
                'border-w' => [['border' => $borderWidth]],
                'border-w-x' => [['border-x' => $borderWidth]],
                'border-w-y' => [['border-y' => $borderWidth]],
                'border-w-s' => [['border-s' => $borderWidth]],
                'border-w-e' => [['border-e' => $borderWidth]],
                'border-w-t' => [['border-t' => $borderWidth]],
                'border-w-r' => [['border-r' => $borderWidth]],
                'border-w-b' => [['border-b' => $borderWidth]],
                'border-w-l' => [['border-l' => $borderWidth]],
                'border-opacity' => [['border-opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'border-style' => [['border' => [...$lineStyles, 'hidden']]],
                'divide-x' => [['divide-x' => $borderWidth]],
                'divide-x-reverse' => ['divide-x-reverse'],
                'divide-y' => [['divide-y' => $borderWidth]],
                'divide-y-reverse' => ['divide-y-reverse'],
                'divide-opacity' => [['divide-opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'divide-style' => [['divide' => $lineStyles]],
                'border-color' => [['border' => $colors]],
                'border-color-x' => [['border-x' => $colors]],
                'border-color-y' => [['border-y' => $colors]],
                'border-color-s' => [['border-s' => $colors]],
                'border-color-e' => [['border-e' => $colors]],
                'border-color-t' => [['border-t' => $colors]],
                'border-color-r' => [['border-r' => $colors]],
                'border-color-b' => [['border-b' => $colors]],
                'border-color-l' => [['border-l' => $colors]],
                'divide-color' => [['divide' => $colors]],
                'outline-w' => [['outline' => ['', $isNum, $isArbLen, $isArbVarLen]]],
                'outline-offset' => [['outline-offset' => [$isNum, $isArbLen, $isArbVarLen]]],
                'outline-color' => [['outline' => $colors]],
                'outline-style' => [['outline' => ['none', ...$lineStyles, 'dashed']]],
                'ring-w' => [['ring' => $borderWidth]],
                'ring-w-inset' => ['ring-inset'],
                'ring-color' => [['ring' => $colors]],
                'ring-opacity' => [['ring-opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'ring-offset-w' => [['ring-offset' => [$isNum, $isArbLen, $isArbVarLen]]],
                'ring-offset-color' => [['ring-offset' => $colors]],

                // -------------------------------------------------------
                // Effects
                // -------------------------------------------------------
                'shadow' => [['shadow' => ['', 'inner', 'none', 'sm', 'md', 'lg', 'xl', '2xl', $isArbShadow, $isArbVarShadow]]],
                'shadow-color' => [['shadow' => $colors]],
                'opacity' => [['opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'mix-blend' => [['mix-blend' => [...$blendModes, 'plus-darker', 'plus-lighter']]],
                'bg-blend' => [['bg-blend' => $blendModes]],

                // -------------------------------------------------------
                // Filters
                // -------------------------------------------------------
                'filter' => [['filter' => ['', 'none']]],
                'blur' => [['blur' => $blur]],
                'brightness' => [['brightness' => $brightness]],
                'contrast' => [['contrast' => [$isNum, $isArbNum, $isArbVarNum]]],
                'drop-shadow' => [['drop-shadow' => ['', 'none', 'sm', 'md', 'lg', 'xl', '2xl', $isArbShadow, $isArbVar]]],
                'grayscale' => [['grayscale' => ['', $isNum, $isArbNum, $isArbVar]]],
                'hue-rotate' => [['hue-rotate' => [$isNum, $isArbNum, $isArbVar]]],
                'invert' => [['invert' => ['', $isNum, $isArbNum, $isArbVar]]],
                'saturate' => [['saturate' => [$isNum, $isArbNum, $isArbVar]]],
                'sepia' => [['sepia' => ['', $isNum, $isArbNum, $isArbVar]]],
                'backdrop-filter' => [['backdrop-filter' => ['', 'none']]],
                'backdrop-blur' => [['backdrop-blur' => $blur]],
                'backdrop-brightness' => [['backdrop-brightness' => $brightness]],
                'backdrop-contrast' => [['backdrop-contrast' => [$isNum, $isArbNum, $isArbVarNum]]],
                'backdrop-grayscale' => [['backdrop-grayscale' => ['', $isNum, $isArbNum, $isArbVar]]],
                'backdrop-hue-rotate' => [['backdrop-hue-rotate' => [$isNum, $isArbNum, $isArbVar]]],
                'backdrop-invert' => [['backdrop-invert' => ['', $isNum, $isArbNum, $isArbVar]]],
                'backdrop-opacity' => [['backdrop-opacity' => [$isNum, $isArbNum, $isArbVar]]],
                'backdrop-saturate' => [['backdrop-saturate' => [$isNum, $isArbNum, $isArbVar]]],
                'backdrop-sepia' => [['backdrop-sepia' => ['', $isNum, $isArbNum, $isArbVar]]],

                // -------------------------------------------------------
                // Tables
                // -------------------------------------------------------
                'border-collapse' => [['border' => ['collapse', 'separate']]],
                'border-spacing' => [['border-spacing' => $spacing]],
                'border-spacing-x' => [['border-spacing-x' => $spacing]],
                'border-spacing-y' => [['border-spacing-y' => $spacing]],
                'table-layout' => [['table' => ['auto', 'fixed']]],
                'caption' => [['caption' => ['top', 'bottom']]],

                // -------------------------------------------------------
                // Transitions & Animation
                // -------------------------------------------------------
                'transition' => [['transition' => ['none', 'all', '', 'colors', 'opacity', 'shadow', 'transform', $isArb, $isArbVar]]],
                'duration' => [['duration' => [$isNum, $isArbNum, $isArbVar]]],
                'ease' => [['ease' => ['linear', 'in', 'out', 'in-out', $isArb, $isArbVar]]],
                'delay' => [['delay' => [$isNum, $isArbNum, $isArbVar]]],
                'animate' => [['animate' => ['none', 'spin', 'ping', 'pulse', 'bounce', $isArb, $isArbVar]]],

                // -------------------------------------------------------
                // Transforms
                // -------------------------------------------------------
                'transform' => [['transform' => ['', 'gpu', 'none']]],
                'scale' => [['scale' => $scale]],
                'scale-x' => [['scale-x' => $scale]],
                'scale-y' => [['scale-y' => $scale]],
                'rotate' => [['rotate' => $rotate]],
                'translate-x' => [['translate-x' => $translate]],
                'translate-y' => [['translate-y' => $translate]],
                'skew-x' => [['skew-x' => $skew]],
                'skew-y' => [['skew-y' => $skew]],
                'transform-origin' => [['origin' => ['center', 'top', 'top-right', 'right', 'bottom-right', 'bottom', 'bottom-left', 'left', 'top-left', $isArb, $isArbVar]]],

                // -------------------------------------------------------
                // Interactivity
                // -------------------------------------------------------
                'accent' => [['accent' => ['auto', ...$colors]]],
                'appearance' => [['appearance' => ['none', 'auto']]],
                'cursor' => [['cursor' => ['auto', 'default', 'pointer', 'wait', 'text', 'move', 'help', 'not-allowed', 'none', 'context-menu', 'progress', 'cell', 'crosshair', 'vertical-text', 'alias', 'copy', 'no-drop', 'grab', 'grabbing', 'all-scroll', 'col-resize', 'row-resize', 'n-resize', 'e-resize', 's-resize', 'w-resize', 'ne-resize', 'nw-resize', 'se-resize', 'sw-resize', 'ew-resize', 'ns-resize', 'nesw-resize', 'nwse-resize', 'zoom-in', 'zoom-out', $isArb, $isArbVar]]],
                'caret-color' => [['caret' => $colors]],
                'pointer-events' => [['pointer-events' => ['none', 'auto']]],
                'resize' => [['resize' => ['none', 'y', 'x', '']]],
                'scroll-behavior' => [['scroll' => ['auto', 'smooth']]],
                'scroll-m' => [['scroll-m' => $spacing]],
                'scroll-mx' => [['scroll-mx' => $spacing]],
                'scroll-my' => [['scroll-my' => $spacing]],
                'scroll-ms' => [['scroll-ms' => $spacing]],
                'scroll-me' => [['scroll-me' => $spacing]],
                'scroll-mt' => [['scroll-mt' => $spacing]],
                'scroll-mr' => [['scroll-mr' => $spacing]],
                'scroll-mb' => [['scroll-mb' => $spacing]],
                'scroll-ml' => [['scroll-ml' => $spacing]],
                'scroll-p' => [['scroll-p' => $spacing]],
                'scroll-px' => [['scroll-px' => $spacing]],
                'scroll-py' => [['scroll-py' => $spacing]],
                'scroll-ps' => [['scroll-ps' => $spacing]],
                'scroll-pe' => [['scroll-pe' => $spacing]],
                'scroll-pt' => [['scroll-pt' => $spacing]],
                'scroll-pr' => [['scroll-pr' => $spacing]],
                'scroll-pb' => [['scroll-pb' => $spacing]],
                'scroll-pl' => [['scroll-pl' => $spacing]],
                'snap-align' => [['snap' => ['start', 'end', 'center', 'align-none']]],
                'snap-stop' => [['snap' => ['normal', 'always']]],
                'snap-type' => [['snap' => ['none', 'x', 'y', 'both']]],
                'snap-strictness' => [['snap' => ['mandatory', 'proximity']]],
                'touch' => [['touch' => ['auto', 'none', 'manipulation']]],
                'touch-x' => [['touch' => ['pan-x', 'pan-left', 'pan-right']]],
                'touch-y' => [['touch' => ['pan-y', 'pan-up', 'pan-down']]],
                'touch-pz' => [['touch' => ['pinch-zoom']]],
                'select' => [['select' => ['none', 'text', 'all', 'auto']]],
                'will-change' => [['will-change' => ['auto', 'scroll', 'contents', 'transform', $isArb, $isArbVar]]],

                // -------------------------------------------------------
                // SVG
                // -------------------------------------------------------
                'fill' => [['fill' => ['none', ...$colors]]],
                'stroke-w' => [['stroke' => [$isNum, $isArbLen, $isArbVar]]],
                'stroke' => [['stroke' => ['none', ...$colors]]],

                // -------------------------------------------------------
                // Accessibility
                // -------------------------------------------------------
                'forced-color-adjust' => [['forced-color-adjust' => ['auto', 'none']]],
            ],

            // ---------------------------------------------------------------
            // Conflicting class groups
            // ---------------------------------------------------------------
            'conflictingClassGroups' => [
                'overflow'    => ['overflow-x', 'overflow-y'],
                'overscroll'  => ['overscroll-x', 'overscroll-y'],
                'inset'       => ['inset-x', 'inset-y', 'start', 'end', 'top', 'right', 'bottom', 'left'],
                'inset-x'     => ['right', 'left'],
                'inset-y'     => ['top', 'bottom'],
                'flex'        => ['basis', 'grow', 'shrink'],
                'gap'         => ['gap-x', 'gap-y'],
                'p'           => ['px', 'py', 'ps', 'pe', 'pt', 'pr', 'pb', 'pl'],
                'px'          => ['pr', 'pl'],
                'py'          => ['pt', 'pb'],
                'm'           => ['mx', 'my', 'ms', 'me', 'mt', 'mr', 'mb', 'ml'],
                'mx'          => ['mr', 'ml'],
                'my'          => ['mt', 'mb'],
                'size'        => ['w', 'h'],
                'font-size'   => ['leading'],
                'fvn-normal'  => ['fvn-ordinal', 'fvn-slashed-zero', 'fvn-figure', 'fvn-spacing', 'fvn-fraction'],
                'fvn-ordinal' => ['fvn-normal'],
                'fvn-slashed-zero' => ['fvn-normal'],
                'fvn-figure'  => ['fvn-normal'],
                'fvn-spacing' => ['fvn-normal'],
                'fvn-fraction' => ['fvn-normal'],
                'line-clamp'  => ['overflow', 'display'],
                'rounded'     => ['rounded-s', 'rounded-e', 'rounded-t', 'rounded-r', 'rounded-b', 'rounded-l', 'rounded-ss', 'rounded-se', 'rounded-ee', 'rounded-es', 'rounded-tl', 'rounded-tr', 'rounded-br', 'rounded-bl'],
                'rounded-s'   => ['rounded-ss', 'rounded-es'],
                'rounded-e'   => ['rounded-se', 'rounded-ee'],
                'rounded-t'   => ['rounded-tl', 'rounded-tr'],
                'rounded-r'   => ['rounded-tr', 'rounded-br'],
                'rounded-b'   => ['rounded-br', 'rounded-bl'],
                'rounded-l'   => ['rounded-tl', 'rounded-bl'],
                'border-w'    => ['border-w-s', 'border-w-e', 'border-w-t', 'border-w-r', 'border-w-b', 'border-w-l', 'border-w-x', 'border-w-y'],
                'border-w-x'  => ['border-w-r', 'border-w-l'],
                'border-w-y'  => ['border-w-t', 'border-w-b'],
                'border-color' => ['border-color-t', 'border-color-r', 'border-color-b', 'border-color-l', 'border-color-x', 'border-color-y', 'border-color-s', 'border-color-e'],
                'border-color-x' => ['border-color-r', 'border-color-l'],
                'border-color-y' => ['border-color-t', 'border-color-b'],
                'scroll-m'    => ['scroll-mx', 'scroll-my', 'scroll-ms', 'scroll-me', 'scroll-mt', 'scroll-mr', 'scroll-mb', 'scroll-ml'],
                'scroll-mx'   => ['scroll-mr', 'scroll-ml'],
                'scroll-my'   => ['scroll-mt', 'scroll-mb'],
                'scroll-p'    => ['scroll-px', 'scroll-py', 'scroll-ps', 'scroll-pe', 'scroll-pt', 'scroll-pr', 'scroll-pb', 'scroll-pl'],
                'scroll-px'   => ['scroll-pr', 'scroll-pl'],
                'scroll-py'   => ['scroll-pt', 'scroll-pb'],
                'border-spacing' => ['border-spacing-x', 'border-spacing-y'],
                'touch'       => ['touch-x', 'touch-y', 'touch-pz'],
                'font-weight' => [],
                'scale'       => ['scale-x', 'scale-y'],
                'filter'      => ['blur', 'brightness', 'contrast', 'drop-shadow', 'grayscale', 'hue-rotate', 'invert', 'saturate', 'sepia'],
                'backdrop-filter' => ['backdrop-blur', 'backdrop-brightness', 'backdrop-contrast', 'backdrop-grayscale', 'backdrop-hue-rotate', 'backdrop-invert', 'backdrop-opacity', 'backdrop-saturate', 'backdrop-sepia'],
            ],

            'conflictingClassGroupModifiers' => [
                'font-size' => ['leading'],
            ],
        ];
    }
}
