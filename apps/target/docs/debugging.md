# Debugging Guide

This guide covers debugging techniques, tools, and best practices for the Basil project across all development environments.

## 🔧 Environment Setup for Debugging

### Prerequisites

- Docker and Docker Compose running
- PhpStorm IDE (recommended)
- Browser developer tools
- Xdebug configured (see [Xdebug Configuration](tools/xdebug.md))

## 🐛 API Debugging (Symfony + PHP)

### Xdebug Configuration

Xdebug is pre-configured in the Docker environment. See [Xdebug setup guide](tools/xdebug.md) for detailed configuration.

### Debugging API Endpoints

**1. Enable Xdebug in Docker**

```bash
# Xdebug is enabled by default in development
# Check if it's working
docker compose exec api php -m | grep xdebug
```

**2. Set Breakpoints in PhpStorm**

- Open your PHP file
- Click in the gutter next to line numbers
- Red dots indicate active breakpoints

**3. Debug API Requests**

```bash
# Make API request with Xdebug trigger
curl -X GET "https://basil.local/api/users" \
  -H "Accept: application/json" \
  -H "Cookie: XDEBUG_SESSION=PHPSTORM"

# Or use query parameter
curl "https://basil.local/api/users?XDEBUG_SESSION_START=PHPSTORM"
```

### Using Symfony Debug Tools

**Symfony Profiler**

```bash
# Access profiler at
https://basil.local/_profiler

# View latest profiles
https://basil.local/_profiler/latest
```

**Debug Bar**
The Symfony debug toolbar appears at the bottom of pages in development mode, showing:

- Request/Response information
- Database queries
- Cache hits/misses
- Security information
- Performance metrics

**Dump Functions**

```php
<?php
// In your controller or service
use Symfony\Component\VarDumper\VarDumper;

// Simple dump
dump($variable);

// Dump and die
dd($variable);

// Advanced dump with context
VarDumper::dump($variable);
```

### Database Debugging

**Doctrine Query Logging**

```php
<?php
// Enable SQL logging in config/packages/dev/doctrine.yaml
doctrine:
    dbal:
        logging: true
        profiling_collect_backtrace: true
```

**Debug Queries**

```php
<?php
// In your repository or service
use Doctrine\DBAL\Logging\DebugStack;

$debugStack = new DebugStack();
$entityManager->getConnection()->getConfiguration()->setSQLLogger($debugStack);

// Execute your queries
$users = $userRepository->findAll();

// Check executed queries
foreach ($debugStack->queries as $query) {
    dump($query);
}
```

### API Platform Debugging

**Debug API Resources**

```bash
# List all API resources
docker compose exec api php bin/console debug:api-platform:resources

# Debug specific resource
docker compose exec api php bin/console debug:api-platform:resource User
```

**Debug OpenAPI Documentation**

```bash
# Generate OpenAPI spec
docker compose exec api php bin/console api:openapi:export --output=openapi.json
```

### Logging and Monitoring

**Custom Logging**

```php
<?php
// In your service
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function createUser(array $data): User
    {
        $this->logger->info('Creating user', ['email' => $data['email']]);

        try {
            // User creation logic
            $user = new User();

            $this->logger->info('User created successfully', ['userId' => $user->getId()]);
            return $user;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create user', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}
```

**View Logs**

```bash
# View API logs
docker compose logs api -f

# View specific log file
docker compose exec api tail -f var/log/dev.log
```

## 🎨 Frontend Debugging (Nuxt.js + Vue)

### Browser Developer Tools

**Vue DevTools**

1. Install Vue DevTools browser extension
2. Open browser developer tools
3. Navigate to Vue tab
4. Inspect components, state, and events

**Network Tab**

- Monitor API requests
- Check request/response headers
- Analyze response times
- Debug CORS issues

### Nuxt DevTools

**Enable Nuxt DevTools**

```typescript
// nuxt.config.ts
export default defineNuxtConfig({
  devtools: { enabled: true },
})
```

**Access DevTools**
Visit your application and press `Shift + Alt + D` or look for the Nuxt icon in your browser.

Features include:

- Component inspector
- Route analyzer
- Plugin inspector
- Module insights
- Performance metrics

### Console Debugging

**Basic Console Methods**

