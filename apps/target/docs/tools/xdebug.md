# Xdebug Configuration Guide

This guide covers configuring Xdebug 3 for debugging PHP applications in the Basil project using Docker and various IDEs.

## 🔧 Xdebug Overview

Xdebug is a powerful debugging and profiling tool for PHP that allows you to:

- Set breakpoints and step through code
- Inspect variables and call stacks
- Profile application performance
- Generate code coverage reports
- Trace function calls

## 🐳 Docker Configuration

### Xdebug in Docker Container

Xdebug is pre-configured in the API Docker container. The configuration is located in:

```dockerfile
# api/Dockerfile (development stage)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
```

### Xdebug Configuration File

The Xdebug configuration is set via environment variables in `docker/api/xdebug.ini`:

```ini
; Xdebug 3 configuration
xdebug.mode=debug,coverage,profile
xdebug.start_with_request=trigger
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.idekey=PHPSTORM
xdebug.log=/tmp/xdebug.log
xdebug.log_level=7

; Step debugging
xdebug.step_debug=1
xdebug.remote_enable=1

; Profiling (optional)
xdebug.profiler_enable_trigger=1
xdebug.profiler_output_dir=/tmp/xdebug_profiles

; Coverage (for testing)
xdebug.coverage_enable=1
```

### Environment Variables

In your `compose.yaml`, Xdebug is configured through environment variables:

```yaml
services:
  api:
    environment:
      # Xdebug configuration
      XDEBUG_MODE: debug,coverage
      XDEBUG_CONFIG: >-
        client_host=host.docker.internal
        client_port=9003
        idekey=PHPSTORM
        start_with_request=trigger
        log=/tmp/xdebug.log
        log_level=7
```

### Host Network Configuration

For different operating systems, you may need to adjust the client host:

**Docker Desktop (Windows/macOS):**

```yaml
XDEBUG_CONFIG: client_host=host.docker.internal
```

**Linux with Docker Engine:**

```yaml
XDEBUG_CONFIG: client_host=172.17.0.1
```

**Custom Network:**

```bash
# Find your host IP from container
docker compose exec api ip route show default | awk '/default/ {print $3}'
```

## 🎯 IDE Configuration

### PhpStorm Setup

#### 1. Configure PHP Interpreter

1. **File > Settings > PHP**
2. Click **"..."** next to CLI Interpreter
3. Add **Docker Compose** interpreter:
   - **Server**: Your Docker server
   - **Configuration files**: `./compose.yaml`
   - **Service**: `api`
4. Verify Xdebug is detected in interpreter info

#### 2. Configure Debug Settings

1. **File > Settings > PHP > Debug**
2. Set configuration:
   - **Debug port**: `9003`
   - **Can accept external connections**: ✓
   - **Max simultaneous connections**: `5`
   - **Break at first line in PHP scripts**: ✗
   - **Force break at first line when no path mapping specified**: ✗
   - **Force break at first line when a script is outside the project**: ✗

#### 3. Configure Server

1. **File > Settings > PHP > Servers**
2. Create new server:
   - **Name**: `Basil Local`
   - **Host**: `basil.local`
   - **Port**: `443`
   - **Debugger**: `Xdebug`
   - **Use path mappings**: ✓
   - **Path mappings**:
     - Project files: `{project_root}/api` → `/var/www/html`

#### 4. Create Debug Configuration

1. **Run > Edit Configurations**
2. Add **PHP Web Page**:
   - **Name**: `Basil Debug`
   - **Server**: `Basil Local`
   - **Start URL**: `/`
   - **Browser**: Chrome/Firefox

### VS Code Setup

#### 1. Install PHP Debug Extension

```bash
# Install PHP Debug extension
code --install-extension felixfbecker.php-debug
```

#### 2. Configure Launch.json

