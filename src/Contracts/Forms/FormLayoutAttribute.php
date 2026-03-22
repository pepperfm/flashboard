<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

final class FormLayoutAttribute
{
    public const string BREAKPOINT_DEFAULT = 'default';
    public const string BREAKPOINT_SM = 'sm';
    public const string BREAKPOINT_MD = 'md';
    public const string BREAKPOINT_LG = 'lg';
    public const string BREAKPOINT_XL = 'xl';
    public const string BREAKPOINT_2XL = '2xl';

    public const string KEY_ALIGN = 'align';
    public const string KEY_COLUMNS = 'columns';
    public const string KEY_COLUMN_SPAN = 'column_span';
    public const string KEY_DIRECTION = 'direction';
    public const string KEY_GAP = 'gap';
    public const string KEY_LAYOUT = 'layout';
    public const string KEY_WRAP = 'wrap';
    public const string KEY_JUSTIFY = 'justify';

    public const string VALUE_FULL = 'full';

    /**
     * @var list<string>
     */
    public const array BREAKPOINTS = [
        self::BREAKPOINT_DEFAULT,
        self::BREAKPOINT_SM,
        self::BREAKPOINT_MD,
        self::BREAKPOINT_LG,
        self::BREAKPOINT_XL,
        self::BREAKPOINT_2XL,
    ];

    private function __construct()
    {
    }
}