```typescript
// Basic logging
console.log('Debug info:', data)
console.warn('Warning message')
console.error('Error occurred:', error)

// Grouped logging
console.group('User Operations')
console.log('Creating user')
console.log('Validating data')
console.groupEnd()

// Table display
console.table(users)

// Performance timing
console.time('API Request')
await $fetch('/api/users')
console.timeEnd('API Request')
```

**Conditional Debugging**

```typescript
// Only log in development
if (process.dev) {
  console.log('Development debug info:', data)
}

// Custom debug function
const debug = (message: string, data?: any) => {
  if (process.env.NODE_ENV === 'development') {
    console.log(`[DEBUG] ${message}`, data)
  }
}
```

### Debugging Composables

**Debug useApi Composable**

```typescript
// composables/useApi.ts
export const useApi = () => {
  const { $fetch } = useNuxtApp()

  const getUsers = async () => {
    try {
      console.log('Fetching users...')
      const users = await $fetch('/api/users')
      console.log('Users fetched:', users)
      return users
    } catch (error) {
      console.error('Failed to fetch users:', error)
      throw error
    }
  }

  return { getUsers }
}
```

**Debug Pinia Stores**

```typescript
// stores/user.ts
export const useUserStore = defineStore('user', () => {
  const users = ref([])
  const loading = ref(false)

  const fetchUsers = async () => {
    console.log('Store: Starting user fetch')
    loading.value = true

    try {
      const { getUsers } = useApi()
      users.value = await getUsers()
      console.log('Store: Users loaded:', users.value.length)
    } catch (error) {
      console.error('Store: Failed to fetch users:', error)
    } finally {
      loading.value = false
      console.log('Store: Fetch completed, loading:', loading.value)
    }
  }

  return { users, loading, fetchUsers }
})
```

### Vue Component Debugging

**Debug Component Lifecycle**

```vue
<script setup lang="ts">
const props = defineProps<{
  userId: string
}>()

// Debug props changes
watch(
  () => props.userId,
  (newId, oldId) => {
    console.log('UserId changed:', { from: oldId, to: newId })
  },
)

// Debug component mounting
onMounted(() => {
  console.log('Component mounted with props:', props)
})

// Debug component updates
onUpdated(() => {
  console.log('Component updated')
})
</script>
```

**Debug Reactive Data**

```vue
<script setup lang="ts">
const user = ref(null)
const isLoading = ref(false)

// Watch reactive changes
watch(
  user,
  (newUser, oldUser) => {
    console.log('User changed:', { old: oldUser, new: newUser })
  },
  { deep: true },
)

watch(isLoading, (loading) => {
  console.log('Loading state:', loading)
})
</script>
```

### Network Request Debugging

**Debug $fetch Calls**

```typescript
// Create debug wrapper for $fetch
const debugFetch = async (url: string, options?: any) => {
  console.log('🚀 API Request:', { url, options })

  try {
    const response = await $fetch(url, options)
    console.log('✅ API Response:', { url, response })
    return response
  } catch (error) {
    console.error('❌ API Error:', { url, error })
    throw error
  }
}

// Use in composables
export const useApi = () => {
  const getUsers = () => debugFetch('/api/users')
  return { getUsers }
}
```

### Error Handling and Reporting

**Global Error Handling**

```typescript
// plugins/error-handler.client.ts
export default defineNuxtPlugin(() => {
  // Handle Vue errors
  const vueApp = useNuxtApp().vueApp
  vueApp.config.errorHandler = (error, context) => {
    console.error('Vue Error:', { error, context })
    // Send to error reporting service
  }

  // Handle unhandled promise rejections
  window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled Promise Rejection:', event.reason)
  })
})
```

## 🔍 Database Debugging

### PostgreSQL Debugging

**Connect to Database**

```bash
# Connect via Docker
docker compose exec postgres psql -U basil -d basil

# Or use external tool
psql -h localhost -p 5432 -U basil -d basil
```

**Useful SQL Queries**

```sql
-- Check active connections
SELECT * FROM pg_stat_activity;

-- View table sizes
SELECT schemaname, tablename,
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Check slow queries (requires log configuration)
SELECT query, mean_time, calls
FROM pg_stat_statements
ORDER BY mean_time DESC
LIMIT 10;
```

