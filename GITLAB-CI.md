# GitLab CI/CD Configuration

## Required GitLab CI/CD Variables

To build the frontend Docker image, you need to configure the following CI/CD variables in your GitLab project:

**Settings → CI/CD → Variables**

### Environment Variables

| Variable Name | Example Value | Description |
|---------------|---------------|-------------|
| `VITE_BASE_URL` | `http://chapsmind.chapsvision.com` | Base URL of the application |
| `VITE_KEYCLOAK_URL` | `https://sso.dwcode.team` | Keycloak server URL |
| `VITE_KEYCLOAK_REALM` | `mint` | Keycloak realm name |
| `VITE_BACKEND_API` | `http://chapsmind.chapsvision.com` | Backend API URL |
| `VITE_KEYCLOAK_CLIENT_ID` | `chapsmind-front-prod` | Keycloak client ID for frontend |

### How It Works

1. **Before Build**: The CI pipeline generates `.env.production` from the GitLab CI/CD variables
2. **During Build**: The Dockerfile copies `.env.production` and uses it to build the Vite app
3. **Result**: Environment variables are baked into the built static files

### Pipeline Stages

- **build-and-push-frontend**: Builds and pushes Docker images on `main` branch
  - Tags: `${CI_COMMIT_SHORT_SHA}` and `latest`

- **test-build-frontend**: Tests the build on merge requests (without pushing)

### Security Notes

- Never commit `.env.production` with production values to the repository
- Always use GitLab CI/CD variables for sensitive configuration
- Variables are masked in CI logs when marked as "Protected" or "Masked" in GitLab settings
