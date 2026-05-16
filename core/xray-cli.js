#!/usr/bin/env node
/**
 * X-Ray CLI helper — called by qa-test slash command and engine post-hooks
 *
 * Usage:
 *   node xray-cli.js import-gherkin --feature <path> --project <KEY> --parent <TICKET>
 *   node xray-cli.js create-execution --parent <TICKET> --tests KEY1,KEY2
 *   node xray-cli.js import-results --xml <path> --exec <TEST_EXEC_KEY>
 *   node xray-cli.js verify
 */

require('dotenv').config({ path: require('path').join(__dirname, '..', '.env') });

const fs = require('fs');
const { JiraClient } = require('./jira-client');

const jira = new JiraClient();

function parseArgs(argv) {
  const args = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i].startsWith('--')) {
      const key = argv[i].slice(2);
      const next = argv[i + 1];
      if (next && !next.startsWith('--')) { args[key] = next; i++; }
      else args[key] = true;
    } else {
      args._.push(argv[i]);
    }
  }
  return args;
}

async function main() {
  const argv = process.argv.slice(2);
  const command = argv[0];
  const args = parseArgs(argv.slice(1));

  switch (command) {
    case 'import-gherkin': {
      if (!args.feature || !args.project) {
        console.error('Usage: xray-cli.js import-gherkin --feature <path> --project <KEY> [--parent <TICKET>]');
        process.exit(1);
      }
      const content = fs.readFileSync(args.feature, 'utf8');
      console.log(`Importing ${args.feature} into X-Ray project ${args.project}...`);
      const result = await jira.importGherkin(content, args.project);
      console.log(JSON.stringify(result, null, 2));

      // Auto-create Test Execution and link to parent if provided
      if (args.parent && result.updatedOrCreatedTests?.length) {
        const testKeys = result.updatedOrCreatedTests.map(t => t.self?.split('/').pop() || t.key).filter(Boolean);
        if (testKeys.length) {
          console.log(`\nCreating Test Execution for ${testKeys.length} tests...`);
          const exec = await jira.createTestExecution(args.parent, testKeys);
          console.log(`Test Execution created: ${exec.key}`);
        }
      }
      break;
    }

    case 'create-execution': {
      if (!args.parent) {
        console.error('Usage: xray-cli.js create-execution --parent <TICKET> [--tests KEY1,KEY2]');
        process.exit(1);
      }
      const testKeys = args.tests ? args.tests.split(',') : [];
      const exec = await jira.createTestExecution(args.parent, testKeys);
      console.log(JSON.stringify(exec, null, 2));
      break;
    }

    case 'import-results': {
      if (!args.xml) {
        console.error('Usage: xray-cli.js import-results --xml <path> [--exec <TEST_EXEC_KEY>]');
        process.exit(1);
      }
      const xml = fs.readFileSync(args.xml, 'utf8');
      const result = await jira.importResults(xml, args.exec);
      console.log(JSON.stringify(result, null, 2));
      break;
    }

    case 'verify': {
      const result = await jira.verifyXrayCredentials();
      if (result.ok) {
        console.log(`X-Ray auth OK — token valid for ${result.expiresIn}`);
      } else {
        console.error(`X-Ray auth FAILED: ${result.reason}`);
        process.exit(1);
      }
      break;
    }

    default:
      console.error(`Unknown command: ${command}`);
      console.error('Commands: import-gherkin, create-execution, import-results, verify');
      process.exit(1);
  }
}

main().catch(err => {
  console.error(`Fatal: ${err.message}`);
  process.exit(1);
});
