<?php

namespace Torann\DomParser;

/**
 * simple html dom parser
 * Paperg - in the find routine: allow us to specify that we want case insensitive testing of the value of the selector.
 * Paperg - change $size from protected to public so we can easily access it
 * Paperg - added ForceTagsClosed in the constructor which tells us whether we trust the html or not.  Default is to NOT trust it.
 */
class HtmlDom
{
    /**
     * All of the Defines for the classes below.
     */
    const HDOM_TYPE_ELEMENT = 1;
    const HDOM_TYPE_COMMENT = 2;
    const HDOM_TYPE_TEXT = 3;
    const HDOM_TYPE_ENDTAG = 4;
    const HDOM_TYPE_ROOT = 5;
    const HDOM_TYPE_UNKNOWN = 6;
    const HDOM_QUOTE_DOUBLE = 0;
    const HDOM_QUOTE_SINGLE = 1;
    const HDOM_QUOTE_NO = 3;
    const HDOM_INFO_BEGIN = 0;
    const HDOM_INFO_END = 1;
    const HDOM_INFO_QUOTE = 2;
    const HDOM_INFO_SPACE = 3;
    const HDOM_INFO_TEXT = 4;
    const HDOM_INFO_INNER = 5;
    const HDOM_INFO_OUTER = 6;
    const HDOM_INFO_ENDSPACE = 7;

    const DEFAULT_TARGET_CHARSET = 'UTF-8';
    const DEFAULT_BR_TEXT = "\r\n";
    const DEFAULT_SPAN_TEXT = " ";

    /** @var DomNode $root */
    public $root = null;
    public $nodes = [];
    public $callback = null;
    public $lowercase = false;

    // Used to keep track of how large the text was when we started.
    public $original_size;
    public $size;
    protected $pos;
    protected $doc;
    protected $char;
    protected $cursor;
    protected $parent;
    protected $noise = [];
    protected $token_blank = " \t\r\n";
    protected $token_equal = ' =/>';
    protected $token_slash = " />\r\n\t";
    protected $token_attr = ' >';

    // Note that this is referenced by a child node, and so it needs to be public for that node to see this information.
    public $charset = '';
    public $target_charset = '';
    protected $default_br_text = "";
    public $default_span_text = "";

    // use isset instead of in_array, performance boost about 30%...
    protected $self_closing_tags = ['img' => 1, 'br' => 1, 'input' => 1, 'meta' => 1, 'link' => 1, 'hr' => 1, 'base' => 1, 'embed' => 1, 'spacer' => 1];

    protected $block_tags = ['root' => 1, 'body' => 1, 'form' => 1, 'div' => 1, 'span' => 1, 'table' => 1];

    // Known sourceforge issue #2977341
    // B tags that are not closed cause us to return everything to the end of the document.
    protected $optional_closing_tags = [
        'tr' => ['tr' => 1, 'td' => 1, 'th' => 1],
        'th' => ['th' => 1],
        'td' => ['td' => 1],
        'li' => ['li' => 1],
        'dt' => ['dt' => 1, 'dd' => 1],
        'dd' => ['dd' => 1, 'dt' => 1],
        'dl' => ['dd' => 1, 'dt' => 1],
        'p' => ['p' => 1],
        'nobr' => ['nobr' => 1],
        'b' => ['b' => 1],
        'option' => ['option' => 1],
    ];

    public function __construct(
        $str = null,
        $lowercase = true,
        $forceTagsClosed = true,
        $target_charset = self::DEFAULT_TARGET_CHARSET,
        $stripRN = true,
        $defaultBRText = self::DEFAULT_BR_TEXT,
        $defaultSpanText = self::DEFAULT_SPAN_TEXT
    ) {
        if ($str) {
            if (preg_match("/^http:\/\//i", $str) || is_file($str)) {
                $this->loadFile($str);
            }
            else {
                $this->load($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);
            }
        }

        // Forcing tags to be closed implies that we don't trust the html, but it can lead to parsing errors if we SHOULD trust the html.
        if (!$forceTagsClosed) {
            $this->optional_closing_array = [];
        }

        $this->target_charset = $target_charset;
    }

