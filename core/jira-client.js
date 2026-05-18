/**
 * Jira REST API client — for terminal mode (no MCP)
 * Used by engine when running without Claude Code
 */

const XRAY_AUTH_URL = 'https://xray.cloud.getxray.app/api/v2/authenticate';
const XRAY_API_URL  = 'https://xray.cloud.getxray.app/api/v2';

class JiraClient {
  constructor(config = {}) {
    this.baseUrl = config.baseUrl || process.env.JIRA_BASE_URL || process.env.JIRA_HOST || 'https://your-jira-instance.atlassian.net';
    this.email = config.email || process.env.JIRA_EMAIL;
    this.token = config.token || process.env.JIRA_TOKEN;
    this.auth = Buffer.from(`${this.email}:${this.token}`).toString('base64');

    this.xrayClientId     = config.xrayClientId     || process.env.XRAY_CLIENT_ID;
    this.xrayClientSecret = config.xrayClientSecret || process.env.XRAY_CLIENT_SECRET;
    this._xrayToken       = null;
    this._xrayTokenExpiry = 0;
  }

  get headers() {
    return {
      'Authorization': `Basic ${this.auth}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  // ── X-Ray Auth ────────────────────────────────────────────────────────────

  get hasXray() {
    return !!(this.xrayClientId && this.xrayClientSecret);
  }

  async getXrayToken() {
    if (this._xrayToken && Date.now() < this._xrayTokenExpiry) return this._xrayToken;

    const res = await fetch(XRAY_AUTH_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        client_id: this.xrayClientId,
        client_secret: this.xrayClientSecret,
      }),
    });

    if (!res.ok) {
      const err = await res.text();
      throw new Error(`X-Ray auth failed (${res.status}): ${err}`);
    }

    // Response is a plain quoted string token
    const raw = await res.text();
    this._xrayToken = raw.replace(/^"|"$/g, '');
    // X-Ray tokens are valid for 24h — cache for 23h to be safe
    this._xrayTokenExpiry = Date.now() + 23 * 60 * 60 * 1000;
    return this._xrayToken;
  }

  async xrayHeaders() {
    const token = await this.getXrayToken();
    return {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
  }

  // ── Read ──────────────────────────────────────────────────────────────────

  async getIssue(key) {
    const res = await fetch(
      `${this.baseUrl}/rest/api/3/issue/${key}?expand=renderedFields,names`,
      { headers: this.headers }
    );
    if (!res.ok) throw new Error(`Jira ${res.status}: cannot fetch ${key}`);
    return res.json();
  }

  // Format issue as clean Markdown for agent context
  async getIssueMarkdown(key) {
    const issue = await this.getIssue(key);
    const f = issue.fields;

    const description = f.description?.content
      ?.map(block => block.content?.map(c => c.text || '').join('') || '')
      .join('\n') || f.renderedFields?.description || '(no description)';

    const ac = this.extractAcceptanceCriteria(f);

    return `# ${key}: ${f.summary}

**Type:** ${f.issuetype?.name} | **Status:** ${f.status?.name} | **Priority:** ${f.priority?.name}
**Assignee:** ${f.assignee?.displayName || 'Unassigned'} | **Reporter:** ${f.reporter?.displayName}

## Description
${description}

## Acceptance Criteria
${ac || '(no explicit AC found — derive from description)'}

## Labels
${f.labels?.join(', ') || 'none'}

## Components
${f.components?.map(c => c.name).join(', ') || 'none'}
`;
  }

  extractAcceptanceCriteria(fields) {
    // Try custom field for AC
    for (const [key, val] of Object.entries(fields)) {
      if (key.startsWith('customfield_') && typeof val === 'string' && val.length > 10) {
        if (val.toLowerCase().includes('criteria') || val.toLowerCase().includes('given') || val.toLowerCase().includes('ac-')) {
          return val;
        }
      }
    }
    // Fallback: extract from description
    const desc = fields.renderedFields?.description || '';
    const acMatch = desc.match(/acceptance criteria[:\s]*([\s\S]*?)(?=\n#{1,3}|$)/i);
    return acMatch?.[1]?.trim() || null;
  }

  // ── Write ─────────────────────────────────────────────────────────────────

  async addComment(key, markdownText) {
    // Convert markdown to Atlassian Document Format (ADF)
    const adf = this.markdownToAdf(markdownText);

    const res = await fetch(`${this.baseUrl}/rest/api/3/issue/${key}/comment`, {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify({ body: adf }),
    });
    if (!res.ok) throw new Error(`Jira ${res.status}: cannot add comment to ${key}`);
    return res.json();
  }

  async createXRayTest(parentKey, testPlan) {
    const body = {
      fields: {
        project: { key: parentKey.split('-')[0] },
        summary: `[QA] Test Plan — ${parentKey}`,
        description: this.markdownToAdf(testPlan),
        issuetype: { name: 'Test' },
        labels: ['qa-orchestrator', 'auto-generated'],
      },
    };

    const res = await fetch(`${this.baseUrl}/rest/api/3/issue`, {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const err = await res.text();
      throw new Error(`Jira ${res.status}: cannot create X-Ray test — ${err}`);
    }
    const created = await res.json();

    // Link to parent ticket
    await this.createLink(created.key, parentKey, 'is tested by');

    return created;
  }

  async createLink(fromKey, toKey, linkType = 'Relates') {
    const res = await fetch(`${this.baseUrl}/rest/api/3/issueLink`, {
      method: 'POST',
      headers: this.headers,
      body: JSON.stringify({
        type: { name: linkType },
        inwardIssue: { key: fromKey },
        outwardIssue: { key: toKey },
      }),
    });
    return res.ok;
  }

  // ── X-Ray Cloud API ───────────────────────────────────────────────────────

  /**
   * Import a Gherkin .feature file directly into X-Ray.
   * Returns the list of created/updated test issue keys.
   */
  async importGherkin(featureContent, projectKey) {
    if (!this.hasXray) throw new Error('X-Ray credentials not configured (XRAY_CLIENT_ID / XRAY_CLIENT_SECRET)');

    const token = await this.getXrayToken();

    // X-Ray Cloud requires multipart/form-data:
    //   - "file"     : the .feature file
    //   - "testInfo" : JSON blob specifying the Jira issue type to use for created tests
    const formData = new globalThis.FormData();
    formData.append('file', new Blob([featureContent], { type: 'text/plain' }), 'feature.feature');

    const testInfo = JSON.stringify({
      fields: {
        project: { key: projectKey },
        issuetype: { id: '10123' }, // "QA" issue type in TAR project (Test type not enabled)
      },
    });
    formData.append('testInfo', new Blob([testInfo], { type: 'application/json' }), 'testInfo.json');

    const res = await fetch(`${XRAY_API_URL}/import/feature?projectKey=${projectKey}`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: formData,
    });

    if (!res.ok) {
      const err = await res.text();
      throw new Error(`X-Ray importGherkin failed (${res.status}): ${err}`);
    }
    return res.json();
  }

  /**
   * Create a Test Execution in X-Ray and attach test issue keys to it.
   * Returns the created Test Execution issue key.
   */
  async createTestExecution(parentKey, testKeys, summary) {
    if (!this.hasXray) throw new Error('X-Ray credentials not configured');

    const headers = await this.xrayHeaders();
    const projectKey = parentKey.split('-')[0];

    const body = {
      fields: {
        project: { key: projectKey },
        summary: summary || `[QA] Test Execution — ${parentKey}`,
        issuetype: { name: 'Test Execution' },
        labels: ['qa-orchestrator'],
      },
      xray: {
        tests: testKeys.map(key => ({ key })),
      },
    };

    const res = await fetch(`${XRAY_API_URL}/import/execution`, {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
    });

    if (!res.ok) {
      const err = await res.text();
      throw new Error(`X-Ray createTestExecution failed (${res.status}): ${err}`);
    }

    const data = await res.json();
    const execKey = data.key;

    // Link the execution to the parent Jira ticket
    await this.createLink(execKey, parentKey, 'is tested by');

    return data;
  }

  /**
   * Upload a JUnit/Playwright XML results file to an existing Test Execution.
   */
  async importResults(xmlContent, testExecKey) {
    if (!this.hasXray) throw new Error('X-Ray credentials not configured');

    const token = await this.getXrayToken();
    const url = testExecKey
      ? `${XRAY_API_URL}/import/execution/junit?testExecKey=${testExecKey}`
      : `${XRAY_API_URL}/import/execution/junit`;

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'text/xml',
      },
      body: xmlContent,
    });

    if (!res.ok) {
      const err = await res.text();
      throw new Error(`X-Ray importResults failed (${res.status}): ${err}`);
    }
    return res.json();
  }

  /**
   * Verify credentials by authenticating and returning the token expiry.
   */
  async verifyXrayCredentials() {
    if (!this.hasXray) return { ok: false, reason: 'No credentials configured' };
    try {
      await this.getXrayToken();
      return { ok: true, expiresIn: '23h' };
    } catch (e) {
      return { ok: false, reason: e.message };
    }
  }

  // ── ADF conversion ────────────────────────────────────────────────────────

  markdownToAdf(markdown) {
    const lines = markdown.split('\n');
    const content = [];

    for (const line of lines) {
      if (line.startsWith('## ')) {
        content.push({ type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: line.slice(3) }] });
      } else if (line.startsWith('# ')) {
        content.push({ type: 'heading', attrs: { level: 1 }, content: [{ type: 'text', text: line.slice(2) }] });
      } else if (line.startsWith('- ') || line.startsWith('* ')) {
        content.push({ type: 'bulletList', content: [{ type: 'listItem', content: [{ type: 'paragraph', content: [{ type: 'text', text: line.slice(2) }] }] }] });
      } else if (line.trim()) {
        content.push({ type: 'paragraph', content: [{ type: 'text', text: line }] });
      }
    }

    return { version: 1, type: 'doc', content: content.length ? content : [{ type: 'paragraph', content: [{ type: 'text', text: markdown }] }] };
  }
}

module.exports = { JiraClient };
