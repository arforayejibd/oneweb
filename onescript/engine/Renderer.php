<?php

namespace OneScript\Engine;

class Renderer {
    private string $viewsDir;

    public function __construct(string $viewsDir) {
        $this->viewsDir = rtrim($viewsDir, '/\\');
    }

    public function renderNodes(array $nodes, array &$context): string {
        $html = '';

        foreach ($nodes as $node) {
            switch ($node['type']) {
                case 'TextNode':
                    $html .= $node['value'];
                    break;

                case 'VarNode':
                    $val = $this->evaluateExpression($node['expression'], $context);
                    $html .= htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
                    break;

                case 'QueryNode':
                    $table = $node['table'];
                    // Evaluate where clause variables if present
                    $whereEvaluated = !empty($node['where']) ? $this->evaluateStringWithContext($node['where'], $context) : null;
                    $records = Database::query($table, $whereEvaluated, $node['orderBy'], $node['limit']);
                    
                    // Assign query results to context array under table name
                    $context[$table] = $records;

                    if (!empty($node['children'])) {
                        $html .= $this->renderNodes($node['children'], $context);
                    }
                    break;

                case 'ForeachNode':
                    $arrayVal = $this->evaluateExpression($node['array'], $context);
                    if (is_array($arrayVal) || $arrayVal instanceof \Traversable) {
                        $itemKey = $node['item'];
                        foreach ($arrayVal as $idx => $elem) {
                            $loopContext = $context;
                            $loopContext[$itemKey] = $elem;
                            $loopContext['_loop'] = [
                                'index' => $idx + 1,
                                'first' => $idx === 0,
                            ];
                            $html .= $this->renderNodes($node['children'], $loopContext);
                        }
                    }
                    break;

                case 'IfNode':
                    $condResult = $this->evaluateCondition($node['condition'], $context);
                    if ($condResult) {
                        $html .= $this->renderNodes($node['ifChildren'], $context);
                    } elseif (!empty($node['elseChildren'])) {
                        $html .= $this->renderNodes($node['elseChildren'], $context);
                    }
                    break;

                case 'IncludeNode':
                    $filename = $node['file'];
                    $filePath = $this->resolveIncludePath($filename);
                    if (file_exists($filePath)) {
                        $includeSource = file_get_contents($filePath);
                        $tokens = Lexer::tokenize($includeSource);
                        $astNodeList = Parser::parse($tokens);
                        $html .= $this->renderNodes($astNodeList, $context);
                    } else {
                        $html .= "<!-- Include Error: Template '{$filename}' not found -->";
                    }
                    break;

                case 'FormNode':
                    $actionType = strtolower($node['actionType']);
                    $table = $node['table'];
                    $where = !empty($node['where']) ? $this->evaluateStringWithContext($node['where'], $context) : '';
                    $redirect = !empty($node['redirect']) ? $this->evaluateStringWithContext($node['redirect'], $context) : ($_SERVER['REQUEST_URI'] ?? '/');
                    $extra = $node['extra'] ?? '';

                    $html .= "<form method=\"POST\" action=\"\" {$extra}>\n";
                    $html .= "  <input type=\"hidden\" name=\"_onescript_action\" value=\"" . htmlspecialchars($actionType) . "\">\n";
                    $html .= "  <input type=\"hidden\" name=\"_onescript_table\" value=\"" . htmlspecialchars($table) . "\">\n";
                    if ($where !== '') {
                        $html .= "  <input type=\"hidden\" name=\"_onescript_where\" value=\"" . htmlspecialchars($where) . "\">\n";
                    }
                    $html .= "  <input type=\"hidden\" name=\"_onescript_redirect\" value=\"" . htmlspecialchars($redirect) . "\">\n";
                    break;

                default:
                    break;
            }
        }

        return $html;
    }

    private function resolveIncludePath(string $file): string {
        $file = preg_replace('/\.one$/i', '', trim($file, '/\\'));
        
        $rootDir = dirname($this->viewsDir);
        $publicDir = $rootDir . '/public';

        $paths = [
            $publicDir . '/' . $file . '.one',
            $publicDir . '/includes/' . $file . '.one',
            $this->viewsDir . '/' . $file . '.one',
            $this->viewsDir . '/includes/' . $file . '.one',
            $this->viewsDir . '/' . $file,
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $publicDir . '/' . $file . '.one';
    }

    private function evaluateStringWithContext(string $str, array $context): string {
        return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function($m) use ($context) {
            return (string)$this->evaluateExpression($m[1], $context);
        }, $str);
    }

    private function evaluateExpression(string $expr, array $context) {
        $expr = trim($expr);

        // Dot notation property access e.g. user.name, product.price
        if (strpos($expr, '.') !== false) {
            $parts = explode('.', $expr);
            $current = $context;
            foreach ($parts as $part) {
                if (is_array($current) && isset($current[$part])) {
                    $current = $current[$part];
                } elseif (is_object($current) && isset($current->$part)) {
                    $current = $current->$part;
                } else {
                    return null;
                }
            }
            return $current;
        }

        // Direct context variable lookup
        if (array_key_exists($expr, $context)) {
            return $context[$expr];
        }

        // Check if literal number or string
        if (is_numeric($expr)) {
            return $expr + 0;
        }

        return null;
    }

    private function evaluateCondition(string $condition, array $context): bool {
        $condition = trim($condition);

        // Evaluates comparison e.g. user.balance > 0 or status == "active" or count > 0 or variable
        if (preg_match('/^(.+?)\s*(==|!=|>|<|>=|<=)\s*(.+)$/', $condition, $m)) {
            $left = $this->evaluateExpression(trim($m[1]), $context);
            $rightRaw = trim($m[3]);
            $right = (substr($rightRaw, 0, 1) === '"' || substr($rightRaw, 0, 1) === "'") 
                ? trim($rightRaw, '"\'') 
                : $this->evaluateExpression($rightRaw, $context);

            switch ($m[2]) {
                case '==': return $left == $right;
                case '!=': return $left != $right;
                case '>':  return $left > $right;
                case '<':  return $left < $right;
                case '>=': return $left >= $right;
                case '<=': return $left <= $right;
            }
        }

        // Single boolean variable check
        $val = $this->evaluateExpression($condition, $context);
        if (is_array($val)) {
            return !empty($val);
        }
        return (bool)$val;
    }
}
