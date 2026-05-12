# OpenSearch Migration System

This document describes the OpenSearch migration system that provides version control for OpenSearch index schemas, similar to Symfony's Doctrine migrations.

## Overview

The migration system is designed to be **simple and declarative**, just like database migrations. You simply declare what you want to do with your indices, and the system handles the complexity behind the scenes.

**Important**: Unlike SQL migrations that can update existing table schemas, OpenSearch migrations require **rewriting the entire index schema**. When you "update" an index, the system actually creates a completely new version of the index with the new schema and migrates all data to it.

## Key Principles

1. **Simple Declarations**: Migrations just say "create this index", "update this index", or "delete this index"
2. **Automatic Versioning**: The system handles all versioning complexity automatically
3. **Zero Downtime**: Uses blue-green deployment pattern with aliases
4. **Easy Rollbacks**: Each migration can be rolled back safely
5. **Versioning Agnostic**: Migrations are completely unaware of versioning - they just work with index names
6. **Transactional**: All operations are executed transactionally with proper error handling and cleanup

## Migration Types

### 1. Create Index

```php
public function up(): void
{
    $this->createIndex('document', [
        'mappings' => [
            'properties' => [
                'id' => ['type' => 'keyword'],
                'title' => ['type' => 'text'],
            ],
        ],
    ]);
}

public function down(): void
{
    $this->deleteIndex('document');
}
```

### 2. Update Index

```php
public function up(): void
{
    $this->updateIndex('document', [
        'mappings' => [
            'properties' => [
                'newField' => ['type' => 'keyword'],
            ],
        ],
    ]);
}

public function down(): void
{
    // you should play here the last migration
    $this->updateIndex('document', [
    //last migration mapping
    ]);
}
```

### 3. Multiple Indices

```php
public function up(): void
{
    $this->createIndex('user', [...]);
    $this->createIndex('profile', [...]);
}

public function down(): void
{
    $this->deleteIndex('user');
    $this->deleteIndex('profile');
}
```

## Available Methods

### AbstractOpenSearchMigration Methods

- `createIndex(string $name, array $config)`: Create a new index
- `updateIndex(string $name, array $config)`: Update existing index settings/mappings
- `deleteIndex(string $name)`: Delete an index

**Warning**: The `updateIndex` method does not modify the existing index in-place. Instead, it creates a completely new index with the updated schema and migrates all data to it, then switches the alias. This is due to OpenSearch's limitation that prevents altering existing index mappings.

**Note**: These methods are completely versioning-agnostic. You just specify the index name (e.g., 'document', 'user'), and the system handles all versioning complexity automatically.

## Usage

### Generate a New Migration

```bash
docker compose exec api php bin/console elasticsearch:migrations:generate "Add tags field to potato index"
```

### Check Migration Status

```bash
# Show all migrations
docker compose exec api php bin/console elasticsearch:migrations:migrate --status

# Show index status
docker compose exec api php bin/console elasticsearch:index:status
```

### Execute Migrations

```bash
# Execute all pending migrations (interactive - asks for confirmation)
docker compose exec api php bin/console elasticsearch:migrations:migrate

# Execute migrations without confirmation (useful for CI/CD)
docker compose exec api php bin/console elasticsearch:migrations:migrate --force

# Dry run (see what would be executed)
docker compose exec api php bin/console elasticsearch:migrations:migrate --dry-run

# Dry run with force (shows what would be executed without confirmation)
docker compose exec api php bin/console elasticsearch:migrations:migrate --dry-run --force
```

### Rollback Migrations

```bash
# Rollback the last migration
docker compose exec api php bin/console elasticsearch:migrations:migrate --rollback
```

## How It Works

### Key Difference from SQL Migrations

**SQL Migrations**: Can modify existing table schemas (add columns, change types, etc.) without recreating the table.

**OpenSearch Migrations**: Cannot modify existing index schemas. Any schema change requires:

1. Creating a completely new index with the new schema
2. Copying all data from the old index to the new index
3. Switching the alias to point to the new index
4. Deleting the old index

This is a fundamental limitation of OpenSearch - you cannot alter the mapping of an existing index.

### Behind the Scenes

1. **Migration Execution**: Your migration runs and declares operations (create/update/delete)
2. **Automatic Versioning**: The system creates versioned indices (e.g., `potato_version20250902095025`)
3. **Alias Management**: Aliases are automatically managed to point to the correct version
4. **Data Migration**: If updating, data is automatically copied to the new version
5. **Error Handling**: Comprehensive error handling with automatic cleanup on failure
6. **Cleanup**: Old versions are automatically cleaned up

### Example Flow

1. **Migration 1**: Creates `potato` index -> System creates `potato_version20250902095025` and points alias to it
2. **Migration 2**: Updates `potato` index -> System creates `potato_version20250902095026`, copies data, switches alias, deletes old version
3. **Migration 3**: Adds field to `potato` -> System creates `potato_version20250902095027`, copies data, switches alias, deletes old version

### Error Handling & Recovery

The system includes comprehensive error handling:

- **Index Creation**: Checks for duplicates, handles creation failures
- **Data Copying**: Automatic cleanup if data migration fails
- **Alias Switching**: Rollback to previous state if alias switch fails
- **Cleanup**: Non-critical cleanup failures don't break the operation
- **Logging**: Detailed logging at each step for debugging

## Field Type Considerations

### Nested vs Object Fields

When designing your index mappings in migrations, it's crucial to understand the difference between `nested` and `object` field types, as this choice significantly impacts query behavior and performance.

