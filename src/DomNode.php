<?php

namespace Torann\DomParser;

/**
 * simple html dom node
 * PaperG - added ability for "find" routine to lowercase the value of the selector.
 * PaperG - added $tag_start to track the start position of the tag in the total byte index
 *
 * @package PlaceLocalInclude
 */
class DomNode
{
    public $nodetype = HtmlDom::HDOM_TYPE_TEXT;
    public $tag = 'text';
    public $attr = [];

    /** @var DomNode[] $children */
    public $children = [];
    public $nodes = [];
    public $parent = null;

    // The "info" array - see HtmlDom::HDOM_INFO_... for what each element contains.
    public $_ = [];
    public $tag_start = 0;
    private $dom = null;

    public function __construct(HtmlDom $dom)
    {
        $this->dom = $dom;
        $dom->nodes[] = $this;
    }

    public function __destruct()
    {
        $this->clear();
    }

    public function __toString()
    {
        return $this->outerText();
    }

    // clean up memory due to php5 circular references memory leak...
    public function clear()
    {
        $this->dom = null;
        $this->nodes = null;
        $this->parent = null;
        $this->children = null;
    }

    // dump node's tree
    public function dump($show_attr = true, $deep = 0)
    {
        $lead = str_repeat('    ', $deep);

        echo $lead . $this->tag;
        if ($show_attr && count($this->attr) > 0) {
            echo '(';
            foreach ($this->attr as $k => $v) {
                echo "[$k]=>\"" . $this->$k . '", ';
            }
            echo ')';
        }
        echo "\n";

        if ($this->nodes) {
            foreach ($this->nodes as $c) {
                $c->dump($show_attr, $deep + 1);
            }
        }
    }


    // Debugging function to dump a single dom node with a bunch of information about it.
    public function dumpNode($echo = true)
    {
        $string = $this->tag;

        if (count($this->attr) > 0) {
            $string .= '(';
            foreach ($this->attr as $k => $v) {
                $string .= "[$k]=>\"" . $this->$k . '", ';
            }
            $string .= ')';
        }
        if (count($this->_) > 0) {
            $string .= ' $_ (';

            foreach ($this->_ as $k => $v) {
                if (is_array($v)) {
                    $string .= "[$k]=>(";
                    foreach ($v as $k2 => $v2) {
                        $string .= "[$k2]=>\"" . $v2 . '", ';
                    }
                    $string .= ")";
                }
                else {
                    $string .= "[$k]=>\"" . $v . '", ';
                }
            }
            $string .= ")";
        }

        if (isset($this->text)) {
            $string .= " text: (" . $this->text . ")";
        }

        $string .= " HDOM_INNER_INFO: ' NULL ";
        $string .= " children: " . count($this->children);
        $string .= " nodes: " . count($this->nodes);
        $string .= " tag_start: " . $this->tag_start;
        $string .= "\n";

        if ($echo) {
            echo $string;

            return '';
        }
        else {
            return $string;
        }
    }

    // returns the parent of node
    // If a node is passed in, it will reset the parent of the current node to that one.
    public function parent($parent = null)
    {
        // I am SURE that this doesn't work properly.
        // It fails to unset the current node from it's current parents nodes or children list first.
        if ($parent !== null) {
            $this->parent = $parent;
            $this->parent->nodes[] = $this;
            $this->parent->children[] = $this;
        }

        return $this->parent;
    }

    // verify that node has children
    public function hasChildNodes()
    {
        return !empty($this->children);
    }

    // returns children of node
    public function children($idx = -1)
    {
        if ($idx === -1) {
            return $this->children;
        }
        if (isset($this->children[$idx])) {
            return $this->children[$idx];
        }

        return null;
    }

    // returns the first child of node
    public function firstChild()
    {
        if (count($this->children) > 0) {
            return $this->children[0];
        }

        return null;
    }

    // returns the last child of node
    public function lastChild()
    {
        if (($count = count($this->children)) > 0) {
            return $this->children[$count - 1];
        }

        return null;
    }

    // returns the next sibling of node
    public function nextSibling()
    {
        if ($this->parent === null) {
            return null;
        }

        $idx = 0;
        $count = count($this->parent->children);
        while ($idx < $count && $this !== $this->parent->children[$idx]) {
            ++$idx;
        }
        if (++$idx >= $count) {
            return null;
        }

        return $this->parent->children[$idx];
    }

