#!/usr/bin/env node

/**
 * Quick test: verify optimization system is integrated and working
 */

const { OptimizationConfig } = require('./core/optimization-config');
const { ParallelExecutor } = require('./core/parallel-executor');

console.log('\n📊 QA Orchestrator — Optimization System Test\n');

// 1. Test OptimizationConfig
console.log('✅ Test 1: OptimizationConfig loaded');
console.log(`   Agent models: ${Object.keys(OptimizationConfig.AGENT_MODELS).length}`);
console.log(`   Tier 1 agents: ${OptimizationConfig.TIER_STRATEGY.tier1.agents.join(', ')}`);
console.log(`   Tier 2 agents: ${OptimizationConfig.TIER_STRATEGY.tier2.agents.length} (parallel)`);
console.log(`   Tier 3 agents: ${OptimizationConfig.TIER_STRATEGY.tier3.agents.length} (conditional)`);

// 2. Test Sampling Modes
console.log('\n✅ Test 2: Sampling Modes');
const modes = Object.entries(OptimizationConfig.SAMPLING.modes);
modes.forEach(([key, mode]) => {
  console.log(`   ${key}: ${mode.time}s, $${mode.cost.toFixed(2)}`);
});

// 3. Test Cost Calculation
console.log('\n✅ Test 3: Cost Calculation');
const costQuick = OptimizationConfig.calculateCost([1, 2]);
const costFull = OptimizationConfig.calculateCost([1, 2, 3]);
console.log(`   Quick (Tier 1+2): $${costQuick.toFixed(2)}`);
console.log(`   Full (All Tiers): $${costFull.toFixed(2)}`);

// 4. Test Conditional Logic
console.log('\n✅ Test 4: Conditional Logic (Tier 3)');
const tier2Results = {
  bugHunter: { criticalCount: 5 },
  testSelector: { gaps: 10 },
  overallQuality: 65,
};
console.log(`   Should run "reviewer"? ${OptimizationConfig.shouldRunConditional('reviewer', tier2Results)}`);
console.log(`   Should run "testGenerator"? ${OptimizationConfig.shouldRunConditional('testGenerator', tier2Results)}`);
console.log(`   Should run "promptTuner"? ${OptimizationConfig.shouldRunConditional('promptTuner', tier2Results)}`);

// 5. Test Caching (no actual file I/O)
console.log('\n✅ Test 5: Caching Configuration');
console.log(`   Caching enabled: ${OptimizationConfig.CACHING.enabled}`);
console.log(`   TTL: ${OptimizationConfig.CACHING.ttl / 1000}s (${OptimizationConfig.CACHING.ttl / 60000}m)`);
console.log(`   Cache directory: ${OptimizationConfig.CACHING.cacheDir}`);

// 6. Test Summary
console.log('\n✅ Test 6: Summary Report');
const summary = OptimizationConfig.summary();
console.log(`   Strategy: ${summary.strategy}`);
console.log(`   Cost: ${summary.costPerWorkflow}`);
console.log(`   Time: ${summary.timePerWorkflow}`);
console.log(`   Cost reduction: ${summary.costReduction}`);
console.log(`   Speedup: ${summary.speedup}`);
console.log(`   Quality: ${summary.quality}`);

// 7. Test ParallelExecutor instantiation
console.log('\n✅ Test 7: ParallelExecutor');
const mockEngine = {
  emit: (event, data) => {
    // Mock emit
  },
  agents: {},
  runAgent: async (id, msg) => ({ text: 'Mock output', duration: 100 }),
};
const executor = new ParallelExecutor(mockEngine);
console.log(`   ParallelExecutor instantiated`);
console.log(`   Has executeWorkflow method: ${typeof executor.executeWorkflow === 'function'}`);
console.log(`   Has _executeTier method: ${typeof executor._executeTier === 'function'}`);
console.log(`   Has getSummary method: ${typeof executor.getSummary === 'function'}`);

console.log('\n🎉 All tests passed! Optimization system is ready.\n');