    public function __destruct()
    {
        $this->clear();
    }

    /**
     * Get html dom from file
     *
     * @param        $url
     * @param bool   $use_include_path
     * @param null   $context
     * @param int    $offset
     * @param bool   $lowercase
     * @param bool   $forceTagsClosed
     * @param string $target_charset
     * @param bool   $stripRN
     * @param string $defaultBRText
     * @param string $defaultSpanText
     *
     * @return bool|static
     */
    public static function fromFile(
        $url,
        $use_include_path = false,
        $context = null,
        $offset = 0,
        $lowercase = true,
        $forceTagsClosed = true,
        $target_charset = self::DEFAULT_TARGET_CHARSET,
        $stripRN = true,
        $defaultBRText = self::DEFAULT_BR_TEXT,
        $defaultSpanText = self::DEFAULT_SPAN_TEXT
    ) {
        // We DO force the tags to be terminated.
        $dom = new self(
            null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText
        );

        // For sourceforge users: uncomment the next line and comment the retreive_url_contents
        // line 2 lines down if it is not already done.
        $contents = file_get_contents($url, $use_include_path, $context, $offset);

        // Paperg - use our own mechanism for getting the contents as we want to control the timeout.
        if (empty($contents)) {
            return false;
        }

        // The second parameter can force the selectors to all be lowercase.
        $dom->load($contents, $lowercase, $stripRN);

        return $dom;
    }

    /**
     * Get html dom from string
     *
     * @param        $str
     * @param bool   $lowercase
     * @param bool   $forceTagsClosed
     * @param string $target_charset
     * @param bool   $stripRN
     * @param string $defaultBRText
     * @param string $defaultSpanText
     *
     * @return bool|static
     */
    public static function fromString(
        $str,
        $lowercase = true,
        $forceTagsClosed = true,
        $target_charset = self::DEFAULT_TARGET_CHARSET,
        $stripRN = true,
        $defaultBRText = self::DEFAULT_BR_TEXT,
        $defaultSpanText = self::DEFAULT_SPAN_TEXT
    ) {
        $dom = new self(
            null, $lowercase, $forceTagsClosed, $target_charset, $stripRN, $defaultBRText, $defaultSpanText
        );

        if (empty($str)) {
            $dom->clear();

            return false;
        }

        $dom->load($str, $lowercase, $stripRN);

        return $dom;
    }

    // load html from string
    public function load(
        $str,
        $lowercase = true,
        $stripRN = true,
        $defaultBRText = self::DEFAULT_BR_TEXT,
        $defaultSpanText = self::DEFAULT_SPAN_TEXT
    ) {
        // prepare
        $this->prepare($str, $lowercase, $stripRN, $defaultBRText, $defaultSpanText);

        // strip out comments
        $this->removeNoise("'<!--(.*?)-->'is");

        // strip out cdata
        $this->removeNoise("'<!\[CDATA\[(.*?)\]\]>'is", true);

        // Per sourceforge http://sourceforge.net/tracker/?func=detail&aid=2949097&group_id=218559&atid=1044037
        // Script tags removal now preceeds style tag removal.
        // strip out <script> tags
        $this->removeNoise("'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is");
        $this->removeNoise("'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is");

        // strip out <style> tags
        $this->removeNoise("'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is");
        $this->removeNoise("'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is");

        // strip out preformatted tags
        $this->removeNoise("'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is");
        // strip out server side scripts

        $this->removeNoise("'(<\?)(.*?)(\?>)'s", true);

        // strip smarty scripts
        $this->removeNoise("'(\{\w)(.*?)(\})'s", true);

        // parsing
        while ($this->parse()) {
            //
        }

        // end
        $this->root->_[self::HDOM_INFO_END] = $this->cursor;

        $this->parseCharset();

        return $this;
    }

