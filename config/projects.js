/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * PROJECT CONFIGURATIONS
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Each project has: Jira, Git, Auth, Env, and optional localPath for scanning.
 * Add any new project by copying the template at the bottom.
 */

const PROJECTS = {

  target: {
    id: 'target',
    name: 'ChapsMind TARGET',
    key: 'TAR',
    cloudId: '60cc5e3d-8230-41aa-9611-5c348537a1ea',
    env: { type: 'staging', url: 'https://main.staging.target.localnet' },
    git: {
      host: 'git.mediaspeech.com',
      port: 17890,
      group: 'chapsmind',
      repo: 'chapsmind',
      projectId: 'chapsmind/chapsmind',
      protocol: 'ssh',
    },
    auth: { provider: 'keycloak', realm: 'chapsmind', client: 'Basile-PWA' },
    localPath: '/home/secharif/chaps/chapsmind-workspace',
    stack: ['React', 'Symfony', 'Doctrine', 'OpenSearch', 'N8N', 'Dify', 'RabbitMQ', 'Keycloak'],
    xray: true,
    confluenceSpace: 'QCD',
  },

  screen: {
    id: 'screen',
    name: 'ChapsMind SCREEN',
    key: 'SCR',
    cloudId: '60cc5e3d-8230-41aa-9611-5c348537a1ea',
    env: { type: 'local', url: 'http://localhost:3000' },
    git: {
      host: 'git.mediaspeech.com',
      port: 17890,
      group: 'chapsmind',
      repo: 'chapsmind-screen',
      projectId: 'chapsmind/chapsmind-screen',
      protocol: 'ssh',
    },
    auth: { provider: 'keycloak', realm: 'chapsmind', client: 'Screen-PWA' },
    localPath: '/home/secharif/chaps/chaps-testing-workspace',
    stack: ['React', 'Node.js', 'Express', 'Dashboard', 'i18n', 'Chart.js'],
    xray: true,
    confluenceSpace: 'QCD',
  },

  // ── Template: copy this for any new project ──────────────────────────
  /*
  myproject: {
    id: 'myproject',
    name: 'My Project Name',
    key: 'MPK',                         // Jira project key
    cloudId: '60cc5e3d-...',            // Atlassian cloud ID
    env: { type: 'staging', url: 'https://...' },
    git: {
      host: 'git.mediaspeech.com',
      port: 17890,
      group: 'mygroup',
      repo: 'myrepo',
      projectId: 'mygroup/myrepo',      // GitLab path or numeric ID
      protocol: 'ssh',
    },
    auth: { provider: 'keycloak', realm: '...', client: '...' },
    localPath: '/home/.../project',     // For scanner (optional)
    stack: [],                          // Auto-filled by scanner
    xray: true,
    confluenceSpace: 'QCD',
  },
  */
};

module.exports = { PROJECTS };