Create `.vscode/launch.json`:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/var/www/html": "${workspaceFolder}/api"
      },
      "ignore": ["**/vendor/**/*.php"]
    },
    {
      "name": "Launch currently open script",
      "type": "php",
      "request": "launch",
      "program": "${file}",
      "cwd": "${fileDirname}",
      "port": 9003
    }
  ]
}
```

#### 3. Configure Settings

Create `.vscode/settings.json`:

```json
{
  "php.debug.executablePath": "/usr/bin/php",
  "php.validate.executablePath": "/usr/bin/php",
  "php.suggest.basic": false
}
```

### Vim/Neovim Setup

#### Using Vimspector

Install and configure Vimspector:

```vim
" .vimspector.json
{
  "configurations": {
    "PHP Debug": {
      "adapter": "vscode-php-debug",
      "configuration": {
        "name": "Listen for Xdebug",
        "type": "php",
        "request": "launch",
        "port": 9003,
        "pathMappings": {
          "/var/www/html": "${workspaceRoot}/api"
        }
      }
    }
  }
}
```

## 🌐 Browser Configuration

### Browser Extensions

Install Xdebug browser extensions:

**Chrome:**

- [Xdebug Helper](https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc)

**Firefox:**

- [Xdebug Helper](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-for-firefox/)

**Edge:**

- [Xdebug Helper](https://microsoftedge.microsoft.com/addons/detail/xdebug-helper/ggnngifabofaddiejjeagbaebkejomen)

### Extension Configuration

1. **Right-click extension icon > Options**
2. **IDE Key**: Set to `PHPSTORM` (or your IDE's key)
3. **Domain**: Add `basil.local`

### Manual Triggering

If not using browser extensions, add these parameters to any URL:

```bash
# Trigger debugging with GET parameter
https://basil.local/api/users?XDEBUG_SESSION_START=PHPSTORM

# Or with cookie
curl -H "Cookie: XDEBUG_SESSION=PHPSTORM" https://basil.local/api/users
```

## 🔍 Debugging Workflow

### Basic Debugging Steps

1. **Set Breakpoints**:
   - Click in the gutter next to line numbers
   - Or use `Ctrl+F8` (Cmd+F8) in PhpStorm

2. **Start Listening**:
   - Click "Start Listening for PHP Debug Connections" (phone icon)
   - Or use **Run > Start Listening for PHP Debug Connections**

3. **Trigger Debug Session**:
   - Enable browser extension
   - Or add `?XDEBUG_SESSION_START=PHPSTORM` to URL
   - Make request to your application

4. **Debug Session**:
   - Execution stops at breakpoints
   - Use step controls to navigate
   - Inspect variables in debug panel

### Debug Controls

**Step Commands:**

- **Step Over (F8)**: Execute current line, don't enter functions
- **Step Into (F7)**: Enter function calls
- **Step Out (Shift+F8)**: Exit current function
- **Resume (F9)**: Continue execution to next breakpoint

### Variable Inspection

**In PhpStorm:**

- **Variables panel**: Shows local variables and their values
- **Watches**: Add expressions to monitor
- **Evaluate**: Run PHP expressions in current context

**Quick evaluation:**

- Select variable/expression
- Press `Alt+F8` (PhpStorm) to evaluate

## 🧪 Testing with Xdebug

### PHPUnit with Coverage

Run tests with code coverage:

```bash
# Generate coverage report
docker compose exec api php vendor/bin/phpunit --coverage-html var/coverage

# View coverage report
open api/var/coverage/index.html
```

### Debugging Tests

1. **Set breakpoints** in test methods or application code
2. **Start debug listener** in IDE
3. **Run tests with debug trigger**:

```bash
# Debug specific test
docker compose exec api php -dxdebug.start_with_request=yes vendor/bin/phpunit tests/Unit/Service/UserServiceTest.php

# Or with environment variable
docker compose exec -e XDEBUG_SESSION=PHPSTORM api php vendor/bin/phpunit tests/Unit/Service/UserServiceTest.php
```

### Remote Debugging Tests

For debugging tests in CI or remote environments:

```bash
# Enable Xdebug for tests
export XDEBUG_MODE=debug,coverage
export XDEBUG_CONFIG="client_host=your-machine-ip client_port=9003"

# Run tests
php vendor/bin/phpunit
```

## 📊 Profiling

### Enable Profiling

Add to your environment or URL:

```bash
# Profile with trigger
https://basil.local/api/users?XDEBUG_PROFILE=1

# Or set environment variable
XDEBUG_CONFIG="profiler_enable=1"
```

### Analyze Profiles

1. **Profiles location**: `/tmp/xdebug_profiles` in container
2. **Copy to host**:

```bash
docker compose exec api ls /tmp/xdebug_profiles
docker compose cp api:/tmp/xdebug_profiles ./profiles
```

3. **Analyze with tools**:
   - **KCacheGrind** (Linux/Windows)
   - **QCacheGrind** (macOS)
   - **Webgrind** (Web-based)

## 🚨 Troubleshooting

### Common Issues

#### 1. Xdebug Not Connecting

**Check Xdebug is loaded:**

```bash
docker compose exec api php -m | grep xdebug
```

**Check Xdebug configuration:**

```bash
docker compose exec api php -i | grep xdebug
```

**Verify network connectivity:**

```bash
# Test from container to host
docker compose exec api nc -zv host.docker.internal 9003