    // load html from file
    public function loadFile()
    {
        $args = func_get_args();
        $this->load(call_user_func_array('file_get_contents', $args), true);

        // Throw an error if we can't properly load the dom.
        if (($error = error_get_last()) !== null) {
            $this->clear();

            return false;
        }
    }

    // set callback function
    public function setCallback($function_name)
    {
        $this->callback = $function_name;
    }

    // remove callback function
    public function removeCallback()
    {
        $this->callback = null;
    }

    // save dom as string
    public function save($filepath = '')
    {
        $ret = $this->root->innerText();

        if ($filepath !== '') {
            file_put_contents($filepath, $ret, LOCK_EX);
        }

        return $ret;
    }

    // find dom node by css selector
    // Paperg - allow us to specify that we want case insensitive testing of the value of the selector.
    public function find($selector, $idx = null, $lowercase = false)
    {
        return $this->root->find($selector, $idx, $lowercase);
    }

    // clean up memory due to php5 circular references memory leak...
    public function clear()
    {
        foreach ($this->nodes as $n) {
            $n->clear();
            $n = null;
        }

        // add next line is documented in the sourceforge repository. 2977248 as a fix for ongoing memory leaks that occur even with the use of clear.
        if (isset($this->children)) {
            foreach ($this->children as $n) {
                $n->clear();
                $n = null;
            }
        }

        if (isset($this->parent)) {
            $this->parent->clear();
            unset($this->parent);
        }

        if (isset($this->root)) {
            $this->root->clear();
            unset($this->root);
        }

        unset($this->doc);
        unset($this->noise);
    }

    public function dump($show_attr = true)
    {
        $this->root->dump($show_attr);
    }

    // prepare HTML data and init everything
    protected function prepare($str, $lowercase = true, $stripRN = true, $defaultBRText = self::DEFAULT_BR_TEXT, $defaultSpanText = self::DEFAULT_SPAN_TEXT)
    {
        $this->clear();

        // set the length of content before we do anything to it.
        $this->size = strlen($str);

        // Save the original size of the html that we got in.  It might be useful to someone.
        $this->original_size = $this->size;

        //before we save the string as the doc...  strip out the \r \n's if we are told to.
        if ($stripRN) {
            $str = str_replace("\r", " ", $str);
            $str = str_replace("\n", " ", $str);

            // set the length of content since we have changed it.
            $this->size = strlen($str);
        }

        $this->doc = $str;
        $this->pos = 0;
        $this->cursor = 1;
        $this->noise = [];
        $this->nodes = [];
        $this->lowercase = $lowercase;
        $this->default_br_text = $defaultBRText;
        $this->default_span_text = $defaultSpanText;
        $this->root = new DomNode($this);
        $this->root->tag = 'root';
        $this->root->_[self::HDOM_INFO_BEGIN] = -1;
        $this->root->nodetype = self::HDOM_TYPE_ROOT;
        $this->parent = $this->root;

        if ($this->size > 0) {
            $this->char = $this->doc[0];
        }
    }

    // parse html content
    protected function parse()
    {
        if (($s = $this->copyUntilChar('<')) === '') {
            return $this->readTag();
        }

        // text
        $node = new DomNode($this);
        ++$this->cursor;
        $node->_[self::HDOM_INFO_TEXT] = $s;
        $this->linkNodes($node, false);

        return true;
    }

