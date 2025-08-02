<?php

namespace Tests\Performance;

require_once __DIR__ . '/../../vendor/autoload.php';

use Refynd\Container\Container;
use Refynd\Http\Router;
use Refynd\Cache\FileStore;
use Refynd\Cache\HighPerformanceCache;

echo "🚀 Refynd Performance Optimization Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Test 1: Container Performance
echo "📦 Testing Container Performance...\n";

$container = new Container();

// Use anonymous class to avoid PSR-4 issues
$testServiceClass = new class {
    public function getValue(): string { return 'test'; }
};

$container->bind('TestService', function() use ($testServiceClass) {
    return $testServiceClass;
});

// Without cache
$container->clearCaches();
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $container->make('TestService');
}
$withoutCache = microtime(true) - $start;

// With cache (run again to populate cache)
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $container->make('TestService');
}
$withCache = microtime(true) - $start;

$containerImprovement = (($withoutCache - $withCache) / $withoutCache) * 100;

echo "  Without cache: " . round($withoutCache, 4) . "s\n";
echo "  With cache: " . round($withCache, 4) . "s\n";
echo "  Improvement: " . round($containerImprovement, 2) . "%\n\n";

// Test 2: Route Compilation Performance
echo "🛣️  Testing Route Compilation Performance...\n";

$router = new Router($container);

// Add test routes
$router->get('/', fn() => 'Home');
$router->get('/users/{id}', fn($id) => "User {$id}");
$router->get('/posts/{id}/comments', fn($id) => "Comments for post {$id}");

// Test without compilation
$router->setCompilationEnabled(false);
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $request = \Symfony\Component\HttpFoundation\Request::create('/users/123');
    $router->dispatch($request);
}
$withoutCompilation = microtime(true) - $start;

// Test with compilation
$router->setCompilationEnabled(true);
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $request = \Symfony\Component\HttpFoundation\Request::create('/users/123');
    $router->dispatch($request);
}
$withCompilation = microtime(true) - $start;

$routingImprovement = (($withoutCompilation - $withCompilation) / $withoutCompilation) * 100;

echo "  Without compilation: " . round($withoutCompilation, 4) . "s\n";
echo "  With compilation: " . round($withCompilation, 4) . "s\n";
echo "  Improvement: " . round($routingImprovement, 2) . "%\n\n";

// Test 3: High-Performance Cache
echo "💾 Testing High-Performance Cache...\n";

$fileStore = new FileStore(['path' => sys_get_temp_dir() . '/perf_test']);
$highPerfCache = new HighPerformanceCache($fileStore);

// Populate both caches
for ($i = 0; $i < 100; $i++) {
    $fileStore->put("key_{$i}", "value_{$i}");
    $highPerfCache->put("key_{$i}", "value_{$i}");
}

// Test file store
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $fileStore->get("key_" . ($i % 100));
}
$fileStoreTime = microtime(true) - $start;

// Test high-performance cache
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $highPerfCache->get("key_" . ($i % 100));
}
$highPerfTime = microtime(true) - $start;

$cacheImprovement = (($fileStoreTime - $highPerfTime) / $fileStoreTime) * 100;
$stats = $highPerfCache->getStats();

echo "  File store: " . round($fileStoreTime, 4) . "s\n";
echo "  High-performance cache: " . round($highPerfTime, 4) . "s\n";
echo "  Improvement: " . round($cacheImprovement, 2) . "%\n";
echo "  Cache hit ratio: " . $stats['hit_ratio'] . "%\n\n";

// Summary
echo "📊 Performance Summary\n";
echo "=" . str_repeat("=", 25) . "\n";
echo "Container optimization: " . round($containerImprovement, 2) . "%\n";
echo "Route compilation: " . round($routingImprovement, 2) . "%\n";
echo "High-performance cache: " . round($cacheImprovement, 2) . "%\n";

$averageImprovement = ($containerImprovement + $routingImprovement + $cacheImprovement) / 3;
echo "Average improvement: " . round($averageImprovement, 2) . "%\n\n";

echo "✅ Performance optimization test completed!\n";

// Cleanup
$fileStore->flush();
