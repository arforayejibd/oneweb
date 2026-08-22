<?php

namespace OneScript\Engine;

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
                // Peek token string
                $rest = substr($source, $cursor);

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

            // Match Form Directives e.g. <form @insert="products"...>
            if (substr($source, $cursor, 5) === '<form' || substr($source, $cursor, 7) === '<button') {
                $rest = substr($source, $cursor);

                // <form @insert="table" [redirect="url"] ...>
                if (preg_match('/^<form\s+@insert=["\']([^"\']+)["\'](?:\s+redirect=["\']([^"\']+)["\'])?([^>]*)>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_FORM_INSERT',
                        'table' => $m[1],
                        'redirect' => !empty($m[2]) ? $m[2] : null,
                        'extra' => $m[3],
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // <form @update="table" where="clause" [redirect="url"] ...>
                if (preg_match('/^<form\s+@update=["\']([^"\']+)["\'](?:\s+where=["\']([^"\']+)["\'])?(?:\s+redirect=["\']([^"\']+)["\'])?([^>]*)>/i', $rest, $m)) {
                    $tokens[] = [
                        'type' => 'T_FORM_UPDATE',
                        'table' => $m[1],
                        'where' => !empty($m[2]) ? $m[2] : null,
                        'redirect' => !empty($m[3]) ? $m[3] : null,
                        'extra' => $m[4],
                        'raw' => $m[0]
                    ];
                    $cursor += strlen($m[0]);
                    continue;
                }

                // <form @delete="table" where="clause" [redirect="url"] ...>
                if (preg_match('/^<form\s+@delete=["\']([^"\']+)["\'](?:\s+where=["\']([^"\']+)["\'])?(?:\s+redirect=["\']([^"\']+)["\'])?([^>]*)>/i', $rest, $m)) {
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

            $nextForm = strpos($source, '<form', $cursor);
            if ($nextForm !== false && $nextForm < $nextSpecial) $nextSpecial = $nextForm;

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
}