    // PAPERG - dkchou - added this to try to identify the character set of the page we have just parsed so we know better how to spit it out later.
    // (or the content_type header from the last transfer), we will parse THAT, and if a charset is specified, we will use it over any other mechanism.
    protected function parseCharset()
    {
        $charset = null;

        if (empty($charset)) {
            $el = $this->root->find('meta[http-equiv=Content-Type]', 0);
            if (!empty($el)) {
                $fullvalue = $el->content;

                if (!empty($fullvalue)) {
                    $success = preg_match('/charset=(.+)/', $fullvalue, $matches);
                    if ($success) {
                        $charset = $matches[1];
                    }
                    else {
                        // If there is a meta tag, and they don't specify the character set, research says that it's typically ISO-8859-1
                        $charset = 'ISO-8859-1';
                    }
                }
            }
        }

        // If we couldn't find a charset above, then lets try to detect one based on the text we got...
        if (empty($charset)) {
            // Have php try to detect the encoding from the text given to us.
            $charset = (function_exists('mb_detect_encoding')) ? mb_detect_encoding($this->root->plaintext . "ascii", $encoding_list = ["UTF-8", "CP1252"]) : false;

            // and if this doesn't work...  then we need to just wrongheadedly assume it's UTF-8 so that we can move on - cause this will usually give us most of what we need...
            if ($charset === false) {
                $charset = 'UTF-8';
            }
        }

        // Since CP1252 is a superset, if we get one of it's subsets, we want it instead.
        if ((strtolower($charset) == strtolower('ISO-8859-1')) || (strtolower($charset) == strtolower('Latin1')) || (strtolower($charset) == strtolower('Latin-1'))) {
            $charset = 'CP1252';
        }

        return $this->charset = $charset;
    }

    // read tag info
    protected function readTag()
    {
        if ($this->char !== '<') {
            $this->root->_[self::HDOM_INFO_END] = $this->cursor;

            return false;
        }

        $begin_tag_pos = $this->pos;
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

        // end tag
        if ($this->char === '/') {
            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

            // This represents the change in the HtmlDom trunk from revision 180 to 181.
            // $this->skip($this->token_blank_t);
            $this->skip($this->token_blank);
            $tag = $this->copyUntilChar('>');

            // skip attributes in end tag
            if (($pos = strpos($tag, ' ')) !== false) {
                $tag = substr($tag, 0, $pos);
            }

            $parent_lower = strtolower($this->parent->tag);
            $tag_lower = strtolower($tag);

            if ($parent_lower !== $tag_lower) {
                if (isset($this->optional_closing_tags[$parent_lower]) && isset($this->block_tags[$tag_lower])) {
                    $this->parent->_[self::HDOM_INFO_END] = 0;
                    $org_parent = $this->parent;

                    while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $this->parent->parent;
                    }

                    if (strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $org_parent; // restore origonal parent
                        if ($this->parent->parent) {
                            $this->parent = $this->parent->parent;
                        }
                        $this->parent->_[self::HDOM_INFO_END] = $this->cursor;

                        return $this->asTextNode($tag);
                    }
                }
                elseif (($this->parent->parent) && isset($this->block_tags[$tag_lower])) {
                    $this->parent->_[self::HDOM_INFO_END] = 0;
                    $org_parent = $this->parent;

                    while (($this->parent->parent) && strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $this->parent->parent;
                    }

                    if (strtolower($this->parent->tag) !== $tag_lower) {
                        $this->parent = $org_parent; // restore origonal parent
                        $this->parent->_[self::HDOM_INFO_END] = $this->cursor;

                        return $this->asTextNode($tag);
                    }
                }
                elseif (($this->parent->parent) && strtolower($this->parent->parent->tag) === $tag_lower) {
                    $this->parent->_[self::HDOM_INFO_END] = 0;
                    $this->parent = $this->parent->parent;
                }
                else {
                    return $this->asTextNode($tag);
                }
            }

            $this->parent->_[self::HDOM_INFO_END] = $this->cursor;
            if ($this->parent->parent) {
                $this->parent = $this->parent->parent;
            }

            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

            return true;
        }

        $node = new DomNode($this);
        $node->_[self::HDOM_INFO_BEGIN] = $this->cursor;
        ++$this->cursor;
        $tag = $this->copyUntil($this->token_slash);
        $node->tag_start = $begin_tag_pos;

