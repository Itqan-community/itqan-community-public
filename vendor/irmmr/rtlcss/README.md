# RTL-CSS

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg) ![License](https://img.shields.io/badge/license-MIT-green.svg) ![GitHub issues](https://img.shields.io/github/issues/irmmr/rtl-css.svg) ![GitHub stars](https://img.shields.io/github/stars/irmmr/rtl-css.svg)

RTL-CSS is a PHP module designed to automatically convert CSS styles from left-to-right (LTR) to right-to-left (RTL) direction. This is particularly useful for applications that require localization support for languages that read from right to left, such as Persian, Arabic or Hebrew.

RTL-CSS utilizes the [MyIntervals/PHP-CSS-Parser](https://github.com/MyIntervals/PHP-CSS-Parser) library for parsing CSS. This powerful library provides a robust and flexible way to work with CSS stylesheets, allowing us to efficiently analyze and manipulate CSS rules.
## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Examples](#examples)
- [Contributing](#contributing)
- [License](#license)

## Features

- Automatically converts CSS properties to RTL equivalents.
- Supports various CSS properties including `margin`, `padding`, `float`, and more.
- Easy integration with existing PHP projects.

## Installation

To install RTL-CSS, you can clone the repository or include it in your project using Composer. Here are the steps for both methods:

### Clone the Repository

```bash
git clone https://github.com/irmmr/rtl-css.git
```

### Via composer
```bash
composer require irmmr/rtlcss
```

If you're using Composer, you can add RTL-CSS as a dependency in your composer.json file:
```json
{
    "require": {
        "irmmr/rtl-css": "*"
    }
}
```

## Note
I created this project by observing https://github.com/moodlehq/rtlcss-php. One of the reasons was that this project was no longer active and needed some improvements. I tried to add new sections to it and at the same time, I aimed to make it more similar to the original project https://github.com/MohammadYounes/rtlcss/. I don't need to mention that this entire project is modeled and copied from the projects I referred to.

## Usage

To use RTL-CSS in your project, include the main PHP file and call the conversion function. Here’s a simple example:

```php
// it's very simple and I know this
$css_code = "div { float: left; }";

// parse css code
$css_parser = new Parser($css_content);

try {
    $css_tree = $css_parser->parse();
} catch (SourceException $e) {
    // Error occ
    return;
}

// create rtlcss parser
$rtlcss = new RTLParser($css_tree);

// parsing css rules and properties
$rtlcss->flip();

// get parsed css code ==> div {float: right;}
echo $css_tree->render();
```

## Directives
Directives are a set of instructions that you can use to create certain commands for the generated RTL code. For example, you can change or remove some selectors, or make it so that rtlcss does not modify them.

- `ignore`
- `remove`
- `raw`
- `rename`
- `discard`

#### Ignore
```css
/* rtl:ignore */
div {
  float: right;
  text-align: right;
  font-size: 15px;
}

/* rtl:ignore:margin-left */
a {
   margin-left: 10px;
   background-position: 10px;
}
```
```css
div {
  float: right;
  text-align: right;
  font-size: 15px;
}

a {
   margin-left: 10px;
   background-position: right 10px top 50%;
}
```


## Examples

```css
.example {
  display: inline-block;
  padding: 5px 10px 15px 20px;
  margin: 5px 10px 15px 20px;
  border-style: dotted dashed double solid;
  border-width: 1px 2px 3px 4px;
  border-color: red green blue black;
  box-shadow: -1em 0 0.4em gray, 3px 3px 30px black;
}
```
Output:
```css
.example {
  display: inline-block;
  padding: 5px 20px 15px 10px;
  margin: 5px 20px 15px 10px;
  border-style: dotted solid double dashed;
  border-width: 1px 4px 3px 2px;
  border-color: red black blue green;
  box-shadow: 1em 0 0.4em gray, -3px 3px 30px black;
}
```

## Encoder
You can use the Encoder to encode CSS codes that the CSS parser cannot analyze and parse correctly. The best current use of the Encoder is to prevent errors related to color codes and functions. The CSS parser cannot parse color function values that are entered without commas, and for this reason, it removes the corresponding rule.

```php
// using rgb(0 0 0 / 20%)
$css_code = "
div {
    float: left;
    box-shadow: 2px 2px 2px 1px rgb(0 0 0 / 20%);
}
";
...

// create rtlcss parser
$rtlcss = new RTLParser($css_tree);

// parsing css rules and properties
$rtlcss->flip();
```
Output: (missing `box-shadow`)
```css
div {float: right;}
```

Now using Encoder:
```php
// using rgb(0 0 0 / 20%)
$css_code = "
div {
    float: left;
    box-shadow: 2px 2px 2px 1px rgb(0 0 0 / 20%);
}
";

// rtl encoder
$rtl_encode = new Encode($css_code);

// change css value
$css_code = $rtl_encode->encode();

// parse css code
$css_parser = new Parser($css_content);

try {
    $css_tree = $css_parser->parse();
} catch (SourceException $e) {
    // Error occ
    return;
}

// create rtlcss parser
$rtlcss = new RTLParser($css_tree);

// parsing css rules and properties
$rtlcss->flip();

// get rendered and update encoded
$rendered = $css_tree->render();
$rtl_encode->setEncoded($rendered);

// get parsed css code
echo $rtl_encode->decode();
```
Output:
```css
div {float: right;box-shadow: -2px 2px 2px 1px rgb(0 0 0 / 20%);}
```
