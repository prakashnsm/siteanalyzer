<?php
// (C) 2012 hush2 <hushywushy@gmail.com>

function error_handler()
{
    // TODO: log err
    throw new Exception("Internal Error. Possibly a bad page.");
}

class Site
{
    public function __construct($header, $body)
    {
        !defined('DEBUG') or set_error_handler('error_handler', -1);
        
        $this->header = $header;
        $this->body   = $body;

        libxml_use_internal_errors(true);

        $this->doc = new DOMDocument();
        $this->doc->loadHTML($body);
        $this->xpath = new DOMXpath($this->doc);

        $this->attr_only  = $this->find_tag_attributes(array('html', 'head'));

        $this->meta_tags  = $this->clean_tag_attributes($this->process_tags('meta'));
        $this->link_tags  = $this->clean_tag_attributes($this->process_tags('link'));
        // "application/x-shockwave-flash"
        $this->link_tags .= $this->clean_tag_attributes($this->process_tags('embed'));
        $this->inline_js  = $this->process_tags('script[not(@src)]'); // Inline JS
        
        list($this->comment_tags, $this->comments_ie) = $this->find_comments();

        $this->meta_tags .= $this->comments_ie;
        $this->style      = $this->process_tags('style');
        
        $this->href_src   = $this->find_links('link',   'href');
        $this->href_src  .= $this->find_links('a',      'href');
        $this->href_src  .= $this->find_links('script', 'src');
        $this->href_src  .= $this->find_links('img',    'src');
        $this->href_src  .= $this->find_links('iframe', 'src');
        $this->href_src  .= $this->find_links('form',   'action');
        $this->href_src  .= $this->find_links('div',    'data-href'); // HTML5
        $this->href_src  .= $this->find_links('embed',  'src');       // youtube, etc..
        $this->href_src  .= $this->inline_js;     // Use inline for inline specific keywords
        //$this->href_src  .= $this->find_links('form',   'action');   // cse, etc..
        $this->href_src  .= $this->comments_ie;
        
        $this->log_stuff();
    }

    protected function find_links($tag, $attr)
    {
        $tags = $this->xpath->query("//{$tag}[@{$attr}]");
        $strings = '';
        foreach ($tags as $tag) {
            $strings .= $tag->getAttribute($attr) . "\n";
        }
        return $strings;
    }

    protected function process_tags($tag)
    {
        $tags = $this->xpath->query("//{$tag}");
        $strings = '';
        foreach ($tags as $tag) {
            $strings .= $this->doc->saveHtml($tag);
        }
        return $strings;
    }

    protected function find_comments()
    {
        $tags = $this->xpath->query("//comment()");
        $strings = '';
        $strings2 = '';
        foreach ($tags as $tag) {
            $strings .= $this->doc->saveHtml($tag);
            $text = $tag->textContent;
            if (strpos($text, '[if') === 0) {   // IE conditional
                $strings2 .= $text;
            }
        }
        return array($strings, $strings2);
    }

    public function analyze($xml)
    {
        $found  = array();
        $xml    = simplexml_load_file($xml);
        foreach ($xml as $item) {
            foreach ($item->pattern as $pattern) {
                $is_match = $this->check_match($pattern);
                if ($is_match !== false) {
                    if (is_string($is_match)) {
                        $item->ver = $is_match;
                    }
                    $found[] = $item;
                    break;
                }
            }
        }
        return $found;
    }

    // Return bool or version number.
    protected function check_match($pattern)
    {
        switch ($pattern['type'])
        {
            case 'header':
                $content = $this->header;
                break;

            case 'head':
                $content = $this->attr_only;
                break;

            case 'meta':
                $content = $this->meta_tags;
                break;

            case 'link':
                $content = $this->link_tags;
                break;

            case 'inline':
                $content = $this->inline_js;
                break;

            case 'comment':
                $content = $this->comment_tags;
                break;

            case 'doctype':
                $doctype = $this->doc->doctype;
                if (empty($doctype->publicId)) {
                    $content = "//DTD HTML 5//";    // assume its <!doctype html>
                } else {
                    $content = $this->doc->doctype->publicId;
                }
                break;
            
            // For testing.
            case 'xpath':
                $xpath = $this->xpath->query($pattern->{0});
                if ($xpath && $xpath->length) {
                    return true;
                }
                return false;

            case 'style':
                $content = $this->style;
                break;
            
            // Use as a last resort.
            case 'all':
                $content = $this->body;
                break;

            default:
                $content = $this->href_src;
                break;
        }
        
        if ($pattern['regex']) {
            // Use @ as delimiter so slashes don't need to be escaped,            
            // and case insensitive match.
            $regex = '@' . (string) $pattern . '@i';    
            if (preg_match($regex, $content, $matches)) {
                if (count($matches) > 1) {
                    return end($matches);   // For multiple groups in regex
                }
                return true;
            }
        } elseif (stripos($content, (string) $pattern) !== false) {
            return true;
        }
        return false;
    }

    protected function clean_tag_attributes($tags)
    {
        return preg_replace(array("/\s*?=\s*/i", "/'/", '@"\s*(.*?)\s*"@i'),
                            array("=", '"', '"$1"'),
                            $tags);
    }

    protected function find_tag_attributes(array $tags)
    {
        $strings = '';
        foreach ($tags as $tag) {
            $attrs = $this->xpath->query("//{$tag}");
            if (!$attrs->length) {
                continue;
            }
            $attrs = $attrs->item(0)->attributes;
            if ($attrs->length) {
                foreach ($attrs as $attr) {
                    $strings .= "{$attr->name}=\"{$attr->value}\"" . PHP_EOL;
                }
            }
        }
        return $strings;
    }
    
    protected function log_stuff()
    {
        if ('DEBUG') {
            file_put_contents(LOG_DIR . 'attr_only.txt', $this->attr_only);
            file_put_contents(LOG_DIR . 'meta.txt',      $this->meta_tags);
            file_put_contents(LOG_DIR . 'comment.txt',   $this->comment_tags);
            file_put_contents(LOG_DIR . 'link.txt',      $this->link_tags);
            file_put_contents(LOG_DIR . 'inline_js.txt', $this->inline_js);
            file_put_contents(LOG_DIR . 'href_src.txt',  $this->href_src);
            file_put_contents(LOG_DIR . 'style.txt',     $this->style);
        }
    }
}