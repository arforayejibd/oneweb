<?php

namespace OneScript\Engine;

class Renderer {
    private string $viewsDir;

    public function __construct(string $viewsDir) {
        $this->viewsDir = rtrim($viewsDir, '/\\');
    }

    public function renderNodes(array $nodes, array &$context): string {
        $html = '';

        // Inject Auth helper state if not set
        if (!isset($context['auth'])) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $context['auth'] = [
                'check' => !empty($_SESSION['user']),
                'user'  => $_SESSION['user'] ?? null,
            ];
        }

        foreach ($nodes as $node) {
            switch ($node['type']) {
                case 'TextNode':
                    $html .= $node['value'];
                    break;

                case 'VarNode':
                    $val = $this->evaluateExpression($node['expression'], $context);
                    $html .= htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
                    break;

                case 'AuthNode':
                    $isAuth = !empty($context['auth']['check']) || !empty($_SESSION['user']);
                    if ($isAuth && !empty($node['children'])) {
                        $html .= $this->renderNodes($node['children'], $context);
                    }
                    break;

                case 'GuestNode':
                    $isAuth = !empty($context['auth']['check']) || !empty($_SESSION['user']);
                    if (!$isAuth && !empty($node['children'])) {
                        $html .= $this->renderNodes($node['children'], $context);
                    }
                    break;

                case 'TryCatchNode':
                    try {
                        $html .= $this->renderNodes($node['tryChildren'], $context);
                    } catch (\Throwable $e) {
                        $catchVar = $node['catchVar'] ?? 'error';
                        $catchContext = $context;
                        $catchContext[$catchVar] = $e->getMessage();
                        if (!empty($node['catchChildren'])) {
                            $html .= $this->renderNodes($node['catchChildren'], $catchContext);
                        }
                    }
                    break;

                case 'QueryNode':
                    $table = $node['table'];
                    $whereEvaluated = !empty($node['where']) ? $this->evaluateStringWithContext($node['where'], $context) : null;
                    $records = Database::query($table, $whereEvaluated, $node['orderBy'], $node['limit']);
                    
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
                    $validate = $node['validate'] ?? '';

                    $signature = FormHandler::generateSignature($actionType, $table, $where);

                    $html .= "<form method=\"POST\" action=\"\" {$extra}>\n";
                    $html .= "  <input type=\"hidden\" name=\"_onescript_action\" value=\"" . htmlspecialchars($actionType) . "\">\n";
                    $html .= "  <input type=\"hidden\" name=\"_onescript_table\" value=\"" . htmlspecialchars($table) . "\">\n";
                    if ($where !== '') {
                        $html .= "  <input type=\"hidden\" name=\"_onescript_where\" value=\"" . htmlspecialchars($where) . "\">\n";
                    }
                    if ($validate !== '') {
                        $html .= "  <input type=\"hidden\" name=\"_onescript_validate\" value=\"" . htmlspecialchars($validate) . "\">\n";
                    }
                    $html .= "  <input type=\"hidden\" name=\"_onescript_signature\" value=\"" . htmlspecialchars($signature) . "\">\n";
                    $html .= "  <input type=\"hidden\" name=\"_onescript_redirect\" value=\"" . htmlspecialchars($redirect) . "\">\n";

                    // Inject Flash Validation Alerts if present
                    if (!empty($_SESSION['_onescript_flash_error'])) {
                        $html .= "  <div class=\"mb-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold\">\n";
                        $html .= "    <i class=\"fa-solid fa-triangle-exclamation mr-2\"></i> " . htmlspecialchars($_SESSION['_onescript_flash_error']) . "\n";
                        $html .= "  </div>\n";
                        unset($_SESSION['_onescript_flash_error']);
                    }
                    break;

                case 'ComponentNode':
                    $childrenHtml = !empty($node['children']) ? $this->renderNodes($node['children'], $context) : '';
                    
                    $evaluatedAttrs = [];
                    foreach ($node['attributes'] as $attrKey => $attrVal) {
                        if (is_string($attrVal)) {
                            $evaluatedAttrs[$attrKey] = $this->evaluateStringWithContext($attrVal, $context);
                        } else {
                            $evaluatedAttrs[$attrKey] = $attrVal;
                        }
                    }

                    $compName = $node['name'];
                    
                    // 1. Check built-in component registry
                    if (ComponentRegistry::has($compName)) {
                        $html .= ComponentRegistry::render($compName, $evaluatedAttrs, $childrenHtml);
                    } else {
                        // 2. Check for Developer Custom Component file in components/ directory
                        $customPath = $this->resolveComponentPath($compName);
                        if ($customPath && file_exists($customPath)) {
                            $compSource = file_get_contents($customPath);
                            $compTokens = Lexer::tokenize($compSource);
                            $compAst = Parser::parse($compTokens);
                            
                            $compContext = array_merge($context, $evaluatedAttrs, ['slot' => $childrenHtml]);
                            $html .= $this->renderNodes($compAst, $compContext);
                        } else {
                            // Fallback to custom tag rendering if not found
                            $html .= "<{$compName}>{$childrenHtml}</{$compName}>";
                        }
                    }
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

    private function resolveComponentPath(string $compName): ?string {
        $cleanName = preg_replace('/^one-/i', '', strtolower(trim($compName)));
        
        $rootDir = dirname($this->viewsDir);
        $publicDir = $rootDir . '/public';

        $candidates = [
            $publicDir . '/components/' . $compName . '.one',
            $publicDir . '/components/' . $cleanName . '.one',
            $this->viewsDir . '/components/' . $compName . '.one',
            $this->viewsDir . '/components/' . $cleanName . '.one',
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        return null;
    }

    private function evaluateStringWithContext(string $str, array $context): string {
        return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function($m) use ($context) {
            return (string)$this->evaluateExpression($m[1], $context);
        }, $str);
    }

    private function evaluateExpression(string $expr, array $context) {
        $expr = trim($expr);

        // Dot notation property access e.g. user.name, auth.user.email
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

        $val = $this->evaluateExpression($condition, $context);
        if (is_array($val)) {
            return !empty($val);
        }
        return (bool)$val;
    }
}
