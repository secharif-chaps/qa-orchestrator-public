# API & User Interface Standards

## API Platform Best Practices

### Symfony Validator, Not Manual JSON

Let API Platform handle deserialization/validation:

```php
// ❌ Bad: manual parsing
$data = json_decode($request->getContent(), true);
Assert::keyExists($data, 'status');

// ✅ Good: let API Platform deserialize + validate
#[Post(input: CreateResourceDto::class)]
```

### API Platform Serialization Pipeline

Keep `deserialize`/`validate` enabled by default:

```php
#[Post(
    deserialize: true,  // ✅ Default behavior
    validate: true,     // ✅ Default behavior
)]
```

### EnumConstraint for DTO Choices

Use shared Enum constraint instead of `in_array` checks:

```php
use App\Infrastructure\Shared\Constraint\EnumConstraint;

#[EnumConstraint(enumClass: ResourceStatus::class)]
public string $status;
```

---

## Authorization

### Processor-Level Auth Checks

Processors do not extend `AbstractController` — use `Security::isGranted()` + throw `AccessDeniedHttpException`:

```php
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

public function process(mixed $data, Operation $operation, array $uriVariables = []): mixed
{
    if (!$this->security->isGranted('EDIT', $data)) {
        throw new AccessDeniedHttpException('You do not have permission to edit this resource.');
    }
    return $this->handle(new UpdateResourceAction($data->getId()));
}
```

### Security Grant for Target User

Use `Security::isGrantedForUser()` when verifying another user's permissions:

```php
if (!$this->security->isGrantedForUser($targetUser, 'EDIT', $resource)) {
    throw new AccessDeniedHttpException();
}
```

---

## Error Handling

### Controlled Error Payload Keys

Emit a single, predictable error key:

```php
return new JsonResponse(['detail' => 'Resource not found'], 404);
```

### Specific Not-Found Messages

Mention the missing identifier in errors:

```php
throw new NotFoundHttpException("Resource {$resourceId} not found");
```

---

## Locale Handling

### Translator Locale Auto-Detection

Rely on Symfony's locale negotiation instead of manual parsing:

```php
// ❌ Bad: manual header parsing
$locale = $request->headers->get('Accept-Language');

// ✅ Good: let Symfony handle it
public function __construct(private readonly LocaleResolver $localeResolver) {}
```

---

## API Documentation

### Document API Endpoints

Keep API Platform resource docs up-to-date:

```php
#[ApiResource(
    description: 'Manages aggregate resources with CRUD operations',
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new Post(security: "is_granted('CREATE')"),
    ]
)]
```

---

## HTTP Methods & Status Codes

### HTTP Methods

| Method | Purpose         |
| ------ | --------------- |
| GET    | Retrieve data   |
| POST   | Create resource |
| PUT    | Full update     |
| PATCH  | Partial update  |
| DELETE | Remove resource |

### HTTP Status Codes

| Code | Meaning              |
| ---- | -------------------- |
| 200  | Success              |
| 201  | Created              |
| 204  | No Content           |
| 400  | Bad Request          |
| 403  | Forbidden            |
| 404  | Not Found            |
| 422  | Unprocessable Entity |

---

## RESTful Design

- **Plural Nouns**: Use plural nouns for resource endpoints (`/users`, `/watch_files`)
- **Nested Resources**: Limit nesting depth to 2-3 levels maximum
- **Query Parameters**: Use query parameters for filtering, sorting, pagination
- **Consistent Naming**: Use consistent, lowercase, snake_case naming

---

## Response Handling

### Stable Response API Usage

Interact with API Platform responses via `$client->request()` return values:

```php
// ✅ Good: use response object directly
$response = $client->request('GET', '/api/resources');
$data = $response->toArray();
```
