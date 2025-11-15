# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 API application for weight logging, using Laravel Fortify for authentication and Laravel Sanctum for API token management. The project follows Laravel's streamlined file structure introduced in Laravel 11.

## Technology Stack

- **PHP**: 8.4+
- **Laravel Framework**: v12
- **Authentication**: Laravel Fortify (headless authentication)
- **API Authentication**: Laravel Sanctum (stateful SPA + token-based)
- **Testing**: Pest v4
- **Code Style**: Laravel Pint
- **Database**: SQLite (default)

## Common Commands

### Development

```bash
# Start all development services (server, queue, logs, vite)
composer run dev

# Start development server only
php artisan serve

# Watch frontend assets
npm run dev

# Build frontend assets
npm run build
```

### Testing

```bash
# Run all tests
composer test
# OR
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run tests matching a name
php artisan test --filter=testName
```

### Code Quality

```bash
# Format code (run before finalizing changes)
vendor/bin/pint --dirty
```

### Setup

```bash
# First-time setup (install deps, generate key, migrate, build assets)
composer setup
```

## Architecture & Structure

### Authentication Flow

This API uses a **stateful SPA authentication** approach optimized for first-party web applications:

1. **Laravel Fortify** provides headless authentication endpoints under the `/auth` prefix:
   - Registration: `POST /auth/register`
   - Login: `POST /auth/login`
   - Logout: `POST /auth/logout`
   - Password reset: `POST /auth/forgot-password`, `POST /auth/reset-password`
   - Profile updates: `PUT /auth/user/profile-information`, `PUT /auth/user/password`

2. **Laravel Sanctum** manages API authentication via:
   - **Cookie-based sessions** for first-party SPA (configured with `statefulApi()` middleware)
   - **Personal access tokens** for third-party API access
   - CSRF protection for stateful requests via `sanctum/csrf-cookie` endpoint

3. **Custom Fortify Actions** in `app/Actions/Fortify/`:
   - `CreateNewUser` - User registration logic
   - `UpdateUserProfileInformation` - Profile updates
   - `UpdateUserPassword` - Password changes
   - `ResetUserPassword` - Password reset logic

### CORS Configuration

CORS is configured in `config/cors.php` to allow:
- Paths: `api/*`, `sanctum/csrf-cookie`, `auth/*`
- All methods, origins, and headers (currently permissive - should be tightened for production)
- Credentials support enabled for cookie-based authentication

### Laravel 12 Structure Notes

- **No `app/Http/Middleware/`** - middleware registration happens in `bootstrap/app.php`
- **No `app/Console/Kernel.php`** - commands auto-register from `app/Console/Commands/`
- **Service providers** registered in `bootstrap/providers.php`
- **Stateful API** configured via `$middleware->statefulApi()` in `bootstrap/app.php`

### API Routes

API routes are defined in `routes/api.php` and automatically prefixed with `/api`. Example authenticated route:

```php
Route::get('/user', fn(Request $request) => $request->user())
    ->middleware('auth:sanctum');
```

### Database Schema

Key tables:
- `users` - User accounts with email/password authentication
- `personal_access_tokens` - Sanctum API tokens
- Standard Laravel cache, jobs, and queue tables

## Development Guidelines

### Follow Laravel Boost Guidelines

This project follows Laravel Boost guidelines (see `.github/copilot-instructions.md`). Key principles:

- Use `php artisan make:*` commands to create files
- Always use Form Request classes for validation (not inline validation)
- Use Eloquent relationships and eager loading to prevent N+1 queries
- Prefer `Model::query()` over `DB::`
- Use PHP 8 constructor property promotion
- Always use explicit return types and type hints
- Run `vendor/bin/pint --dirty` before finalizing changes
- Write Pest tests for all features

### Testing with Pest v4

- Tests use Pest syntax, not PHPUnit
- Create tests with: `php artisan make:test --pest <name>`
- Feature tests go in `tests/Feature/`, unit tests in `tests/Unit/`
- Pest v4 supports browser testing in `tests/Browser/`
- Use factories for test data setup
- Run minimal tests with filters during development

### API Development Patterns

When adding new API endpoints:

1. Define routes in `routes/api.php`
2. Protect with `auth:sanctum` middleware where needed
3. Create Form Request classes for validation
4. Use Eloquent API Resources for response formatting
5. Consider API versioning for breaking changes
6. Write feature tests covering happy/failure paths

### Authentication Implementation

When working with authentication:

- Fortify configuration in `config/fortify.php` (views disabled, JSON responses only)
- Sanctum stateful domains configured in `config/sanctum.php`
- Login rate limiting: 5 attempts per minute per email+IP
- Fortify routes use `/auth` prefix (configurable)
- Custom authentication logic goes in `app/Actions/Fortify/` classes
