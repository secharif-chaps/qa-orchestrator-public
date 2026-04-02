# Keycloak Theme Customization

This document provides a comprehensive guide to customizing Keycloak themes, specifically focusing on the "chapsmind" theme. It covers configuration, theme hierarchy, inheritance, and how to extract and customize default themes.

## Theme Features

The ChapsMind theme provides a custom-branded login experience with:

- **Custom branding**: ChapsMind logo and color scheme
- **Hanken Grotesk font**: Custom typography (regular, 500, 600, 700 weights)
- **Organization support**: Templates for organization selection flow
- **Accessibility**: WCAG 2.1 AA compliant with semantic HTML and ARIA attributes
- **Internationalization**: Full support for English and French
- **Error states**: Custom styling for form validation errors
- **Social login**: Styled identity provider buttons
- **Responsive design**: Mobile-friendly layout

## 1. Configuring the theme

In your `docker-compose.override.yaml`, under the `keycloak` service, mount the following volumes:

```yaml
services:
    keycloak:
        volumes:
            - ./docker/keycloak/realm-chapsmind-dev.json:/opt/keycloak/data/import/realm-chapsmind-dev.json
            - ./docker/keycloak/chapsmind-theme:/opt/keycloak/themes/chapsmind
```

This ensures:

- Your `realm-chapsmind-dev.json` is imported on startup.
- The `chapsmind-theme` directory on the host is available at `/opt/keycloak/themes/chapsmind` in the container.

In your realm JSON (`realm-chapsmind-dev.json`), specify the login theme:

```json
{
  "loginTheme": "chapsmind",
  ...
}
```

## 2. Theme hierarchy and inheritance

A theme’s root directory contains a `theme.properties` file. For “chapsmind”:

```properties
parent=keycloak.v2
import=common/keycloak

darkMode=false

styles=css/styles-chapsmind.css css/styles.css
```

- `parent=keycloak.v2`: inherit from the official Keycloak v2 theme.
- `import=common/keycloak`: import shared resources.
- Overrides (e.g., CSS, dark mode) customize behavior and appearance.

Keycloak resolves resources by:

1. Looking in `themes/chapsmind`.
2. Falling back to `themes/keycloak.v2`.
3. Finally, checking `themes/common/keycloak`.

You can have separate themes for login, account, email, admin, each with similar inheritance.

## 3. Extracting default themes to have a starting point/inspiration

To extract the default themes:

1. Retrieve the theme jar list from the running Keycloak container:

```bash
docker compose exec keycloak sh -c "ls /opt/keycloak/lib/lib/main/ | grep 'keycloak-themes-.*.jar'"
```

You would see a list of theme jars, such as:

```bash
org.keycloak.keycloak-themes-26.4.6.jar
org.keycloak.keycloak-themes-vendor-26.4.6.jar
```

2. Copy the themes from the Keycloak container to your local directory:

```bash
docker compose cp keycloak:/opt/keycloak/lib/lib/main/org.keycloak.keycloak-themes-26.4.6.jar ./docker/keycloak
```

3. Extract the contents of the jar file:

```bash
mkdir -p ./docker/keycloak/default-themes
unzip -d ./docker/keycloak/default-themes ./docker/keycloak/org.keycloak.keycloak-themes-26.4.6.jar
```

For more details, refer to the [Keycloak Theme Development Guide](https://www.keycloak.org/docs/latest/server_development/index.html#_themes).

## 4. Theme Structure

The chapsmind theme includes the following custom templates and resources:

### Templates (FreeMarker)

| File                        | Description                                             |
| --------------------------- | ------------------------------------------------------- |
| `template.ftl`              | Base layout with semantic HTML structure                |
| `login.ftl`                 | Standard username/password login                        |
| `login-username.ftl`        | Identity-first login (username only, for organizations) |
| `login-reset-password.ftl`  | Password reset request form                             |
| `login-update-password.ftl` | New password form after reset                           |
| `select-organization.ftl`   | Organization selection for multi-org users              |
| `error.ftl`                 | Error page display                                      |
| `header.ftl`                | Shared header with logo and title                       |
| `footer.ftl`                | Shared footer with legal links                          |
| `field.ftl`                 | Reusable form field macros                              |
| `buttons.ftl`               | Reusable button macros                                  |
| `social-providers.ftl`      | Identity provider buttons                               |
| `passkeys.ftl`              | WebAuthn/Passkey support                                |

### Resources

| Path                                 | Description                                 |
| ------------------------------------ | ------------------------------------------- |
| `resources/css/styles-chapsmind.css` | Custom CSS with brand colors and components |
| `resources/img/logo.svg`             | ChapsMind logo                              |
| `resources/img/chapsmind-text.svg`   | ChapsMind wordmark                          |
| `resources/fonts/`                   | Hanken Grotesk font files (woff2)           |

### Messages

| File                              | Description          |
| --------------------------------- | -------------------- |
| `messages/messages_en.properties` | English translations |
| `messages/messages_fr.properties` | French translations  |

### CSS Variables

The theme uses CSS custom properties for consistent styling:

```css
--brand-green: #2c6f52;
--brand-dark: #445556;
--error-color: #ad3739;
--error-bg: #ffefef;
--error-border: #852527;
--border-radius-lg: 24px;
```

## 5. Building and Deploying Theme via CI/CD

### Production Theme Packaging

The chapsmind theme is automatically compiled and packaged as a JAR archive through our GitLab CI pipeline. This approach is recommended for production deployments as it provides versioned, portable theme packages.

#### Automated Build Process

The CI pipeline includes a `build-keycloak-theme` job that:

1. **Creates the proper JAR structure** with themes in the correct directory hierarchy
2. **Generates the theme manifest** (`META-INF/keycloak-themes.json`) that lists available themes
3. **Packages everything** into a versioned JAR archive
4. **Publishes to GitLab Package Registry** for easy distribution

#### Versioning Strategy

- **Main branch**: Creates packages tagged as `main` for production use
- **Feature branches**: Creates packages tagged with the branch name for testing

#### Package Registry

Theme packages are available in the GitLab Package Registry at:

```
${CI_API_V4_URL}/projects/${CI_PROJECT_ID}/packages/generic/chapsmind-keycloak-theme/{version}/chapsmind-keycloak-theme-{version}.jar
```

#### Production Deployment

To deploy the theme in production:

1. **Download the theme JAR** from the package registry
2. **Copy to Keycloak providers directory**:
    ```bash
    cp chapsmind-keycloak-theme-main.jar /opt/keycloak/providers/
    ```
3. **Restart Keycloak** to load the new theme
4. **Configure the realm** to use the `chapsmind-theme` login theme

#### Theme Manifest

The theme package includes a `META-INF/keycloak-themes.json` file that defines:

```json
{
    "themes": [
        {
            "name": "chapsmind-theme",
            "types": ["login"]
        }
    ]
}
```

This tells Keycloak that the package contains a theme named `chapsmind-theme` that provides login page customization.
