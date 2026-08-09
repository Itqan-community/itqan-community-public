<?php

namespace Irmmr\RTLCss\Directives;

use Irmmr\RTLCss\Helpers;
use Irmmr\RTLCss\Interfaces\DirectiveInterface;

/**
 * class Directive
 *
 * rtl directives, try to control some
 * rtl actions by comments
 *
 *
 * @package Irmmr\RTLCss\Directives
 */
class Directive implements DirectiveInterface
{
    /**
     * The list of all comments related to one Rule
     * @var  array
     */
    protected array $comments = [];

    /**
     * The list of all comments related to one Rule
     * @var  array
     */
    protected array $commands = [];

    /**
     * get all command results for faster
     * process after parse
     * @var  array
     */
    protected array $results = [
        'remove' => [],
        'ignore' => [],
        'raw'    => [],

        'total-ignore' => false,
        'total-remove' => false,

        'rename' => null,

        'discard' => []
    ];

    /**
     * The function is a PHP constructor.
     *
     * @param array $comments
     */
    public function __construct(array $comments)
    {
        $this->comments = $comments;
    }

    /**
     * get list of comments
     *
     * @return array
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * get list of commands
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * get list of results
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * run this process with
     * applyting all we need to our entry
     */
    public function parse(): void
    {
        foreach ($this->comments as $comment) {
            $content = $comment->getComment();

            // is comment like rtl order?
            if (!$this->isLikeCommand($content)) {
                continue;
            }

            // parse comment for getting commands
            $parse = $this->parseCommentStr($content);

            if (is_null($parse)) {
                continue;
            }

            // check if command name is valid
            if (!empty($parse['name']) && in_array($parse['name'], self::VALID_COMMANDS, true)) {
                $this->commands[] = new Command($parse['name'], $parse['value']);
            }
        }

        // parse commands after getting them
        $this->parseResult();
    }

    /**
     * check if comment content is includes a command
     *
     * @param   string $content
     * @return  bool
     */
    protected function isLikeCommand(string $content): bool
    {
        return preg_match('/' . self::START_RULE . '/', $content);
    }

    /**
     * parse comment string
     *
     * @param  string $input
     * @return array|null
     */
    protected function parseCommentStr(string $input): ?array
    {
        // created by @chatgpt
        // \s* at the beginning: Matches zero or more whitespace characters before rtl:
        // rtl:: Matches the literal string rtl:
        // (?<name>[^:]+): Captures everything up to the next colon (if present) as the name group.
        // (?::(?<value>.*+))?: Optionally matches a colon followed by any characters, capturing it as the value group.
        // \s*$: Matches zero or more whitespace characters at the end of the string.
        $pattern = '/^\s*rtl:(?<name>[^:]+)(?::(?<value>.*+))?\s*$/s';

        if (preg_match($pattern, $input, $matches)) {
            $name  = trim($matches['name'] ?? '');
            $value = $matches['value'] ?? null;

            return [
                'name'  => $name,
                'value' => $value
            ];
        }

        return null;
    }

    /**
     * parse the main results of commands
     */
    protected function parseResult(): void
    {
        foreach ($this->commands as $command) {
            $has_value = $command->hasValue();
            $value     = $command->getValue();

            if ($command->is(self::COMMAND_REMOVE)) {
                if ($has_value) {
                    $this->results['remove'] = array_merge($this->results['remove'], Helpers::splitTrim($value, ','));
                } else {
                    $this->results['total-remove'] = true;
                    break;
                }

            } else if ($command->is(self::COMMAND_IGNORE) && !$this->results['total-ignore']) {
                if ($has_value) {
                    $this->results['ignore'] = array_merge($this->results['ignore'], Helpers::splitTrim($value, ','));
                } else {
                    $this->results['total-ignore'] = true;
                }

            } else if ($command->is(self::COMMAND_RAW) && $has_value) {
                $this->results['raw'] = array_merge($this->results['raw'], Helpers::extractRules($value));

            } else if ($command->is(self::COMMAND_RENAME) && $has_value) {
                $this->results['rename'] = $value;

            } else if ($command->is(self::COMMAND_DISCARD) && $has_value) {
                $this->results['discard'] = array_merge($this->results['discard'], Helpers::splitTrim($value, ','));

            }
        }
    }
}