    // returns the previous sibling of node
    public function previousSibling()
    {
        if ($this->parent === null) {
            return null;
        }
        $idx = 0;
        $count = count($this->parent->children);
        while ($idx < $count && $this !== $this->parent->children[$idx]) {
            ++$idx;
        }
        if (--$idx < 0) {
            return null;
        }

        return $this->parent->children[$idx];
    }

    // function to locate a specific ancestor tag in the path to the root.
    public function findAncestorTag($tag)
    {
        // Start by including ourselves in the comparison.
        $returnDom = $this;

        while (!is_null($returnDom)) {
            if ($returnDom->tag == $tag) {
                break;
            }
            $returnDom = $returnDom->parent;
        }

        return $returnDom;
    }

    // get dom node's inner html
    public function innerText()
    {
        if (isset($this->_[HtmlDom::HDOM_INFO_INNER])) {
            return $this->_[HtmlDom::HDOM_INFO_INNER];
        }
        if (isset($this->_[HtmlDom::HDOM_INFO_TEXT])) {
            return $this->dom->restoreNoise($this->_[HtmlDom::HDOM_INFO_TEXT]);
        }

        $ret = '';
        foreach ($this->nodes as $n) {
            $ret .= $n->outerText();
        }

        return $ret;
    }

    // get dom node's outer text (with tag)
    public function outerText()
    {
        if ($this->tag === 'root') {
            return $this->innerText();
        }

        // trigger callback
        if ($this->dom && $this->dom->callback !== null) {
            call_user_func_array($this->dom->callback, [$this]);
        }

        if (isset($this->_[HtmlDom::HDOM_INFO_OUTER])) {
            return $this->_[HtmlDom::HDOM_INFO_OUTER];
        }
        if (isset($this->_[HtmlDom::HDOM_INFO_TEXT])) {
            return $this->dom->restoreNoise($this->_[HtmlDom::HDOM_INFO_TEXT]);
        }

        // render begin tag
        if ($this->dom && $this->dom->nodes[$this->_[HtmlDom::HDOM_INFO_BEGIN]]) {
            $ret = $this->dom->nodes[$this->_[HtmlDom::HDOM_INFO_BEGIN]]->makeup();
        }
        else {
            $ret = "";
        }

        // render inner text
        if (isset($this->_[HtmlDom::HDOM_INFO_INNER])) {
            // If it's a br tag...  don't return the HDOM_INNER_INFO that we may or may not have added.
            if ($this->tag != "br") {
                $ret .= $this->_[HtmlDom::HDOM_INFO_INNER];
            }
        }
        else {
            if ($this->nodes) {
                foreach ($this->nodes as $n) {
                    $ret .= $this->convertText($n->outerText());
                }
            }
        }

        // render end tag
        if (isset($this->_[HtmlDom::HDOM_INFO_END]) && $this->_[HtmlDom::HDOM_INFO_END] != 0) {
            $ret .= '</' . $this->tag . '>';
        }

        return $ret;
    }

    // get dom node's plain text
    public function text()
    {
        if (isset($this->_[HtmlDom::HDOM_INFO_INNER])) {
            return $this->_[HtmlDom::HDOM_INFO_INNER];
        }
        switch ($this->nodetype) {
            case HtmlDom::HDOM_TYPE_TEXT:
                return $this->dom->restoreNoise($this->_[HtmlDom::HDOM_INFO_TEXT]);
            case HtmlDom::HDOM_TYPE_COMMENT:
                return '';
            case HtmlDom::HDOM_TYPE_UNKNOWN:
                return '';
        }
        if (strcasecmp($this->tag, 'script') === 0) {
            return '';
        }
        if (strcasecmp($this->tag, 'style') === 0) {
            return '';
        }

        $ret = '';

        // In rare cases, (always node type 1 or HtmlDom::HDOM_TYPE_ELEMENT - observed for some span tags, and some p tags) $this->nodes is set to NULL.
        // NOTE: This indicates that there is a problem where it's set to NULL without a clear happening.
        // WHY is this happening?
        if (!is_null($this->nodes)) {
            foreach ($this->nodes as $n) {
                $ret .= $this->convertText($n->text());
            }

            // If this node is a span... add a space at the end of it so multiple spans don't run into each other.  This is plaintext after all.
            if ($this->tag == "span") {
                $ret .= $this->dom->default_span_text;
            }
        }

        return $ret;
    }

