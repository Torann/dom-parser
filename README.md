# Simple HTML DOM Parser

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