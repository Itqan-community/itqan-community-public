<?php

namespace Irmmr\RTLCss\Interfaces;

/**
 * interface DirectiveInterface
 *
 * @package Irmmr\RTLCss\Interfaces
 */
interface DirectiveInterface
{
    // start rule regexp for detect rtl
    public const START_RULE = '^(\s|\*)*!?rtl:';

    // valid commands
    public const VALID_COMMANDS = [
        'ignore',
        'remove',
        'raw',
        'rename',
        'discard'
    ];

    // commands name
    public const COMMAND_IGNORE     = 'ignore';
    public const COMMAND_REMOVE     = 'remove';
    public const COMMAND_RAW        = 'raw';
    public const COMMAND_RENAME     = 'rename';
    public const COMMAND_DISCARD    = 'discard';
}