<?php

spl_autoload_register(function ($class) {
    $prefix = 'OneScript\\Engine\\';
    $baseDir = __DIR__ . '/../onescript/engine/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use OneScript\Engine\Lexer;
use OneScript\Engine\Parser;
use OneScript\Engine\Database;
use OneScript\Engine\Renderer;

echo "=== Running OneScript Automated Test Suite ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest(bool $condition, string $testName) {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "✅ PASS: {$testName}\n";
        $testsPassed++;
    } else {
        echo "❌ FAIL: {$testName}\n";
        $testsFailed++;
    }
}

// 1. Lexer Tokenization Test
$source = '<h1>{{ user.name }}</h1> @query products order by id desc @endquery @foreach products as p {{ p.name }} @endforeach';
$tokens = Lexer::tokenize($source);
assertTest(count($tokens) > 0, "Lexer returns token array");
assertTest($tokens[1]['type'] === 'T_VAR', "Lexer recognizes T_VAR for {{ user.name }}");

// 2. Parser AST Test
$ast = Parser::parse($tokens);
assertTest(count($ast) > 0, "Parser constructs AST nodes");
assertTest($ast[1]['type'] === 'VarNode', "Parser builds VarNode");

// 3. Database Connection & Query Test
$dbConfig = Database::loadConfigFromOneFile();
$pdo = Database::init($dbConfig);
assertTest($pdo instanceof PDO, "Database connects via PDO");

$products = Database::query('products');
assertTest(is_array($products), "Database query returns array");

// Insert Test
$inserted = Database::insert('products', [
    'name' => 'Automated Test Product',
    'category' => 'Test',
    'price' => 99.99,
    'description' => 'Test description'
]);
assertTest($inserted === true, "Database insert succeeds");

// Verify Query
$testProduct = Database::query('products', 'name = "Automated Test Product"');
assertTest(count($testProduct) > 0, "Query fetches newly inserted test record");

// Clean up test product
if (!empty($testProduct[0]['id'])) {
    $deleted = Database::delete('products', 'id = ' . $testProduct[0]['id']);
    assertTest($deleted === true, "Database delete succeeds");
}

// 4. Renderer Template Execution Test
$renderer = new Renderer(__DIR__ . '/../views');
$context = ['title' => 'OneScript Test Page'];
$testNodes = [
    ['type' => 'TextNode', 'value' => '<h1>'],
    ['type' => 'VarNode', 'expression' => 'title'],
    ['type' => 'TextNode', 'value' => '</h1>']
];
$renderedHtml = $renderer->renderNodes($testNodes, $context);
assertTest($renderedHtml === '<h1>OneScript Test Page</h1>', "Renderer resolves {{ title }} variable");

echo "\n=============================================\n";
echo "Test Summary: {$testsPassed} Passed, {$testsFailed} Failed.\n";

if ($testsFailed > 0) {
    exit(1);
} else {
    echo "🎉 ALL ONESCRIPT TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
}