    public function xmlText()
    {
        $ret = $this->innerText();
        $ret = str_ireplace('<![CDATA[', '', $ret);
        $ret = str_replace(']]>', '', $ret);

        return $ret;
    }

    // build node's text with tag
    public function makeup()
    {
        // text, comment, unknown
        if (isset($this->_[HtmlDom::HDOM_INFO_TEXT])) {
            return $this->dom->restoreNoise($this->_[HtmlDom::HDOM_INFO_TEXT]);
        }

        $ret = '<' . $this->tag;
        $i = -1;

        foreach ($this->attr as $key => $val) {
            ++$i;

            // skip removed attribute
            if ($val === null || $val === false) {
                continue;
            }

            $ret .= $this->_[HtmlDom::HDOM_INFO_SPACE][$i][0];

            // no value attr: nowrap, checked selected...
            if ($val === true) {
                $ret .= $key;
            }
            else {
                switch ($this->_[HtmlDom::HDOM_INFO_QUOTE][$i]) {
                    case HtmlDom::HDOM_QUOTE_DOUBLE:
                        $quote = '"';
                        break;
                    case HtmlDom::HDOM_QUOTE_SINGLE:
                        $quote = '\'';
                        break;
                    default:
                        $quote = '';
                }

                $ret .= $key . $this->_[HtmlDom::HDOM_INFO_SPACE][$i][1]
                    . '='
                    . $this->_[HtmlDom::HDOM_INFO_SPACE][$i][2] . $quote . $val . $quote;
            }
        }
        $ret = $this->dom->restoreNoise($ret);

        return $ret . $this->_[HtmlDom::HDOM_INFO_ENDSPACE] . '>';
    }

    /**
     * find elements by css selector
     * PaperG - added ability for find to lowercase the value of the selector.
     *
     * @param string   $selector
     * @param int|null $idx
     * @param bool     $lowercase
     *
     * @return DomNode[]|DomNode|null
     */
    public function find($selector, $idx = null, $lowercase = false)
    {
        $selectors = $this->parseSelector($selector);
        if (($count = count($selectors)) === 0) {
            return [];
        }
        $found_keys = [];

        // find each selector
        for ($c = 0; $c < $count; ++$c) {
            // The change on the below line was documented on the sourceforge code tracker id 2788009
            // used to be: if (($level=count($selectors[0]))===0) return array();
            if (($level = count($selectors[$c])) === 0) {
                return [];
            }

            if (!isset($this->_[HtmlDom::HDOM_INFO_BEGIN])) {
                return [];
            }

            $head = [$this->_[HtmlDom::HDOM_INFO_BEGIN] => 1];

            // handle descendant selectors, no recursive!
            for ($l = 0; $l < $level; ++$l) {
                $ret = [];

                foreach ($head as $k => $v) {
                    $n = ($k === -1) ? $this->dom->root : $this->dom->nodes[$k];
                    //PaperG - Pass this optional parameter on to the seek function.
                    $n->seek($selectors[$c][$l], $ret, $lowercase);
                }

                $head = $ret;
            }

            foreach ($head as $k => $v) {
                if (!isset($found_keys[$k])) {
                    $found_keys[$k] = 1;
                }
            }
        }

        // sort keys
        ksort($found_keys);

        $found = [];
        foreach ($found_keys as $k => $v) {
            $found[] = $this->dom->nodes[$k];
        }

        // return nth-element or array
        if (is_null($idx)) {
            return $found;
        }
        elseif ($idx < 0) {
            $idx = count($found) + $idx;
        }

        return (isset($found[$idx])) ? $found[$idx] : null;
    }

