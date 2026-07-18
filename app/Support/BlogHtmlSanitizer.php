<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for CKEditor blog HTML (no external package).
 */
class BlogHtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'blockquote', 'pre', 'code',
        'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col',
        'figure', 'figcaption',
        'section',
        'div', 'span',
        'oembed',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'col' => ['span'],
        'oembed' => ['url'],
        '*' => ['class'],
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $wrapped = '<div id="blog-sanitize-root">'.$html.'</div>';
            $document->loadHTML(
                '<?xml encoding="UTF-8">'.$wrapped,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
            );

            $root = $document->getElementById('blog-sanitize-root');

            if (! $root instanceof DOMElement) {
                return strip_tags($html, '<'.implode('><', self::ALLOWED_TAGS).'>');
            }

            self::scrubNode($root);

            $output = '';
            foreach (iterator_to_array($root->childNodes) as $child) {
                $output .= $document->saveHTML($child);
            }

            return trim($output);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function scrubNode(DOMNode $node): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        /** @var list<DOMNode> $children */
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE || ! $child instanceof DOMElement) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $dropEntirely = in_array($tag, [
                    'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
                    'form', 'input', 'button', 'textarea', 'select', 'option',
                    'svg', 'math', 'base',
                ], true);

                if ($dropEntirely) {
                    $node->removeChild($child);
                } else {
                    // Keep children of unknown wrappers (e.g. <section>).
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }

                continue;
            }

            self::scrubAttributes($child, $tag);
            self::scrubNode($child);
        }
    }

    private static function scrubAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_values(array_unique(array_merge(
            self::ALLOWED_ATTRS['*'] ?? [],
            self::ALLOWED_ATTRS[$tag] ?? [],
        )));

        /** @var list<string> $names */
        $names = [];
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $names[] = $attr->name;
            }
        }

        foreach ($names as $name) {
            $lower = strtolower($name);

            if (str_starts_with($lower, 'on') || ! in_array($lower, $allowed, true)) {
                $element->removeAttribute($name);

                continue;
            }

            $value = trim($element->getAttribute($name));

            if (in_array($lower, ['href', 'src', 'url'], true) && self::isDangerousUrl($value)) {
                $element->removeAttribute($name);

                continue;
            }

            if ($lower === 'href') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }

            if ($lower === 'target') {
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function isDangerousUrl(string $value): bool
    {
        if ($value === '' || str_starts_with($value, '#')) {
            return false;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return false;
        }

        $lower = strtolower($value);

        if (preg_match('#^(javascript|data|vbscript):#i', $lower)) {
            return true;
        }

        if (preg_match('#^(https?:)?//#i', $value) || str_starts_with($lower, 'mailto:')) {
            return false;
        }

        // Relative paths without scheme (images/blog/...)
        if (! str_contains($value, ':')) {
            return false;
        }

        return true;
    }
}
