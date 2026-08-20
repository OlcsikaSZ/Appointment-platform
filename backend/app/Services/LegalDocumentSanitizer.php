<?php

namespace App\Services;

class LegalDocumentSanitizer
{
    private const ALLOWED_TAGS = '<p><div><br><strong><b><em><i><u><ul><ol><li><h2><h3><span><font>';

    public function sanitize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (! preg_match('/<[a-z][^>]*>/i', $value)) {
            return nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        }

        $value = preg_replace('/<!--.*?-->/s', '', $value) ?? '';
        $value = strip_tags($value, self::ALLOWED_TAGS);

        return trim((string) preg_replace_callback(
            '/<\s*(\/?)\s*([a-z0-9]+)(?:\s+[^>]*)?>/i',
            function (array $match): string {
                $closing = $match[1] === '/';
                $tag = strtolower($match[2]);
                if ($closing) {
                    if ($tag === 'font') {
                        return '</span>';
                    }
                    return in_array($tag, ['br'], true) ? '' : "</{$tag}>";
                }

                if ($tag === 'br') {
                    return '<br>';
                }

                if ($tag === 'font') {
                    preg_match('/\bsize\s*=\s*["\']?([1-7])/i', $match[0], $sizeMatch);
                    $sizes = [1 => 12, 2 => 14, 3 => 16, 4 => 18, 5 => 24, 6 => 28, 7 => 32];
                    $fontSize = $sizes[(int) ($sizeMatch[1] ?? 3)] ?? 16;

                    return "<span style=\"font-size: {$fontSize}px\">";
                }

                if ($tag === 'span') {
                    preg_match('/font-size\s*:\s*(12|14|16|18|20|24|28|32)px/i', $match[0], $sizeMatch);
                    if (isset($sizeMatch[1])) {
                        return '<span style="font-size: '.((int) $sizeMatch[1]).'px">';
                    }
                }

                return "<{$tag}>";
            },
            $value,
        ));
    }
}
