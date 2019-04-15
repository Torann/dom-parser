# Simple HTML DOM Parser

[![Latest Stable Version](https://poser.pugx.org/torann/dom-parser/v/stable.png)](https://packagist.org/packages/torann/dom-parser)
[![Total Downloads](https://poser.pugx.org/torann/dom-parser/downloads.png)](https://packagist.org/packages/torann/dom-parser)
[![Patreon donate button](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://www.patreon.com/torann)
[![Donate weekly to this project using Gratipay](https://img.shields.io/badge/gratipay-donate-yellow.svg)](https://gratipay.com/~torann)
[![Donate to this project using Flattr](https://img.shields.io/badge/flattr-donate-yellow.svg)](https://flattr.com/profile/torann)
[![Donate to this project using Paypal](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4CJA2A97NPYVU)

A HTML DOM parser written in PHP7 let you manipulate HTML in a very easy way! Supports invalid HTML. Find tags on an HTML page with selectors just like jQuery. Extract contents from HTML in a single line.

## Usage

From a string:

```php
use Torann\DomParser\HtmlDom;

$dom = HtmlDom::fromString($str);

$elements = $dom->find($el_name);

```

From a file:

```php
use Torann\DomParser\HtmlDom;

$dom = HtmlDom::fromFile($file_name);

$elements = $dom->find($el_name);

```