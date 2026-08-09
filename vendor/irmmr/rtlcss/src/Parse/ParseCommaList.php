<?php

namespace Irmmr\RTLCss\Parse;

use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Parsing\ParserState;
use Sabberworm\CSS\Parsing\SourceException;
use Sabberworm\CSS\Parsing\UnexpectedEOFException;
use Sabberworm\CSS\Parsing\UnexpectedTokenException;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Value;

/**
 * class ParseCommaList
 *
 * trying to parse comma list like: 12px 20px, 30px 20px right
 *
 * @package Irmmr\RTLCss\Parse
 */
class ParseCommaList
{
    /**
     * a list seprated by comma ","
     * @var RuleValueList
     */
    protected RuleValueList $list;

    /**
     * any text to parse
     * @var string
     */
    protected string $text;

    /**
     * class constructor.
     */
    public function __construct(string $text)
    {
        $this->text  = $text;
        $this->list  = new RuleValueList(',');
    }

    /**
     * parse single value for each entry
     *
     * @param  string $value
     * @return mixed
     */
    protected function parseSingleValue(string $value)
    {
        $settings = Settings::create();
        $parse    = new ParserState($value, $settings);

        try {
            return Value::parseValue($parse);
        } catch (UnexpectedEOFException|UnexpectedTokenException $e) {
            return null;
        }
    }

    /**
     * a function to split multi backgrounds
     * by @chatgpt
     *
     * @param   string $input
     * @return  array
     */
    protected function splitCssValues(string $input): array
    {
        preg_match('/\s*([^;]+)/', $input, $matches);

        if (empty($matches)) {
            return [];
        }

        $backgroundString = $matches[1];

        $backgrounds    = [];
        $level          = 0;
        $current        = '';

        for ($i = 0; $i < strlen($backgroundString); $i++) {
            $char = $backgroundString[$i];

            if ($char === '(') {
                $level ++;
            } elseif ($char === ')') {
                $level --;
            } elseif ($char === ',' && $level === 0) {
                $backgrounds[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (!empty($current)) {
            $backgrounds[] = trim($current);
        }

        return $backgrounds;
    }

    /**
     * parse values and list
     */
    public function parse(): void
    {
        $exp_comma = $this->splitCssValues($this->text);

        foreach ($exp_comma as $i) {
            $i_code  = '.wapper { box-shadow: ' . $i  . '; }';
            $parse_i = new Parser($i_code);

            try {
                $parse_tree = $parse_i->parse();
            } catch (SourceException $e) {
                continue;
            }

            if ($parse_tree->getContents()[0]) {
                $content = $parse_tree->getContents()[0];

                if (isset($content->getRules()[0])) {
                    $rule_value = $parse_tree->getContents()[0]->getRules()[0]->getValue() ?? '';

                    $this->list->addListComponent($rule_value);
                }
            }
        }
    }

    /**
     * get parsed list
     *
     * @return RuleValueList
     */
    public function getList(): RuleValueList
    {
        return $this->list;
    }
}