    // seek for given conditions
    // PaperG - added parameter to allow for case insensitive testing of the value of a selector.
    protected function seek($selector, &$ret, $lowercase = false)
    {
        list($tag, $key, $val, $exp, $no_key) = $selector;

        // xpath index
        if ($tag && $key && is_numeric($key)) {
            $count = 0;
            foreach ($this->children as $c) {
                if ($tag === '*' || $tag === $c->tag) {
                    if (++$count == $key) {
                        $ret[$c->_[HtmlDom::HDOM_INFO_BEGIN]] = 1;

                        return;
                    }
                }
            }

            return;
        }

        $end = (!empty($this->_[HtmlDom::HDOM_INFO_END])) ? $this->_[HtmlDom::HDOM_INFO_END] : 0;
        if ($end == 0) {
            $parent = $this->parent;
            while (!isset($parent->_[HtmlDom::HDOM_INFO_END]) && $parent !== null) {
                $end -= 1;
                $parent = $parent->parent;
            }
            $end += $parent->_[HtmlDom::HDOM_INFO_END];
        }

        for ($i = $this->_[HtmlDom::HDOM_INFO_BEGIN] + 1; $i < $end; ++$i) {
            $node = $this->dom->nodes[$i];

            $pass = true;

            if ($tag === '*' && !$key) {
                if (in_array($node, $this->children, true)) {
                    $ret[$i] = 1;
                }
                continue;
            }

            // compare tag
            if ($tag && $tag != $node->tag && $tag !== '*') {
                $pass = false;
            }
            // compare key
            if ($pass && $key) {
                if ($no_key) {
                    if (isset($node->attr[$key])) {
                        $pass = false;
                    }
                }
                else {
                    if (($key != "plaintext") && !isset($node->attr[$key])) {
                        $pass = false;
                    }
                }
            }
            // compare value
            if ($pass && $key && $val && $val !== '*') {
                // If they have told us that this is a "plaintext" search then we want the plaintext of the node - right?
                if ($key == "plaintext") {
                    // $node->plaintext actually returns $node->text();
                    $nodeKeyValue = $node->text();
                }
                else {
                    // this is a normal search, we want the value of that attribute of the tag.
                    $nodeKeyValue = $node->attr[$key];
                }
                //PaperG - If lowercase is set, do a case insensitive test of the value of the selector.
                if ($lowercase) {
                    $check = $this->match($exp, strtolower($val), strtolower($nodeKeyValue));
                }
                else {
                    $check = $this->match($exp, $val, $nodeKeyValue);
                }

                // handle multiple class
                if (!$check && strcasecmp($key, 'class') === 0) {
                    foreach (explode(' ', $node->attr[$key]) as $k) {
                        // Without this, there were cases where leading, trailing, or double spaces lead to our comparing blanks - bad form.
                        if (!empty($k)) {
                            if ($lowercase) {
                                $check = $this->match($exp, strtolower($val), strtolower($k));
                            }
                            else {
                                $check = $this->match($exp, $val, $k);
                            }
                            if ($check) {
                                break;
                            }
                        }
                    }
                }
                if (!$check) {
                    $pass = false;
                }
            }
            if ($pass) {
                $ret[$i] = 1;
            }
            unset($node);
        }
    }

    protected function match($exp, $pattern, $value)
    {
        switch ($exp) {
            case '=':
                return ($value === $pattern);
            case '!=':
                return ($value !== $pattern);
            case '^=':
                return preg_match("/^" . preg_quote($pattern, '/') . "/", $value);
            case '$=':
                return preg_match("/" . preg_quote($pattern, '/') . "$/", $value);
            case '*=':
                if ($pattern[0] == '/') {
                    return preg_match($pattern, $value);
                }

                return preg_match("/" . $pattern . "/i", $value);
        }

        return false;
    }

