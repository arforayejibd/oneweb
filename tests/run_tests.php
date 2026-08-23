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
$renderer = new Renderer(__DIR__ . '/../public');
$context = ['title' => 'OneScript Test Page'];
$testNodes = [
    ['type' => 'TextNode', 'value' => '<h1>'],
    ['type' => 'VarNode', 'expression' => 'title'],
    ['type' => 'TextNode', 'value' => '</h1>']
];
$renderedHtml = $renderer->renderNodes($testNodes, $context);
assertTest($renderedHtml === '<h1>OneScript Test Page</h1>', "Renderer resolves {{ title }} variable");

// 5. OneScript UI Component System (<one-*>) Test
$uiSource = '<one-grid cols="3"><one-card title="{{ p.name }}" price="৳{{ p.price }}"><p>Product details</p></one-card></one-grid>';
$uiTokens = Lexer::tokenize($uiSource);
assertTest($uiTokens[0]['type'] === 'T_ONE_COMPONENT_START' && $uiTokens[0]['name'] === 'grid', "Lexer tokenizes <one-grid> start tag");
assertTest($uiTokens[0]['attributes']['cols'] === '3', "Lexer parses component attribute cols='3'");

$uiAst = Parser::parse($uiTokens);
assertTest(isset($uiAst[0]['type']) && $uiAst[0]['type'] === 'ComponentNode' && $uiAst[0]['name'] === 'grid', "Parser builds AST ComponentNode for <one-grid>");
assertTest(isset($uiAst[0]['children'][0]['type']) && $uiAst[0]['children'][0]['type'] === 'ComponentNode', "Parser nests child <one-card> inside <one-grid>");

$renderContext = ['p' => ['name' => 'Smart Watch', 'price' => '2500']];
$uiOutput = $renderer->renderNodes($uiAst, $renderContext);
assertTest(strpos($uiOutput, 'grid-cols-') !== false, "Renderer generates responsive grid class for cols=3");
assertTest(strpos($uiOutput, 'Smart Watch') !== false, "Renderer resolves variables inside component attributes");
assertTest(strpos($uiOutput, 'Product details') !== false, "Renderer renders component child HTML content");

// 6. Auth Directives Test (@auth / @guest)
$authSource = '@auth Welcome Member @endauth @guest Hello Guest @endguest';
$authTokens = Lexer::tokenize($authSource);
assertTest($authTokens[0]['type'] === 'T_AUTH_START', "Lexer recognizes @auth directive");
$authAst = Parser::parse($authTokens);
$guestContext = ['auth' => ['check' => false, 'user' => null]];
$guestOutput = $renderer->renderNodes($authAst, $guestContext);
assertTest(strpos($guestOutput, 'Hello Guest') !== false && strpos($guestOutput, 'Welcome Member') === false, "Renderer evaluates @guest correctly for unauthenticated user");

$memberContext = ['auth' => ['check' => true, 'user' => ['name' => 'John']]];
$memberOutput = $renderer->renderNodes($authAst, $memberContext);
assertTest(strpos($memberOutput, 'Welcome Member') !== false && strpos($memberOutput, 'Hello Guest') === false, "Renderer evaluates @auth correctly for authenticated user");

// 7. Developer Custom Component Test (<product-card ... />)
$customCompSource = '<product-card id="101" title="Custom Wireless Mouse" price="1200">High precision optical sensor</product-card>';
$customTokens = Lexer::tokenize($customCompSource);
$customAst = Parser::parse($customTokens);
$customOutput = $renderer->renderNodes($customAst, $context);
assertTest(strpos($customOutput, 'Custom Wireless Mouse') !== false, "Engine resolves developer custom component from public/components/product-card.one");
assertTest(strpos($customOutput, 'High precision optical sensor') !== false, "Engine passes slot content to developer custom component");

// 8. Automatic Engine UI Asset Injection Test
$renderedPage = \OneScript\Engine\OneScript::render(__DIR__ . '/../public/products.one');
assertTest(strpos($renderedPage, 'cdn.tailwindcss.com') !== false, "Engine automatically injects UI assets into rendered response");

echo "\n=============================================\n";
echo "Test Summary: {$testsPassed} Passed, {$testsFailed} Failed.\n";

if ($testsFailed > 0) {
    exit(1);
} else {
    echo "🎉 ALL ONESCRIPT TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
}
