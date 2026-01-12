# Security Testing Guide

## Overview

This document outlines security measures implemented and how to test them.

## Security Fixes Applied

### 1. CORS Configuration
- **Issue**: Wildcard origins (`*`) with credentials enabled
- **Fix**: Environment-based origin whitelist
- **Config**: `CORS_ALLOWED_ORIGINS` in `.env`
- **Test**: Verify only allowed origins can make authenticated requests

### 2. Rate Limiting
- **Issue**: No rate limiting on auth endpoints
- **Fix**: Added throttling to all authentication routes
  - Login: 5 attempts per minute
  - Register: 3 attempts per minute
  - Password reset: 3 attempts per minute
  - File uploads: 20 per minute
- **Test**: Run `php artisan test --filter SecurityTest::test_rate_limiting`

### 3. API Key Security
- **Issue**: API keys accepted in query strings (logged in server logs)
- **Fix**: Only accept API keys in `X-API-Key` header
- **Test**: Verify query string API keys are rejected

### 4. Security Headers
- **Added**: Content Security Policy, Permissions Policy
- **Enhanced**: HSTS with preload
- **Test**: Check response headers for security headers

### 5. Password Reset Timing Attack
- **Issue**: Different response times for existing/non-existing users
- **Fix**: Always perform reset attempt regardless of user existence
- **Test**: Measure response times for both cases

## Running Security Tests

```bash
# Run all security tests
php artisan test --filter SecurityTest

# Run specific test
php artisan test --filter SecurityTest::test_rate_limiting_on_login
```

## Manual Security Testing

### 1. CORS Testing
```bash
# Test from allowed origin
curl -H "Origin: https://yourdomain.com" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS https://api.yourdomain.com/api/v1/auth/login

# Test from disallowed origin (should fail)
curl -H "Origin: https://evil.com" \
     -H "Access-Control-Request-Method: POST" \
     -X OPTIONS https://api.yourdomain.com/api/v1/auth/login
```

### 2. Rate Limiting Testing
```bash
# Attempt login 6 times rapidly (should be rate limited)
for i in {1..6}; do
  curl -X POST https://api.yourdomain.com/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com","password":"wrong"}'
done
```

### 3. SQL Injection Testing
```bash
# Test with SQL injection attempt
curl -X POST https://api.yourdomain.com/api/v1/memora/projects \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"'\''; DROP TABLE users; --","description":"test"}'
```

### 4. XSS Testing
```bash
# Test with XSS attempt
curl -X POST https://api.yourdomain.com/api/v1/memora/projects \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"<script>alert(\"xss\")</script>","description":"test"}'
```

### 5. File Upload Security
```bash
# Test oversized file
curl -X POST https://api.yourdomain.com/api/v1/uploads \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@largefile.jpg"

# Test malicious file type
curl -X POST https://api.yourdomain.com/api/v1/uploads \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@malicious.php"
```

## Environment Configuration

Add to `.env`:
```env
# CORS Configuration
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
CORS_SUPPORTS_CREDENTIALS=true
```

## Security Checklist

- [x] CORS properly configured
- [x] Rate limiting on auth endpoints
- [x] API keys only in headers
- [x] Security headers set
- [x] Password reset timing attack fixed
- [x] File upload validation
- [x] Path traversal protection
- [x] SSRF protection for file downloads
- [x] SQL injection protection (Eloquent ORM)
- [x] XSS protection (Laravel auto-escaping)
- [x] CSRF protection on web routes
- [x] Authentication required for protected routes
- [x] Authorization checks on resources

## Additional Recommendations

1. **Enable HTTPS only** in production
2. **Set secure session cookies** (`SESSION_SECURE_COOKIE=true`)
3. **Use strong password requirements** (already implemented)
4. **Implement 2FA** for sensitive operations
5. **Regular security audits** with tools like:
   - OWASP ZAP
   - Burp Suite
   - Laravel Security Checker: `composer require enlightn/security-checker`