# Check if IDE is listening
netstat -an | grep 9003
```

#### 2. Path Mapping Issues

**Verify paths in IDE:**

- Project path: `/path/to/basil/api`
- Container path: `/var/www/html`

**Check actual paths:**

```bash
# In container
docker compose exec api pwd
docker compose exec api ls -la
```

#### 3. Port Conflicts

**Check port usage:**

```bash
# Windows
netstat -an | findstr :9003

# macOS/Linux
lsof -i :9003
```

**Change Xdebug port:**

```yaml
# In compose.yaml
XDEBUG_CONFIG: client_port=9004
```

#### 4. Performance Issues

**Disable Xdebug when not needed:**

```bash
# Disable for specific command
docker compose exec -e XDEBUG_MODE=off api php bin/console cache:clear
```

**Or modify compose.yaml:**

```yaml
environment:
  XDEBUG_MODE: 'off' # Default off, enable when needed
```

### Debug Logs

**Check Xdebug logs:**

```bash
# View Xdebug log
docker compose exec api tail -f /tmp/xdebug.log

# Enable verbose logging
XDEBUG_CONFIG="log_level=7"
```

**Sample log entries:**

```
[2024-01-15 10:30:00] Log opened at 2024-01-15 10:30:00.123456
[2024-01-15 10:30:00] I: Connecting to configured address/port: host.docker.internal:9003.
[2024-01-15 10:30:00] I: Connected to client. :-)
[2024-01-15 10:30:00] -> <init>
[2024-01-15 10:30:00] <- breakpoint_set -i 1 -t line -f file:///var/www/html/src/Controller/UserController.php -n 25
```

## ⚡ Performance Optimization

### Selective Debugging

Only enable Xdebug when needed:

```bash
# Environment-based enabling
if [ "$APP_ENV" = "dev" ] && [ "$ENABLE_XDEBUG" = "1" ]; then
    export XDEBUG_MODE=debug
else
    export XDEBUG_MODE=off
fi
```

### IDE Performance

**PhpStorm optimizations:**

1. **Exclude vendor directories** from indexing
2. **Limit max simultaneous connections** to 2-3
3. **Disable "Break at first line"** options
4. **Use conditional breakpoints** sparingly

### Docker Performance

**Optimize container:**

```dockerfile
# Only install Xdebug in development
ARG INSTALL_XDEBUG=false
RUN if [ ${INSTALL_XDEBUG} = true ]; then \
    pecl install xdebug && docker-php-ext-enable xdebug; \
fi
```

## 🎯 Advanced Configuration

### Conditional Breakpoints

Set breakpoints that only trigger under specific conditions:

**PhpStorm:**

1. Right-click breakpoint
2. Add condition: `$userId === 123`
3. Breakpoint only triggers when condition is true

### Remote Debugging

For debugging on remote servers:

```ini
; Remote server Xdebug config
xdebug.mode=debug
xdebug.client_host=your-local-ip
xdebug.client_port=9003
xdebug.start_with_request=trigger
```

**SSH Tunnel:**

```bash
# Create tunnel from remote to local
ssh -R 9003:localhost:9003 user@remote-server
```

### Xdebug with Multiple Services

When debugging multiple PHP services:

```yaml
# Service 1
api:
  environment:
    XDEBUG_CONFIG: 'client_port=9003 idekey=API'

# Service 2
worker:
  environment:
    XDEBUG_CONFIG: 'client_port=9004 idekey=WORKER'
```

### Custom Debug Modes

Configure different debug modes for different scenarios:

```bash
# Development debugging
XDEBUG_MODE=debug

# Coverage testing
XDEBUG_MODE=coverage

# Performance profiling
XDEBUG_MODE=profile

# All features
XDEBUG_MODE=debug,coverage,profile
```

## 📚 Best Practices

### Development Workflow

1. **Enable Xdebug only when debugging**
2. **Use meaningful breakpoint conditions**
3. **Remove breakpoints before committing**
4. **Use logging for production debugging**
5. **Profile before optimizing**

### Security Considerations

1. **Never enable Xdebug in production**
2. **Restrict debug access to development IPs**
3. **Use secure debug triggers**
4. **Monitor debug log access**

### Performance Tips

1. **Disable Xdebug for CLI commands when not needed**
2. **Use trigger-based debugging rather than always-on**
3. **Limit debug session duration**
4. **Close debug connections when finished**

This Xdebug configuration guide provides comprehensive setup and troubleshooting information for effective PHP debugging in the Basil project across different development environments and IDEs.
