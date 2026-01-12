# Test Suite Documentation

## Overview

This test suite provides comprehensive coverage for the Mazeloot platform, including:
- Authentication and authorization
- CRUD operations for all domain entities
- Security vulnerabilities
- Integration workflows
- File uploads and media management

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
# Authentication tests
php artisan test --filter AuthenticationTest

# Authorization tests
php artisan test --filter AuthorizationTest

# Security tests
php artisan test --filter SecurityTest

# Project CRUD tests
php artisan test --filter ProjectCrudTest

# Collection tests
php artisan test --filter CollectionTest

# Media tests
php artisan test --filter MediaTest

# Integration tests
php artisan test --filter WorkflowTest
```

### Run with Coverage
```bash
php artisan test --coverage
```

## Test Structure

### Feature Tests
Located in `tests/Feature/`:
- **Auth/** - Authentication and authorization
- **Projects/** - Project management
- **Collections/** - Collection management
- **Media/** - Media upload and management
- **Integration/** - End-to-end workflows

### Unit Tests
Located in `tests/Unit/`:
- **Services/** - Business logic
- **Jobs/** - Background job processing
- **Domains/Memora/** - Domain-specific logic

### Security Tests
Located in `tests/Security/`:
- Rate limiting
- SQL injection protection
- XSS protection
- Path traversal protection
- SSRF protection
- Mass assignment protection
- API key security

## Test Coverage

### Authentication & Authorization
- ✅ User registration
- ✅ User login/logout
- ✅ Email verification
- ✅ Password reset
- ✅ Magic link authentication
- ✅ OAuth integration
- ✅ Token-based authentication
- ✅ API key authentication
- ✅ Authorization checks
- ✅ Resource ownership validation

### Projects
- ✅ Create project
- ✅ List projects
- ✅ View project
- ✅ Update project
- ✅ Delete project
- ✅ Project phases (selection, proofing, collection)
- ✅ Project settings

### Collections
- ✅ Create collection
- ✅ List collections
- ✅ Update collection
- ✅ Publish collection
- ✅ Password protection
- ✅ Public access
- ✅ Draft access control

### Media
- ✅ Upload media
- ✅ List media
- ✅ Delete media
- ✅ File type validation
- ✅ File size validation
- ✅ Authorization checks

### Security
- ✅ Rate limiting
- ✅ CORS protection
- ✅ SQL injection protection
- ✅ XSS protection
- ✅ Path traversal protection
- ✅ SSRF protection
- ✅ Mass assignment protection
- ✅ API key security
- ✅ Password reset timing attack
- ✅ Sensitive data exposure

## Writing New Tests

### Example: Feature Test
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_example(): void
    {
        $response = $this->getJson('/api/v1/endpoint');
        $response->assertStatus(200);
    }
}
```

### Example: Unit Test
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ExampleService;

class ExampleServiceTest extends TestCase
{
    public function test_service_method(): void
    {
        $service = new ExampleService();
        $result = $service->method();
        $this->assertNotNull($result);
    }
}
```

## Test Data

Tests use factories for consistent test data:
- `UserFactory` - User accounts
- `ProjectFactory` - Projects
- `CollectionFactory` - Collections
- `MediaFactory` - Media items

## Continuous Integration

Tests should pass before merging:
1. All feature tests
2. All unit tests
3. All security tests
4. Code coverage > 80%

## Troubleshooting

### Database Issues
Tests use `RefreshDatabase` trait which resets the database between tests.

### Authentication Issues
Use `actingAs()` helper or create tokens manually:
```php
$user = User::factory()->create();
$token = $user->createToken('test-token')->plainTextToken;
$response = $this->getJson('/api/endpoint', [
    'Authorization' => "Bearer {$token}",
]);
```

### File Upload Issues
Use `UploadedFile::fake()` for testing:
```php
$file = UploadedFile::fake()->image('photo.jpg');
$response = $this->postJson('/api/upload', ['file' => $file]);
```
