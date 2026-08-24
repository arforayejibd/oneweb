<?php

namespace OneWeb\Engine;

class Lexer {
    public static function tokenize(string $source): array {
        $tokens = [];
        $length = strlen($source);
        $cursor = 0;

        while ($cursor < $length) {
            // Match {{ variable }}
            if (substr($source, $cursor, 2) === '{{') {
                $end = strpos($source, '}}', $cursor);
                if ($end !== false) {
                    $expr = trim(substr($source, $cursor + 2, $end - ($cursor + 2)));
                    $tokens[] = [
                        'type' => 'T_VAR',
                        'value' => $expr,
                        'raw' => substr($source, $cursor, $end + 2 - $cursor)
                    ];
                    $cursor = $end + 2;
                    continue;
                }
            }

            // Match Directive starting with @
            if ($source[$cursor] === '@') {
                $rest = substr($source, $cursor);

                // @auth
                if (preg_match('/^@auth(?:\s+|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_AUTH_START', 'raw' => '@auth'];
                    $cursor += strlen('@auth');
                    continue;
                }

                // @endauth
                if (preg_match('/^@endauth(?:\s+|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_AUTH_END', 'raw' => '@endauth'];
                    $cursor += strlen('@endauth');
                    continue;
                }

                // @guest
                if (preg_match('/^@guest(?:\s+|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_GUEST_START', 'raw' => '@guest'];
                    $cursor += strlen('@guest');
                    continue;
                }

                // @endguest
                if (preg_match('/^@endguest(?:\s+|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_GUEST_END', 'raw' => '@endguest'];
                    $cursor += strlen('@endguest');
                    continue;
                }

                // @try
                if (preg_match('/^@try(?:\s+|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_TRY_START', 'raw' => '@try'];
                    $cursor += strlen('@try');
                    continue;
                }

                // @catch [var]
                if (preg_match('/^@catch(?:\s+([a-zA-Z0-9_]+))?(?:\s+|$)/i', $rest, $m)) {
                    $tokenLen = strlen($m[0]);
                    $tokens[] = [
                        'type' => 'T_CATCH_START',
                        'var' => !empty($m[1]) ? $m[1] : 'error',
                        'raw' => $m[0]
                    ];
                    $cursor += $tokenLen;
                    continue;
                }

                // @endtry
                if (preg_match('/^@endtry(?:\s+|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_TRY_END', 'raw' => '@endtry'];
                    $cursor += strlen('@endtry');
                    continue;
                }

                // @query <table_name> [where ...] [order by ...] [limit ...]
                if (preg_match('/^@query\s+([a-zA-Z0-9_]+)(?:\s+where\s+(.+?))?(?:\s+order\s+by\s+([a-zA-Z0-9_\s,]+))?(?:\s+limit\s+([0-9]+))?\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_QUERY_START',
                        'table' => $m[1],
                        'where' => !empty($m[2]) ? trim($m[2]) : null,
                        'orderBy' => !empty($m[3]) ? trim($m[3]) : null,
                        'limit' => !empty($m[4]) ? (int)$m[4] : null,
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // @endquery
                if (preg_match('/^@endquery\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_QUERY_END', 'raw' => $m[0]];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // @foreach <array> as <item>
                if (preg_match('/^@foreach\s+([a-zA-Z0-9_\.]+)\s+as\s+([a-zA-Z0-9_]+)\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_FOREACH_START',
                        'array' => $m[1],
                        'item' => $m[2],
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // @endforeach
                if (preg_match('/^@endforeach\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_FOREACH_END', 'raw' => $m[0]];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // @if <condition>
                if (preg_match('/^@if\s+(.+?)\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_IF_START',
                        'condition' => trim($m[1]),
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // @else
                if (preg_match('/^@else\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_ELSE', 'raw' => $m[0]];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // @endif
                if (preg_match('/^@endif\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = ['type' => 'T_IF_END', 'raw' => $m[0]];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // @include "filename" or 'filename'
                if (preg_match('/^@include\s+["\']([^"\']+)["\']\s*(?:\r?\n|$)/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_INCLUDE',
                        'file' => $m[1],
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }
            }

            // Match Custom Component Tags e.g. <one-card...>, <product-card...>, <x-button...>, </product-card>
            if ($source[$cursor] === '<') {
                $rest = substr($source, $cursor);

                // Closing tag: </one-card> or </product-card>
                if (preg_match('/^<\/([a-zA-Z0-9]+-[a-zA-Z0-9-]+)\s*>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_ONE_COMPONENT_END',
                        'name' => strtolower(preg_replace('/^one-/i', '', $m[1])),
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // Self-closing tag: <one-input ... /> or <product-card ... />
                if (preg_match('/^<([a-zA-Z0-9]+-[a-zA-Z0-9-]+)([^>]*?)\/>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_ONE_COMPONENT_SELF',
                        'name' => strtolower(preg_replace('/^one-/i', '', $m[1])),
                        'attributes' => self::parseAttributes($m[2]),
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // Opening tag: <one-card ...> or <product-card ...>
                if (preg_match('/^<([a-zA-Z0-9]+-[a-zA-Z0-9-]+)([^>]*?)>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_ONE_COMPONENT_START',
                        'name' => strtolower(preg_replace('/^one-/i', '', $m[1])),
                        'attributes' => self::parseAttributes($m[2]),
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }
            }

            // Match Form Directives e.g. <form @insert="products"...>
            if (substr($source, $cursor, 5) === '<form' || substr($source, $cursor, 7) === '<button') {
                $rest = substr($source, $cursor);

                // <form @insert="table" [validate="rules"] [redirect="url"] ...>
                if (preg_match('/^<form\s+@insert=["\']([^"\']+)["\'](?:\s+validate=["\']([^"\']+)["\'])?(?:\s+redirect=["\']([^"\']+)["\'])?([^>]*)>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_FORM_INSERT',
                        'table' => $m[1],
                        'validate' => !empty($m[2]) ? $m[2] : null,
                        'redirect' => !empty($m[3]) ? $m[3] : null,
                        'extra' => $m[4],
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // <form @update="table" where="clause" [validate="rules"] [redirect="url"] ...>
                if (preg_match('/^<form\s+@update=["\']([^"\']+)["\'](?:\s+where=["\']([^"\']+)["\'])?(?:\s+validate=["\']([^"\']+)["\'])?(?:\s+redirect=["\']([^"\']+)["\'])?([^>]*)>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_FORM_UPDATE',
                        'table' => $m[1],
                        'where' => !empty($m[2]) ? $m[2] : null,
                        'validate' => !empty($m[3]) ? $m[3] : null,
                        'redirect' => !empty($m[4]) ? $m[4] : null,
                        'extra' => $m[5],
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // <form @delete="table" where="clause" [redirect="url"] ...>
                if (preg_match('/^<(?:form|button)\s+@delete=["\']([^"\']+)["\'](?:\s+where=["\']([^"\']+)["\'])?(?:\s+redirect=["\']([^"\']+)["\'])?([^>]*)>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_FORM_DELETE',
                        'table' => $m[1],
                        'where' => !empty($m[2]) ? $m[2] : null,
                        'redirect' => !empty($m[3]) ? $m[3] : null,
                        'extra' => $m[4],
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }
            }

            // Normal text block token
            $nextSpecial = strlen($source);

            $nextVar = strpos($source, '{{', $cursor);
            if ($nextVar !== false && $nextVar < $nextSpecial) $nextSpecial = $nextVar;

            $nextAt = strpos($source, '@', $cursor);
            if ($nextAt !== false && $nextAt < $nextSpecial) $nextSpecial = $nextAt;

            // Match any hyphenated tag <something-tag
            if (preg_match('/<\/?([a-zA-Z0-9]+-[a-zA-Z0-9-]+)/i', $source, $m, PREG_OFFSET_CAPTURE, $cursor)) {
                $nextTagPos = $m[0][1];
                if ($nextTagPos < $nextSpecial) $nextSpecial = $nextTagPos;
            }

            $nextFormDirective = false;
            if (preg_match('/<(?:form|button)\s+@(insert|update|delete)/i', $source, $m, PREG_OFFSET_CAPTURE, $cursor)) {
                $nextFormDirective = $m[0][1];
            }
            if ($nextFormDirective !== false && $nextFormDirective < $nextSpecial) {
                $nextSpecial = $nextFormDirective;
            }

            if ($nextSpecial === $cursor) {
                // Consume 1 character if no directive matched at cursor
                $tokens[] = ['type' => 'T_TEXT', 'value' => $source[$cursor]];
                $cursor++;
            } else {
                $textChunk = substr($source, $cursor, $nextSpecial - $cursor);
                $tokens[] = ['type' => 'T_TEXT', 'value' => $textChunk];
                $cursor = $nextSpecial;
            }
        }

        return self::mergeTextTokens($tokens);
    }

    private static function mergeTextTokens(array $tokens): array {
        $merged = [];
        $currentText = '';

        foreach ($tokens as $token) {
            if ($token['type'] === 'T_TEXT') {
                $currentText .= $token['value'];
            } else {
                if ($currentText !== '') {
                    $merged[] = ['type' => 'T_TEXT', 'value' => $currentText];
                    $currentText = '';
                }
                $merged[] = $token;
            }
        }

        if ($currentText !== '') {
            $merged[] = ['type' => 'T_TEXT', 'value' => $currentText];
        }

        return $merged;
    }

    public static function parseAttributes(string $attrString): array {
        $attributes = [];
        $pattern = '/([a-zA-Z0-9_-]+)(?:\s*=\s*(?:["\']([^"\']*)["\']|([^\s>]+)))?/';
        if (preg_match_all($pattern, trim($attrString), $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower($match[1]);
                if (isset($match[2]) && $match[2] !== '') {
                    $attributes[$key] = $match[2];
                } elseif (isset($match[3]) && $match[3] !== '') {
                    $attributes[$key] = $match[3];
                } else {
                    $attributes[$key] = true;
                }
            }
        }
        return $attributes;
    }
}