    protected function parseSelector($selector_string)
    {
        // pattern of CSS selectors, modified from mootools
        // Paperg: Add the colon to the attrbute, so that it properly finds <tag attr:ibute="something" > like google does.
        // Note: if you try to look at this attribute, yo MUST use getAttribute since $dom->x:y will fail the php syntax check.
// Notice the \[ starting the attbute?  and the @? following?  This implies that an attribute can begin with an @ sign that is not captured.
// This implies that an html attribute specifier may start with an @ sign that is NOT captured by the expression.
// farther study is required to determine of this should be documented or removed.
//        $pattern = "/([\w-:\*]*)(?:\#([\w-]+)|\.([\w-]+))?(?:\[@?(!?[\w-]+)(?:([!*^$]?=)[\"']?(.*?)[\"']?)?\])?([\/, ]+)/is";
        $pattern = "/([\w-:\*]*)(?:\#([\w-]+)|\.([\w-]+))?(?:\[@?(!?[\w-:]+)(?:([!*^$]?=)[\"']?(.*?)[\"']?)?\])?([\/, ]+)/is";
        preg_match_all($pattern, trim($selector_string) . ' ', $matches, PREG_SET_ORDER);

        $selectors = [];
        $result = [];
        //print_r($matches);

        foreach ($matches as $m) {
            $m[0] = trim($m[0]);
            if ($m[0] === '' || $m[0] === '/' || $m[0] === '//') {
                continue;
            }

            // for browser generated xpath
            if ($m[1] === 'tbody') {
                continue;
            }

            list($tag, $key, $val, $exp, $no_key) = [$m[1], null, null, '=', false];
            if (!empty($m[2])) {
                $key = 'id';
                $val = $m[2];
            }
            if (!empty($m[3])) {
                $key = 'class';
                $val = $m[3];
            }
            if (!empty($m[4])) {
                $key = $m[4];
            }
            if (!empty($m[5])) {
                $exp = $m[5];
            }
            if (!empty($m[6])) {
                $val = $m[6];
            }

            // convert to lowercase
            if ($this->dom->lowercase) {
                $tag = strtolower($tag);
                $key = strtolower($key);
            }
            //elements that do NOT have the specified attribute
            if (isset($key[0]) && $key[0] === '!') {
                $key = substr($key, 1);
                $no_key = true;
            }

            $result[] = [$tag, $key, $val, $exp, $no_key];
            if (trim($m[7]) === ',') {
                $selectors[] = $result;
                $result = [];
            }
        }
        if (count($result) > 0) {
            $selectors[] = $result;
        }

        return $selectors;
    }

    public function __get($name)
    {
        if (isset($this->attr[$name])) {
            return $this->convertText($this->attr[$name]);
        }

        switch (strtolower($name)) {
            case 'outertext':
                return $this->outerText();
            case 'innertext':
                return $this->innerText();
            case 'plaintext':
                return $this->text();
            case 'xmltext':
                return $this->xmlText();
            default:
                return array_key_exists($name, $this->attr);
        }
    }

    public function __set($name, $value)
    {
        switch (strtolower($name)) {
            case 'outertext':
                return $this->_[HtmlDom::HDOM_INFO_OUTER] = $value;
            case 'innertext':
                if (isset($this->_[HtmlDom::HDOM_INFO_TEXT])) {
                    return $this->_[HtmlDom::HDOM_INFO_TEXT] = $value;
                }

                return $this->_[HtmlDom::HDOM_INFO_INNER] = $value;
        }

        if (!isset($this->attr[$name])) {
            $this->_[HtmlDom::HDOM_INFO_SPACE][] = [' ', '', ''];
            $this->_[HtmlDom::HDOM_INFO_QUOTE][] = HtmlDom::HDOM_QUOTE_DOUBLE;
        }

        $this->attr[$name] = $value;
    }

    public function __isset($name)
    {
        switch (strtolower($name)) {
            case 'outertext':
                return true;
            case 'innertext':
                return true;
            case 'plaintext':
                return true;
        }

        //no value attr: nowrap, checked selected...
        return (array_key_exists($name, $this->attr)) ? true : isset($this->attr[$name]);
    }

    public function __unset($name)
    {
        if (isset($this->attr[$name])) {
            unset($this->attr[$name]);
        }
    }

    // PaperG - Function to convert the text from one character set to another if the two sets are not the same.
    public function convertText($text)
    {
        $converted_text = $text;

        $sourceCharset = "";
        $targetCharset = "";

        if ($this->dom) {
            $sourceCharset = strtoupper($this->dom->_charset);
            $targetCharset = strtoupper($this->dom->_target_charset);
        }

        if (!empty($sourceCharset) && !empty($targetCharset) && (strcasecmp($sourceCharset, $targetCharset) != 0)) {
            // Check if the reported encoding could have been incorrect and the text is actually already UTF-8
            if ((strcasecmp($targetCharset, 'UTF-8') == 0) && ($this->isUtf8($text))) {
                $converted_text = $text;
            }
            else {
                $converted_text = iconv($sourceCharset, $targetCharset, $text);
            }
        }

        // Lets make sure that we don't have that silly BOM issue with any of the utf-8 text we output.
        if ($targetCharset == 'UTF-8') {
            if (substr($converted_text, 0, 3) == "\xef\xbb\xbf") {
                $converted_text = substr($converted_text, 3);
            }
            if (substr($converted_text, -3) == "\xef\xbb\xbf") {
                $converted_text = substr($converted_text, 0, -3);
            }
        }

        return $converted_text;
    }