        // doctype, cdata & comments...
        if (isset($tag[0]) && $tag[0] === '!') {
            $node->_[self::HDOM_INFO_TEXT] = '<' . $tag . $this->copyUntilChar('>');

            if (isset($tag[2]) && $tag[1] === '-' && $tag[2] === '-') {
                $node->nodetype = self::HDOM_TYPE_COMMENT;
                $node->tag = 'comment';
            }
            else {
                $node->nodetype = self::HDOM_TYPE_UNKNOWN;
                $node->tag = 'unknown';
            }
            if ($this->char === '>') {
                $node->_[self::HDOM_INFO_TEXT] .= '>';
            }
            $this->linkNodes($node, true);
            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

            return true;
        }

        // text
        if ($pos = strpos($tag, '<') !== false) {
            $tag = '<' . substr($tag, 0, -1);
            $node->_[self::HDOM_INFO_TEXT] = $tag;
            $this->linkNodes($node, false);
            $this->char = $this->doc[--$this->pos]; // prev

            return true;
        }

        if (!preg_match("/^[\w\-:]+$/", $tag)) {
            $node->_[self::HDOM_INFO_TEXT] = '<' . $tag . $this->copyUntil('<>');
            if ($this->char === '<') {
                $this->linkNodes($node, false);

                return true;
            }

            if ($this->char === '>') {
                $node->_[self::HDOM_INFO_TEXT] .= '>';
            }
            $this->linkNodes($node, false);
            $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

            return true;
        }

        // begin tag
        $node->nodetype = self::HDOM_TYPE_ELEMENT;
        $tag_lower = strtolower($tag);
        $node->tag = ($this->lowercase) ? $tag_lower : $tag;

        // handle optional closing tags
        if (isset($this->optional_closing_tags[$tag_lower])) {
            while (isset($this->optional_closing_tags[$tag_lower][strtolower($this->parent->tag)])) {
                $this->parent->_[self::HDOM_INFO_END] = 0;
                $this->parent = $this->parent->parent;
            }
            $node->parent = $this->parent;
        }

        $guard = 0; // prevent infinity loop
        $space = [$this->copySkip($this->token_blank), '', ''];

        // attributes
        do {
            if ($this->char !== null && $space[0] === '') {
                break;
            }
            $name = $this->copyUntil($this->token_equal);
            if ($guard === $this->pos) {
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                continue;
            }
            $guard = $this->pos;

            // handle endless '<'
            if ($this->pos >= $this->size - 1 && $this->char !== '>') {
                $node->nodetype = self::HDOM_TYPE_TEXT;
                $node->_[self::HDOM_INFO_END] = 0;
                $node->_[self::HDOM_INFO_TEXT] = '<' . $tag . $space[0] . $name;
                $node->tag = 'text';
                $this->linkNodes($node, false);

                return true;
            }

            // handle mismatch '<'
            if ($this->doc[$this->pos - 1] == '<') {
                $node->nodetype = self::HDOM_TYPE_TEXT;
                $node->tag = 'text';
                $node->attr = [];
                $node->_[self::HDOM_INFO_END] = 0;
                $node->_[self::HDOM_INFO_TEXT] = substr($this->doc, $begin_tag_pos, $this->pos - $begin_tag_pos - 1);
                $this->pos -= 2;
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $this->linkNodes($node, false);

                return true;
            }

            if ($name !== '/' && $name !== '') {
                $space[1] = $this->copySkip($this->token_blank);
                $name = $this->restoreNoise($name);

                if ($this->lowercase) {
                    $name = strtolower($name);
                }

                if ($this->char === '=') {
                    $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                    $this->parseAttr($node, $name, $space);
                }
                else {
                    //no value attr: nowrap, checked selected...
                    $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_NO;
                    $node->attr[$name] = true;
                    if ($this->char != '>') {
                        $this->char = $this->doc[--$this->pos]; // prev
                    }
                }

                $node->_[self::HDOM_INFO_SPACE][] = $space;
                $space = [$this->copySkip($this->token_blank), '', ''];
            }
            else {
                break;
            }
        } while ($this->char !== '>' && $this->char !== '/');