#### Object Fields (Default)

Object fields are the default when you don't specify a type for complex data structures:

```php
'mappings' => [
    'properties' => [
        'user' => [ // This is an object field by default
            'properties' => [
                'name' => ['type' => 'text'],
                'age' => ['type' => 'integer'],
            ],
        ],
    ],
],
```

**Characteristics:**

- **Flattened Storage**: All properties are stored as separate fields at the root level
- **Cross-Field Queries**: Queries can match across different object instances
- **Performance**: Generally faster for simple queries
- **Memory Usage**: Lower memory footprint

**Example Query Behavior:**

```json
// This query will match documents where ANY user has name="John" AND age=25
// Even if these values come from different user objects
{
  "query": {
    "bool": {
      "must": [{ "term": { "user.name": "John" } }, { "term": { "user.age": 25 } }]
    }
  }
}
```

#### Nested Fields

Nested fields preserve the object structure and treat each nested object as a separate document:

```php
'mappings' => [
    'properties' => [
        'users' => [
            'type' => 'nested',
            'properties' => [
                'name' => ['type' => 'text'],
                'age' => ['type' => 'integer'],
            ],
        ],
    ],
],
```

**Characteristics:**

- **Preserved Structure**: Each nested object is stored as a separate internal document
- **Isolated Queries**: Queries only match within the same nested object
- **Performance**: Slower for simple queries due to additional overhead
- **Memory Usage**: Higher memory footprint

**Example Query Behavior:**

```json
// This query will ONLY match documents where the SAME user object has name="John" AND age=25
{
  "query": {
    "nested": {
      "path": "users",
      "query": {
        "bool": {
          "must": [{ "term": { "users.name": "John" } }, { "term": { "users.age": 25 } }]
        }
      }
    }
  }
}
```

#### When to Use Each Type

**Use Object Fields When:**

- You need to query across different object instances
- Performance is critical for simple queries
- You have simple, flat data structures
- You don't need to maintain object boundaries

**Use Nested Fields When:**

- You need to maintain object boundaries in queries
- You have arrays of complex objects that should be treated as separate entities
- You need to perform aggregations on nested objects
- Data integrity within objects is important

**API Platform Limitation**: API Platform's native filter system only supports `object` field types. If you use `nested` fields, you'll need to implement custom filters or query logic to properly handle nested queries.

#### Migration Considerations

**Important**: Changing between `object` and `nested` types requires a complete index recreation, as this is a fundamental structural change that cannot be done in-place.

**Example Migration:**

```php
// Migration to convert object to nested
public function up(): void
{
    $this->updateIndex('document', [
        'mappings' => [
            'properties' => [
                'tags' => [ // Was object, now nested
                    'type' => 'nested',
                    'properties' => [
                        'name' => ['type' => 'keyword'],
                        'value' => ['type' => 'text'],
                    ],
                ],
            ],
        ],
    ]);
}
```

**Data Impact:**

- Existing data will be migrated to the new structure
- Query behavior will change significantly
- Application code may need updates to handle the new query patterns
- Performance characteristics will change

**Testing Strategy:**

1. Test queries with both object and nested structures
2. Verify that existing application queries still work as expected
3. Consider the performance impact of the change
4. Update application code if necessary to use `nested` queries

## Best Practices

### 1. Keep Migrations Simple

- Use simple index names (e.g., 'document', 'user')
- Don't handle versioning manually - the system does it automatically

### 2. Always Implement Down Methods

- If you create an index, delete it in `down()`
- If you update an index, the system automatically reverts to the previous version

### 3. Test Your Migrations

```bash
# Test migration and rollback
docker compose exec api php bin/console elasticsearch:migrations:migrate
docker compose exec api php bin/console elasticsearch:migrations:migrate --rollback
```

## Troubleshooting

### Common Issues

1. **Migration fails during execution**: Check OpenSearch logs and ensure sufficient disk space
2. **Rollback issues**: Ensure your `down()` method properly reverses the `up()` method
3. **Field type conflicts**: Some field type changes require index recreation
4. **Performance impact**: Remember that "updating" an index actually recreates it entirely, which can be resource-intensive for large indices
5. **Mapping changes**: Any change to field mappings (type, analyzer, etc.) requires a complete index recreation - this is an OpenSearch limitation, not a migration system limitation

### Debugging

- Check migration status: `elasticsearch:migrations:migrate --status`
- Check index status: `elasticsearch:index:status`
- Check OpenSearch logs: `docker compose logs opensearch`
- Verify indices: `docker compose exec opensearch curl -X GET "localhost:9200/_cat/indices?v"`

## Integration with Application

### Using Indices in Code

Always use the alias name in your application code:

```php
// Correct - uses alias
$response = $elasticsearchClient->search([
    'index' => 'document', // This is the alias
    'body' => $query,
]);

// Wrong - never use versioned names
$response = $elasticsearchClient->search([
    'index' => 'document_version20250902095025', // Don't do this
    'body' => $query,
]);
```

### DataFixtures Integration

Your DataFixtures should use the alias name and run migrations first if needed.

## Summary

This migration system provides:

- **Simplicity**: Just declare what you want to do with your indices
- **Safety**: Automatic versioning and zero-downtime deployments
- **Reliability**: Proper rollback support and data integrity
- **Developer Experience**: Similar to database migrations you already know

The system handles all the complexity of OpenSearch versioning, aliases, and data migration, so you can focus on your business logic rather than infrastructure concerns.