    /**
     * Returns true if $string is valid UTF-8 and false otherwise.
     *
     * @param mixed $str String to be tested
     *
     * @return boolean
     */
    public static function isUtf8($str)
    {
        $c = 0;
        $b = 0;
        $bits = 0;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $c = ord($str[$i]);
            if ($c > 128) {
                if (($c >= 254)) {
                    return false;
                }
                elseif ($c >= 252) {
                    $bits = 6;
                }
                elseif ($c >= 248) {
                    $bits = 5;
                }
                elseif ($c >= 240) {
                    $bits = 4;
                }
                elseif ($c >= 224) {
                    $bits = 3;
                }
                elseif ($c >= 192) {
                    $bits = 2;
                }
                else {
                    return false;
                }

                if (($i + $bits) > $len) {
                    return false;
                }

                while ($bits > 1) {
                    $i++;
                    $b = ord($str[$i]);
                    if ($b < 128 || $b > 191) {
                        return false;
                    }
                    $bits--;
                }
            }
        }

        return true;
    }

    /**
     * Function to try a few tricks to determine the displayed size of an img on the page.
     * NOTE: This will ONLY work on an IMG tag. Returns FALSE on all other tag types.
     *
     * @return array|bool
     */
    public function getDisplaySize()
    {
        $width = -1;
        $height = -1;

        if ($this->tag !== 'img') {
            return false;
        }

        // See if there is aheight or width attribute in the tag itself.
        if (isset($this->attr['width'])) {
            $width = $this->attr['width'];
        }

        if (isset($this->attr['height'])) {
            $height = $this->attr['height'];
        }

        // Now look for an inline style.
        if (isset($this->attr['style'])) {
            // Thanks to user gnarf from stackoverflow for this regular expression.
            $attributes = [];
            preg_match_all("/([\w-]+)\s*:\s*([^;]+)\s*;?/", $this->attr['style'], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[2];
            }

            // If there is a width in the style attributes:
            if (isset($attributes['width']) && $width == -1) {
                // check that the last two characters are px (pixels)
                if (strtolower(substr($attributes['width'], -2)) == 'px') {
                    $proposed_width = substr($attributes['width'], 0, -2);
                    // Now make sure that it's an integer and not something stupid.
                    if (filter_var($proposed_width, FILTER_VALIDATE_INT)) {
                        $width = $proposed_width;
                    }
                }
            }

            // If there is a width in the style attributes:
            if (isset($attributes['height']) && $height == -1) {
                // check that the last two characters are px (pixels)
                if (strtolower(substr($attributes['height'], -2)) == 'px') {
                    $proposed_height = substr($attributes['height'], 0, -2);
                    // Now make sure that it's an integer and not something stupid.
                    if (filter_var($proposed_height, FILTER_VALIDATE_INT)) {
                        $height = $proposed_height;
                    }
                }
            }
        }

        // Future enhancement:
        // Look in the tag to see if there is a class or id specified that has a height or width attribute to it.

        // Far future enhancement
        // Look at all the parent tags of this image to see if they specify a class or id that has an img selector that specifies a height or width
        // Note that in this case, the class or id will have the img subselector for it to apply to the image.

        // ridiculously far future development
        // If the class or id is specified in a SEPARATE css file thats not on the page, go get it and do what we were just doing for the ones on the page.

        return [
            'height' => $height,
            'width' => $width,
        ];
    }

    public function getAllAttributes()
    {
        return array_map('html_entity_decode', $this->attr);
    }

    public function getAttribute($name)
    {
        return html_entity_decode($this->__get($name));
    }

    public function setAttribute($name, $value)
    {
        $this->__set($name, $value);
    }

    public function hasAttribute($name)
    {
        return $this->__isset($name);
    }

    public function removeAttribute($name)
    {
        $this->__set($name, null);
    }

    public function getElementById($id)
    {
        return $this->find("#$id", 0);
    }

    public function getElementsById($id, $idx = null)
    {
        return $this->find("#$id", $idx);
    }

    public function getElementByTagName($name)
    {
        return $this->find($name, 0);
    }

    public function getElementsByTagName($name, $idx = null)
    {
        return $this->find($name, $idx);
    }

    public function nodeName()
    {
        return $this->tag;
    }

    public function appendChild($node)
    {
        $node->parent($this);

        return $node;
    }
}