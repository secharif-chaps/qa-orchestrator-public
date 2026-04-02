# 🛡️ Security Scanning with Trivy

## Overview

Basil uses [Trivy](https://trivy.dev/) for automated security vulnerability scanning of container images. The CI/CD pipeline performs comprehensive security scans on both API and PWA containers, with configurable thresholds and automated reporting.

## Features

- **Automated Scanning**: Security scans run on every merge request and deployment
- **Multiple Report Formats**: HTML, JSON, and GitLab Security Report formats
- **Threshold-based Failures**: Pipeline fails based on configurable vulnerability severity limits
- **CVE Skip Functionality**: Ability to ignore specific CVEs when justified
- **MR Integration**: Automatic security scan results posted as merge request comments
- **Documentation Integration**: Security reports published to GitLab Pages

## Configuration

### Vulnerability Thresholds

The pipeline uses configurable thresholds to determine when to fail:

| Severity | Default Threshold | Description                                               |
| -------- | ----------------- | --------------------------------------------------------- |
| Critical | `0`               | Pipeline fails if any critical vulnerabilities found      |
| High     | `10`              | Pipeline fails if more than 10 high vulnerabilities found |
| Medium   | `-1`              | No threshold applied (unlimited)                          |
| Low      | `-1`              | No threshold applied (unlimited)                          |

**Threshold Values:**

- `0` = No vulnerabilities of this severity allowed
- `> 0` = Maximum number of vulnerabilities allowed
- `-1` = Unlimited (no threshold)

### Environment Variables

Configure these variables in **GitLab Settings > CI/CD > Variables**:

```bash
# Vulnerability thresholds
CRITICAL_THRESHOLD=0     # Default: 0 (forbidden)
HIGH_THRESHOLD=10        # Default: 10
MEDIUM_THRESHOLD=-1      # Default: -1 (unlimited)

# CVE skip list (comma-separated)
TRIVY_SKIP_CVES="CVE-2023-12345,CVE-2024-67890"

# GitLab API token for MR comments (required for MR integration)
GITLAB_API_TOKEN=glpat-xxxxxxxxxxxxxxxxxxxx  # Scope: api
```

## CVE Skip Options

### 1. Global Skip via CI/CD Variable

Set `TRIVY_SKIP_CVES` in GitLab CI/CD Variables:

```bash
TRIVY_SKIP_CVES="CVE-2023-12345,CVE-2024-67890,CVE-2023-54321"
```

### 2. Component-Specific Skip

Override in `.gitlab-ci.yml` for specific components:

```yaml
trivy-api:
    variables:
        TRIVY_SKIP_CVES: 'CVE-2023-12345' # API only

trivy-pwa:
    variables:
        TRIVY_SKIP_CVES: 'CVE-2024-67890' # PWA only
```

### 3. Permanent Skip with .trivyignore

Add CVEs to `.trivyignore` file in repository root:

```
# .trivyignore
CVE-2023-12345  # False positive - vendor confirmed not exploitable
CVE-2024-67890  # Requires physical access - low risk for container deployment
```

**Best Practice:** Always document the reason for skipping a CVE.

## Reports and Integration

### Merge Request Comments

When `GITLAB_API_TOKEN` is configured, security scan results are automatically posted as MR comments:

```markdown
## 🛡️ Security Scan Results

**Commit:** `abc123def`

### 🔧 API Container

- 🔴 Critical: **0**
- 🟠 High: **3**
- 🟡 Medium: **12**

### 🎨 PWA Container

- 🔴 Critical: **0**
- 🟠 High: **1**
- 🟡 Medium: **8**

**Configured Thresholds:**

- 🔴 Critical: 0 (0 = block, -1 = unlimited)
- 🟠 High: 10 (-1 = unlimited)
- 🟡 Medium: -1 (-1 = unlimited)

📊 **Detailed Reports:** Available in job artifacts
```

### GitLab Pages Integration

Security reports are automatically integrated into the project documentation:

- **URL**: `https://your-gitlab-pages-url/security/`
- **Contents**:
    - Interactive HTML reports for each component
    - Summary tables with vulnerability counts
    - Raw JSON data for automated processing

### CI/CD Artifacts

Each scan produces the following artifacts (available for 1 week):

```
trivy-api.html     # Interactive HTML report for API
trivy-api.json     # Machine-readable JSON data
trivy-pwa.html     # Interactive HTML report for PWA
trivy-pwa.json     # Machine-readable JSON data
```

## Understanding Severity Levels

| Severity        | Description                                             | Examples                                  |
| --------------- | ------------------------------------------------------- | ----------------------------------------- |
| 🔴 **Critical** | Remotely exploitable without authentication             | RCE, SQL injection, authentication bypass |
| 🟠 **High**     | Significant security impact, may require authentication | Privilege escalation, XSS, CSRF           |
| 🟡 **Medium**   | Moderate security impact or difficult to exploit        | Information disclosure, DoS               |
| 🟢 **Low**      | Minor security concerns or informational                | Version disclosure, weak ciphers          |

## CI/CD Jobs

### `trivy-api`

- Scans the API container image
- Uses `$API_IMAGE:$IMAGE_TAG`
- Generates `trivy-api.*` reports

### `trivy-pwa`

- Scans the PWA container image
- Uses `$PWA_IMAGE:$IMAGE_TAG`
- Generates `trivy-pwa.*` reports

### `trivy-mr-comment`

- Posts scan results to merge request comments
- Only runs on merge requests
- Requires `GITLAB_API_TOKEN`

### `trivy-security-docs`

- Generates security documentation
- Creates summary tables and index pages
- Integrates with MkDocs documentation

## Workflow Integration

### Development Workflow

1. **Push Code** → Security scans run automatically
2. **Review MR** → Security results appear in comments
3. **Address Issues** → Fix vulnerabilities or justify skips
4. **Merge** → Clean security scan required

### Security Response Process

1. **Critical/High Vulnerabilities Found**:
    - Pipeline fails automatically
    - Review vulnerability details in HTML reports
    - Update base images or dependencies
    - Or justify skip with documented reason

2. **False Positives**:
    - Add CVE to `.trivyignore` with justification
    - Or use `TRIVY_SKIP_CVES` variable for temporary skip

3. **Monitoring**:
    - Regular review of security reports in GitLab Pages
    - Update skip lists when CVEs are fixed
    - Adjust thresholds based on risk appetite

## Troubleshooting

### Common Issues

**Pipeline fails with "Critical vulnerabilities exceed threshold"**

- Review the HTML report for vulnerability details
- Update base images: `docker pull <base-image>:latest`
- Or add justified skip to `.trivyignore`

**MR comments not appearing**

- Verify `GITLAB_API_TOKEN` is set with `api` scope
- Check job logs for API response errors
- Ensure token has access to the project

**Security reports missing from documentation**

- Check `trivy-security-docs` job logs
- Verify artifacts are generated correctly
- Ensure `build-docs` job includes security artifacts

### Debug Commands

```bash
# Run Trivy scan locally
docker run --rm -v $(pwd)/.trivyignore:/.trivyignore \
  aquasec/trivy image --format table your-image:tag

# Test GitLab API token
curl -H "PRIVATE-TOKEN: $GITLAB_API_TOKEN" \
  "$CI_API_V4_URL/projects/$CI_PROJECT_ID"
```

## Best Practices

### Security Configuration

- Keep thresholds strict but realistic
- Document all CVE skips with clear justifications
- Regularly review and update skip lists
- Use component-specific skips when appropriate

### Workflow Integration

- Review security reports before merging
- Address critical/high vulnerabilities promptly
- Use security reports for dependency update planning
- Monitor trends in vulnerability counts

### Documentation

- Keep `.trivyignore` comments up to date
- Document security exceptions in MR descriptions
- Review security reports in team meetings
- Share security report URLs with stakeholders

## References

- [Trivy Documentation](https://trivy.dev/latest/)
- [GitLab Security Scanning](https://docs.gitlab.com/ee/user/application_security/)
- [CVE Database](https://cve.mitre.org/)
- [Container Security Best Practices](https://kubernetes.io/docs/concepts/security/)
