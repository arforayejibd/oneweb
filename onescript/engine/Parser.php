<?php

namespace OneScript\Engine;

class Parser {
    private array $tokens;
    private int $pos = 0;

    public function __construct(array $tokens) {
        $this->tokens = $tokens;
    }

    public static function parse(array $tokens): array {
        $parser = new self($tokens);
        return $parser->parseNodes();
    }

    private function parseNodes(?string $untilTokenType = null, ?string $secondaryUntil = null): array {
        $nodes = [];
        $count = count($this->tokens);

        while ($this->pos < $count) {
            $token = $this->tokens[$this->pos];
            $type = $token['type'];

            if ($untilTokenType !== null && ($type === $untilTokenType || $type === $secondaryUntil)) {
                break;
            }

            $this->pos++;

            switch ($type) {
                case 'T_TEXT':
                    $nodes[] = ['type' => 'TextNode', 'value' => $token['value']];
                    break;

                case 'T_VAR':
                    $nodes[] = ['type' => 'VarNode', 'expression' => $token['value']];
                    break;

                case 'T_AUTH_START':
                    $children = $this->parseNodes('T_AUTH_END');
                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_AUTH_END') {
                        $this->pos++; // consume T_AUTH_END
                    }
                    $nodes[] = ['type' => 'AuthNode', 'children' => $children];
                    break;

                case 'T_GUEST_START':
                    $children = $this->parseNodes('T_GUEST_END');
                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_GUEST_END') {
                        $this->pos++; // consume T_GUEST_END
                    }
                    $nodes[] = ['type' => 'GuestNode', 'children' => $children];
                    break;

                case 'T_TRY_START':
                    $tryChildren = $this->parseNodes('T_TRY_END', 'T_CATCH_START');
                    $catchChildren = [];
                    $catchVar = 'error';

                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_CATCH_START') {
                        $catchVar = $this->tokens[$this->pos]['var'] ?? 'error';
                        $this->pos++; // consume T_CATCH_START
                        $catchChildren = $this->parseNodes('T_TRY_END');
                    }

                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_TRY_END') {
                        $this->pos++; // consume T_TRY_END
                    }

                    $nodes[] = [
                        'type' => 'TryCatchNode',
                        'tryChildren' => $tryChildren,
                        'catchChildren' => $catchChildren,
                        'catchVar' => $catchVar
                    ];
                    break;

                case 'T_QUERY_START':
                    $children = $this->parseNodes('T_QUERY_END');
                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_QUERY_END') {
                        $this->pos++; // consume T_QUERY_END
                    }
                    $nodes[] = [
                        'type' => 'QueryNode',
                        'table' => $token['table'],
                        'where' => $token['where'] ?? null,
                        'orderBy' => $token['orderBy'] ?? null,
                        'limit' => $token['limit'] ?? null,
                        'children' => $children
                    ];
                    break;

                case 'T_FOREACH_START':
                    $children = $this->parseNodes('T_FOREACH_END');
                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_FOREACH_END') {
                        $this->pos++; // consume T_FOREACH_END
                    }
                    $nodes[] = [
                        'type' => 'ForeachNode',
                        'array' => $token['array'],
                        'item' => $token['item'],
                        'children' => $children
                    ];
                    break;

                case 'T_IF_START':
                    $ifChildren = $this->parseNodes('T_IF_END', 'T_ELSE');
                    $elseChildren = [];

                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_ELSE') {
                        $this->pos++; // consume T_ELSE
                        $elseChildren = $this->parseNodes('T_IF_END');
                    }

                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_IF_END') {
                        $this->pos++; // consume T_IF_END
                    }

                    $nodes[] = [
                        'type' => 'IfNode',
                        'condition' => $token['condition'],
                        'ifChildren' => $ifChildren,
                        'elseChildren' => $elseChildren
                    ];
                    break;

                case 'T_ONE_COMPONENT_SELF':
                    $nodes[] = [
                        'type' => 'ComponentNode',
                        'name' => $token['name'],
                        'attributes' => $token['attributes'] ?? [],
                        'children' => []
                    ];
                    break;

                case 'T_ONE_COMPONENT_START':
                    $compName = $token['name'];
                    $children = $this->parseNodes('T_ONE_COMPONENT_END');
                    if ($this->pos < $count && $this->tokens[$this->pos]['type'] === 'T_ONE_COMPONENT_END') {
                        $this->pos++; // consume T_ONE_COMPONENT_END
                    }
                    $nodes[] = [
                        'type' => 'ComponentNode',
                        'name' => $compName,
                        'attributes' => $token['attributes'] ?? [],
                        'children' => $children
                    ];
                    break;

                case 'T_INCLUDE':
                    $nodes[] = [
                        'type' => 'IncludeNode',
                        'file' => $token['file']
                    ];
                    break;

                case 'T_FORM_INSERT':
                case 'T_FORM_UPDATE':
                case 'T_FORM_DELETE':
                    $actionType = strtolower(str_replace('T_FORM_', '', $type));
                    $nodes[] = [
                        'type' => 'FormNode',
                        'actionType' => $actionType,
                        'table' => $token['table'],
                        'where' => $token['where'] ?? null,
                        'validate' => $token['validate'] ?? null,
                        'redirect' => $token['redirect'] ?? null,
                        'extra' => $token['extra'] ?? ''
                    ];
                    break;

                default:
                    if (isset($token['raw'])) {
                        $nodes[] = ['type' => 'TextNode', 'value' => $token['raw']];
                    }
                    break;
            }
        }

        return $nodes;
    }
}
