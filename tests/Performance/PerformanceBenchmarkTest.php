<?php

namespace Tests\Performance;

use Tests\TestCase;

/**
 * PerformanceBenchmarkTest - Test Performance Optimizations
 * 
 * Tests to validate that performance optimizations are working correctly
 * and providing measurable improvements.
 */
class PerformanceBenchmarkTest extends TestCase
{
    public function test_performance_benchmark_runs_successfully(): void
    {
        $benchmark = new PerformanceBenchmark(10, true); // Silent mode for testing
        
        // Run container benchmark
        $benchmark->benchmarkContainerResolution();
        $results = $benchmark->getResults();
        
        $this->assertArrayHasKey('container', $results);
        $this->assertArrayHasKey('improvement_percent', $results['container']);
        $this->assertGreaterThanOrEqual(0, $results['container']['improvement_percent']);
    }
    
    public function test_route_compilation_benchmark(): void
    {
        $benchmark = new PerformanceBenchmark(10, true);
        
        $benchmark->benchmarkRouteMatching();
        $results = $benchmark->getResults();
        
        $this->assertArrayHasKey('routing', $results);
        $this->assertArrayHasKey('improvement_percent', $results['routing']);
        $this->assertGreaterThanOrEqual(0, $results['routing']['improvement_percent']);
    }
    
    public function test_cache_performance_benchmark(): void
    {
        $benchmark = new PerformanceBenchmark(10, true);
        
        $benchmark->benchmarkCaching();
        $results = $benchmark->getResults();
        
        $this->assertArrayHasKey('caching', $results);
        $this->assertArrayHasKey('improvement_percent', $results['caching']);
        $this->assertGreaterThanOrEqual(0, $results['caching']['improvement_percent']);
    }
    
    public function test_bootstrap_performance_benchmark(): void
    {
        $benchmark = new PerformanceBenchmark(10, true);
        
        $benchmark->benchmarkBootstrap();
        $results = $benchmark->getResults();
        
        $this->assertArrayHasKey('bootstrap', $results);
        $this->assertArrayHasKey('average_time', $results['bootstrap']);
        $this->assertGreaterThan(0, $results['bootstrap']['average_time']);
    }
    
    public function test_full_benchmark_suite(): void
    {
        $benchmark = new PerformanceBenchmark(5, true); // Very small for CI
        
        $results = $benchmark->runAll();
        
        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('container', $results);
        $this->assertArrayHasKey('routing', $results);
        $this->assertArrayHasKey('caching', $results);
        $this->assertArrayHasKey('bootstrap', $results);
    }
}
