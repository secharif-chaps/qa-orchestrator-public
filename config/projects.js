/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * PROJECT CONFIGURATIONS
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Each project has: Jira, Git, Auth, Env, and optional localPath for scanning.
 * Add any new project by copying the template at the bottom.
 */

const PROJECTS = {
  // ═══════════════════════════════════════════════════════════════════════════════
  // GENERIC EXAMPLE — Copy and customize for your project
  // ═══════════════════════════════════════════════════════════════════════════════

  myproject: {
    id: 'myproject',
    name: 'My Project Name',
    key: 'MYKEY',                       // Your Jira project key
    cloudId: 'your-cloud-id-here',      // Your Atlassian cloud ID
    env: {
      type: 'local',                    // local, staging, production
      url: 'http://localhost',          // Your app URL
      localUrl: 'http://localhost'
    },
    git: {
      host: 'github.com',               // github.com, gitlab.com, bitbucket.org, etc.
      port: 22,                         // 22 for SSH, 443 for HTTPS
      group: 'myorg',                   // Your org/group on the VCS
      repo: 'myrepo',                   // Your repository name
      projectId: 'myorg/myrepo',        // Full path or numeric ID
      protocol: 'ssh',                  // ssh or https
    },
    auth: {
      provider: 'keycloak',             // keycloak, custom, none
      realm: 'myrealm',                 // Your auth realm (if using keycloak)
      client: 'myapp-client'            // Your client ID (if using keycloak)
    },
    stack: [],                          // Auto-detected: Vue3, FastAPI, etc.
    xray: true,                         // Integrate with Atlassian Jira X-Ray
    confluenceSpace: 'QA',              // Your Confluence space key

    // Live Validation — Environment Manager (optional)
    healthEndpoints: {
      api: 'http://localhost/api/health',
      frontend: 'http://localhost',
    },
    testCommands: {
      e2e: 'npx playwright test',
      unit: 'npm run test',
      backend: 'npm run test:backend',
    },
    dockerProject: 'myproject',
    dockerServices: ['frontend', 'backend', 'db'],
    startCommand: 'docker compose up -d',
  },

  // ═══════════════════════════════════════════════════════════════════════════════
  // HOW TO ADD YOUR PROJECT:
  // ═══════════════════════════════════════════════════════════════════════════════
  //
  // 1. Copy the 'myproject' block above
  // 2. Replace 'myproject' with your project ID (e.g., 'shopify', 'acme-api')
  // 3. Set your values:
  //    - name: Human-readable project name
  //    - key: Your Jira project key (e.g., SHOP, ACME)
  //    - cloudId: From your Atlassian URL or API
  //    - git: Your VCS host and repository info
  //    - auth: Your authentication provider
  //
  // 4. Test auto-detection first:
  //    npx qa-orchestrator --project myproject test MYKEY-123
  //
  // 5. QA Orchestrator will auto-detect:
  //    - Test framework (Playwright, Cypress, Jest, etc.)
  //    - VCS type (GitHub, GitLab, Bitbucket)
  //    - Issue tracker (Jira, Linear, GitHub Issues)
  //    - CI/CD system
  //
  // ═══════════════════════════════════════════════════════════════════════════════
};

module.exports = { PROJECTS };