### Doctrine Debugging

**Debug Query Performance**

```php
<?php
// Enable query logging
// config/packages/dev/doctrine.yaml
doctrine:
    dbal:
        logging: true
        profiling_collect_backtrace: true

// Or programmatically
$connection = $entityManager->getConnection();
$connection->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger());
```

**Debug Entity Mapping**

```bash
# Validate mapping
docker compose exec api php bin/console doctrine:schema:validate

# Check database schema
docker compose exec api php bin/console doctrine:schema:update --dump-sql

# Debug specific entity
docker compose exec api php bin/console doctrine:mapping:info
```

## 🔧 Tool-Specific Debugging

### Valkey Debugging

**Connect to Valkey**

```bash
# Connect via Docker
docker compose exec valkey valkey-cli

# Check keys
KEYS *

# Monitor commands
MONITOR
```

### RabbitMQ Debugging

**Access Management Interface**
Visit `http://localhost:15672` (guest/guest)

**Check Queues**

```bash
# List queues
docker compose exec rabbitmq rabbitmqctl list_queues

# Check exchanges
docker compose exec rabbitmq rabbitmqctl list_exchanges
```

### OpenSearch Debugging

**Check Cluster Health**

```bash
# Via curl
curl -X GET "localhost:9200/_cluster/health?pretty"

# Check indices
curl -X GET "localhost:9200/_cat/indices?v"
```

## 🚨 Common Issues and Solutions

### API Issues

**"Class not found" Errors**

```bash
# Clear cache
docker compose exec api php bin/console cache:clear

# Dump autoload
docker compose exec api composer dump-autoload
```

**Database Connection Issues**

```bash
# Check database status
docker compose exec postgres pg_isready

# Reset database
docker compose exec api php bin/console doctrine:database:drop --force
docker compose exec api php bin/console doctrine:database:create
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

### Frontend Issues

**Node Modules Issues**

```bash
# Clear node_modules and reinstall
cd pwa
rm -rf node_modules package-lock.json
npm install
```

**Build Issues**

```bash
# Clear Nuxt cache
cd pwa
rm -rf .nuxt .output
npm run dev
```

### Docker Issues

**Container Won't Start**

```bash
# Check logs
docker compose logs [service-name]

# Rebuild containers
docker compose down
docker compose build --no-cache
docker compose up
```

**Permission Issues**

```bash
# Fix file permissions
sudo chown -R $USER:$USER .
```

## 📊 Performance Debugging

### API Performance

**Profile API Requests**

```php
<?php
// Use Symfony Stopwatch
use Symfony\Component\Stopwatch\Stopwatch;

class UserService
{
    public function __construct(
        private Stopwatch $stopwatch
    ) {}

    public function getUsers(): array
    {
        $this->stopwatch->start('user_fetch');

        // Your logic here
        $users = $this->userRepository->findAll();

        $event = $this->stopwatch->stop('user_fetch');

        // Log performance
        $this->logger->info('User fetch completed', [
            'duration' => $event->getDuration(),
            'memory' => $event->getMemory()
        ]);

        return $users;
    }
}
```

### Frontend Performance

**Monitor Core Web Vitals**

```typescript
// plugins/performance.client.ts
export default defineNuxtPlugin(() => {
  if (process.client) {
    // Monitor Largest Contentful Paint
    new PerformanceObserver((entryList) => {
      for (const entry of entryList.getEntries()) {
        console.log('LCP:', entry.startTime)
      }
    }).observe({ entryTypes: ['largest-contentful-paint'] })

    // Monitor Cumulative Layout Shift
    new PerformanceObserver((entryList) => {
      for (const entry of entryList.getEntries()) {
        console.log('CLS:', entry.value)
      }
    }).observe({ entryTypes: ['layout-shift'] })
  }
})
```

## 🎯 Remote Debugging

### VS Code Remote Debugging

**Launch Configuration**

```json
// .vscode/launch.json
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
      }
    }
  ]
}
```

### Browser Remote Debugging

**Chrome DevTools**

```bash
# Start Chrome with remote debugging
google-chrome --remote-debugging-port=9222

# Access remote DevTools
http://localhost:9222
```

This debugging guide should help you efficiently identify and resolve issues across all layers of the Basil application.