        $this->linkNodes($node, true);
        $node->_[self::HDOM_INFO_ENDSPACE] = $space[0];

        // check self closing
        if ($this->copyUntilCharEscape('>') === '/') {
            $node->_[self::HDOM_INFO_ENDSPACE] .= '/';
            $node->_[self::HDOM_INFO_END] = 0;
        }
        else {
            // reset parent
            if (!isset($this->self_closing_tags[strtolower($node->tag)])) {
                $this->parent = $node;
            }
        }
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

        // If it's a BR tag, we need to set it's text to the default text.
        // This way when we see it in plaintext, we can generate formatting that the user wants.
        // since a br tag never has sub nodes, this works well.
        if ($node->tag == "br") {
            $node->_[self::HDOM_INFO_INNER] = $this->default_br_text;
        }

        return true;
    }

    // parse attributes
    protected function parseAttr($node, $name, &$space)
    {
        // Per sourceforge: http://sourceforge.net/tracker/?func=detail&aid=3061408&group_id=218559&atid=1044037
        // If the attribute is already defined inside a tag, only pay atetntion to the first one as opposed to the last one.
        if (isset($node->attr[$name])) {
            return;
        }

        $space[2] = $this->copySkip($this->token_blank);
        switch ($this->char) {
            case '"':
                $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_DOUBLE;
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restoreNoise($this->copyUntilCharEscape('"'));
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                break;
            case '\'':
                $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_SINGLE;
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                $node->attr[$name] = $this->restoreNoise($this->copyUntilCharEscape('\''));
                $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
                break;
            default:
                $node->_[self::HDOM_INFO_QUOTE][] = self::HDOM_QUOTE_NO;
                $node->attr[$name] = $this->restoreNoise($this->copyUntil($this->token_attr));
        }

        // PaperG: Attributes should not have \r or \n in them, that counts as html whitespace.
        $node->attr[$name] = str_replace("\r", "", $node->attr[$name]);
        $node->attr[$name] = str_replace("\n", "", $node->attr[$name]);

        // PaperG: If this is a "class" selector, lets get rid of the preceeding and trailing space since some people leave it in the multi class case.
        if ($name == "class") {
            $node->attr[$name] = trim($node->attr[$name]);
        }
    }

    // link node's parent
    protected function linkNodes(&$node, $is_child)
    {
        $node->parent = $this->parent;
        $this->parent->nodes[] = $node;
        if ($is_child) {
            $this->parent->children[] = $node;
        }
    }

    // as a text node
    protected function asTextNode($tag)
    {
        $node = new DomNode($this);
        ++$this->cursor;
        $node->_[self::HDOM_INFO_TEXT] = '</' . $tag . '>';
        $this->linkNodes($node, false);
        $this->char = (++$this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

        return true;
    }

    protected function skip($chars)
    {
        $this->pos += strspn($this->doc, $chars, $this->pos);
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
    }

    protected function copySkip($chars)
    {
        $pos = $this->pos;
        $len = strspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next
        if ($len === 0) {
            return '';
        }

        return substr($this->doc, $pos, $len);
    }

    protected function copyUntil($chars)
    {
        $pos = $this->pos;
        $len = strcspn($this->doc, $chars, $pos);
        $this->pos += $len;
        $this->char = ($this->pos < $this->size) ? $this->doc[$this->pos] : null; // next

        return substr($this->doc, $pos, $len);
    }

    protected function copyUntilChar($char)
    {
        if ($this->char === null) {
            return '';
        }

        if (($pos = strpos($this->doc, $char, $this->pos)) === false) {
            $ret = substr($this->doc, $this->pos, $this->size - $this->pos);
            $this->char = null;
            $this->pos = $this->size;

            return $ret;
        }

        if ($pos === $this->pos) {
            return '';
        }
        $pos_old = $this->pos;
        $this->char = $this->doc[$pos];
        $this->pos = $pos;

        return substr($this->doc, $pos_old, $pos - $pos_old);
    }

    protected function copyUntilCharEscape($char)
    {
        if ($this->char === null) {
            return '';
        }

        $start = $this->pos;
        while (1) {
            if (($pos = strpos($this->doc, $char, $start)) === false) {
                $ret = substr($this->doc, $this->pos, $this->size - $this->pos);
                $this->char = null;
                $this->pos = $this->size;

                return $ret;
            }

            if ($pos === $this->pos) {
                return '';
            }

            if ($this->doc[$pos - 1] === '\\') {
                $start = $pos + 1;
                continue;
            }

            $pos_old = $this->pos;
            $this->char = $this->doc[$pos];
            $this->pos = $pos;

            return substr($this->doc, $pos_old, $pos - $pos_old);
        }
    }

    // remove noise from html content
    // save the noise in the $this->noise array.
    protected function removeNoise($pattern, $remove_tag = false)
    {
        $count = preg_match_all($pattern, $this->doc, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        for ($i = $count - 1; $i > -1; --$i) {
            $key = '___noise___' . sprintf('% 5d', count($this->noise) + 1000);

            $idx = ($remove_tag) ? 0 : 1;
            $this->noise[$key] = $matches[$i][$idx][0];
            $this->doc = substr_replace($this->doc, $key, $matches[$i][$idx][1], strlen($matches[$i][$idx][0]));
        }

        // reset the length of content
        $this->size = strlen($this->doc);
        if ($this->size > 0) {
            $this->char = $this->doc[0];
        }
    }

    // restore noise to html content
    public function restoreNoise($text)
    {
        while (($pos = strpos($text, '___noise___')) !== false) {
            // Sometimes there is a broken piece of markup, and we don't GET the pos+11 etc... token which indicates a problem outside of us...
            if (strlen($text) > $pos + 15) {
                $key = '___noise___' . $text[$pos + 11] . $text[$pos + 12] . $text[$pos + 13] . $text[$pos + 14] . $text[$pos + 15];
                if (isset($this->noise[$key])) {
                    $text = substr($text, 0, $pos) . $this->noise[$key] . substr($text, $pos + 16);
                }
                else {
                    // do this to prevent an infinite loop.
                    $text = substr($text, 0, $pos) . 'UNDEFINED NOISE FOR KEY: ' . $key . substr($text, $pos + 16);
                }
            }
            else {
                // There is no valid key being given back to us... We must get rid of the ___noise___ or we will have a problem.
                $text = substr($text, 0, $pos) . 'NO NUMERIC NOISE KEY' . substr($text, $pos + 11);
            }
        }

        return $text;
    }

    // Sometimes we NEED one of the noise elements.
    public function searchNoise($text)
    {
        foreach ($this->noise as $noiseElement) {
            if (strpos($noiseElement, $text) !== false) {
                return $noiseElement;
            }
        }
    }

    public function __toString()
    {
        return $this->root->innerText();
    }

    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'outertext':
                return $this->root->outerText();
            case 'innertext':
                return $this->root->innerText();
            case 'plaintext':
                return $this->root->text();
            case 'charset':
                return $this->charset;
            case 'target_charset':
                return $this->target_charset;
        }
    }

    public function children($idx = -1)
    {
        return $this->root->children($idx);
    }

    public function firstChild()
    {
        return $this->root->firstChild();
    }

    public function lastChild()
    {
        return $this->root->lastChild();
    }

    public function createElement($name, $value = null)
    {
        return @self::fromString("<$name>$value</$name>")->firstChild();
    }

    public function createTextNode($value)
    {
        return @end(self::fromString($value)->nodes);
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

    public function getElementsByTagName($name, $idx = -1)
    {
        return $this->find($name, $idx);
    }
}
