# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Symfony 7.3 API application** for weight logging, implementing **strict DDD/Hexagonal Architecture** following **Matthias Noback's principles** from "Advanced Web Application Architecture".

### Key Characteristics

- **Clean Architecture** with enforced layer boundaries
- **Rich Domain Models** (behavior over getters/setters)
- **CQRS** (Command Query Responsibility Segregation)
- **Hexagonal Architecture** (Ports & Adapters pattern)
- **Event Sourcing** (state derived from domain events, in addition to read model projections)
- **Framework-agnostic domain layer** (pure PHP, zero framework dependencies)
- **JWT-based authentication** (stateless, mobile-friendly)

## Technology Stack

- **PHP**: 8.4+
- **Symfony Framework**: v7.3
- **API Platform**: v4.2 (REST + OpenAPI documentation)
- **Authentication**: LexikJWTAuthenticationBundle (JWT tokens)
- **Database**: MongoDB with `mongodb/mongodb` (not Doctrine ODM)
- **Messaging**: Symfony Messenger (CQRS command/query bus)
- **Testing**: PHPUnit 12
- **Static Analysis**: PHPStan Level Max with strict rules enabled
- **Code Style**: PHP-CS-Fixer
- **Architecture Validation**: Deptrac

### Type Narrowing Preference

Avoid `@var` annotations for inline type narrowing. Instead, use `assert()` or throw an exception - both provide runtime validation and static analysis type narrowing:

```php
// ❌ Avoid: @var lies to the type system without runtime checks
/** @var array<string, mixed> $data */
$data = $serializer->normalize($event);

// ✅ Prefer: assert() validates at runtime AND narrows types for PHPStan
$data = $serializer->normalize($event);
assert(is_array($data));

// ✅ Also good for object types
$event = $serializer->denormalize($data, $eventType);
assert($event instanceof DomainEventInterface);

// ✅ Throwing exceptions also narrows types
if (!$user instanceof User) {
    throw new \InvalidArgumentException('Expected User instance');
}
// $user is now narrowed to User
```

**Note**: `@var` on class properties is fine - this preference applies only to inline variable annotations.

### Static Private Methods

Prefer `private static` over `private` for helper methods that don't use `$this`. This makes it explicit that the method is a pure function with no side effects on instance state.

## Architecture Principles

Following Matthias Noback's guidance from "Advanced Web Application Architecture":

### 1. Rich Domain Models (Not Anemic!)

❌ **WRONG (Anemic Model)**:

```php
class User
{
    private string $email;

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
}
```

✅ **CORRECT (Rich Model)**:

```php
class User
{
    private Email $email;  // Value object

    public static function register(
        UserId $id,
        Email $email,
        HashedPassword $password,
        \DateTimeImmutable $registeredAt,
    ): self {
        // Named constructor - tells a story
    }

    public function changePassword(PlainPassword $current, HashedPassword $new): void
    {
        // Behavior! Encapsulates business logic
        if (!$this->password->verify($current)) {
            throw InvalidCredentialsException::create();
        }
        $this->password = $new;
        $this->recordEvent(new PasswordChanged(...));
    }
}
```

### 2. Value Objects

- **Immutable** - Created once, never modified
- **No identity** - Compared by value equality, not ID
- **Self-validating** - Validation happens at construction
- **Descriptive** - Describe aspects of entities
- **Use `asString()` method** - Follow DDD convention by using `asString()` instead of `toString()` for converting value objects to strings (implement `\Stringable` interface with `__toString()` that delegates to `asString()`)

Examples: `Email`, `UserId`, `PlainPassword`, `HashedPassword`

### 3. Tell, Don't Ask

- Objects should **do things**, not expose their internals
- Methods should **command** behavior, not just get/set data
- Business logic lives **in the aggregate**, not scattered in services

### 4. Ports & Adapters (Hexagonal Architecture)

- **Domain defines interfaces (ports)** - `EventStoreInterface`, `UserReadModelInterface`
- **Infrastructure provides implementations (adapters)** - `MongoEventStore`, `MongoUserReadModel`
- **Domain never depends on Infrastructure** - only the reverse

### 5. Event Sourcing

Aggregates derive state from domain events, not direct property assignment:

- **Events are the source of truth** - State is rebuilt by replaying events
- **Getters only when justified** - Add getters only when required by real business use cases in Domain/Application layers. Never add getters just for database persistence or tests.
- **Optimistic concurrency** - Version checking prevents lost updates
- **CQRS split** - Commands use EventStore, Queries use read model projections

```php
final class User implements EventSourcedAggregateInterface
{
    use RecordsEvents;

    private UserId $id;
    private Email $email;

    private function __construct() {} // State set via apply methods

    public static function register(UserId $id, Email $email, \DateTimeImmutable $at): self
    {
        $user = new self();
        $user->recordThat(new UserRegistered($id->asString(), $email->asString(), $at));
        return $user;
    }

    public function changeEmail(Email $newEmail, \DateTimeImmutable $at): void
    {
        $this->recordThat(new EmailChanged($this->id->asString(), $newEmail->asString(), $at));
    }

    public static function reconstitute(array $events): static
    {
        $user = new self();
        foreach ($events as $event) {
            $user->apply($event);
        }

        return $user;
    }

    private function apply(DomainEventInterface $event): void
    {
        match ($event::class) {
            UserRegistered::class => $this->applyUserRegistered($event),
            EmailChanged::class => $this->applyEmailChanged($event),
            default => throw new \InvalidArgumentException("Unknown event: {$event::class}"),
        };
    }

    private function applyUserRegistered(UserRegistered $event): void
    {
        $this->id = UserId::fromString($event->id);
        $this->email = Email::fromString($event->email);
    }

    private function applyEmailChanged(EmailChanged $event): void
    {
        $this->email = Email::fromString($event->newEmail);
    }
}
```

### 6. Do Not Use Getter Methods

Instead of using getter methods, use readonly properties or PHP 8.4 property hooks.

```php
class Foo
{
    // ❌ Avoid getter methods
    private string $bar;
    public function getBar(): string
    {
        return $this->bar;
    }

    // ✅ Prefer readonly property
    public readonly string $baz;

    // ✅ Or use property hooks for computed values or when a private setter is needed
    public private(set) string $qux;
}
```

Data should be private by default. Expose only what is necessary via readonly properties or property hooks.

## 3-Layer Architecture (Enforced by Deptrac)

```
┌─────────────────────────────────────────────────────────────┐
│                     Infrastructure Layer                     │
│  (Adapters: API Platform, MongoDB, Security, CLI, etc.)    │
└─────────────────────────────────────────────────────────────┘
                            ↑
┌─────────────────────────────────────────────────────────────┐
│                     Application Layer                        │
│         (Use Cases: Command/Query Handlers, DTOs)           │
└─────────────────────────────────────────────────────────────┘
                            ↑
┌─────────────────────────────────────────────────────────────┐
│                       Domain Layer                           │
│   (Aggregates, Value Objects, Events, Port Interfaces)      │
└─────────────────────────────────────────────────────────────┘
```

**Key Rules**:

- **Domain**: Pure PHP only. All validation in value object constructors. Business logic in aggregate methods. Port interfaces defined here, implemented in Infrastructure.
- **Application**: Depends on Domain only. Handlers are thin orchestration. DTOs use primitives (no domain objects).
- **Infrastructure**: Implements Domain interfaces. Never leak framework types to inner layers.

**Read Model Method Naming**:

- `getByX(...)`: Returns data or throws exception
- `findByX(...)`: Returns data or null
- `existsWithX(...)`: Returns boolean

## Directory Structure

```
src/
├── Domain/                 # Layer 1: Pure business logic (no dependencies)
│   ├── Common/             # Shared domain infrastructure
│   │   ├── Aggregate/      # ES aggregate interface + traits
│   │   ├── Event/          # DomainEventInterface
│   │   └── EventStore/     # EventStoreInterface (port)
│   └── {Context}/          # e.g., User/, Order/
│       ├── {Aggregate}.php
│       ├── {ReadModel}Interface.php  # Port for queries
│       ├── ValueObject/
│       ├── Event/
│       └── Exception/
│
├── Application/            # Layer 2: Use cases (depends on Domain only)
│   ├── MessageBus/         # CommandBusInterface (port)
│   ├── Security/           # PasswordHasherInterface (port)
│   └── {Context}/
│       ├── Command/        # Commands + Handlers
│       └── Query/          # Queries + Handlers
│
└── Infrastructure/         # Layer 3: Adapters (depends on everything)
    ├── Api/                # API Platform resources + state processors
    ├── Console/            # CLI commands
    ├── DependencyInjection/ # Compiler passes (e.g., clock timezone validation)
    ├── MessageBus/         # Symfony Messenger adapter
    ├── Persistence/        # EventStore + ReadModel adapters (MongoDB)
    ├── Projection/         # Event handlers updating read models
    └── Security/           # Password hasher adapter

features/                   # Behat feature files (Gherkin scenarios)

tests/
├── Unit/                   # PHPUnit: Classes in isolation (mocked dependencies)
├── Integration/            # PHPUnit: Adapter contract + driving tests (real infra)
├── UseCase/                # Behat context + test doubles (in-memory adapters)
└── E2E/                    # Behat context (real infrastructure)

tools/                      # Build tooling (PHPStan extensions, etc.)
```

## Development Guidelines

### Incremental Development Approach

**IMPORTANT**: Unless explicitly asked to create everything at once, follow this incremental approach, stopping after each step to get feedback:

- ✅ **Only create code needed to fix the current error/test failure**
- ✅ **Take one step at a time** - Let tests drive what to create next
- ✅ **Avoid creating unused code** - Even if it seems likely to be needed soon
- ✅ **Don't generate methods/classes preemptively** - Wait until they're actually called
- ✅ **Keep implementations minimal** - Start with empty/spy implementations

**Example workflow**:

1. Run test → See error about missing class
2. Create minimal class → Run test again
3. See error about missing method → Add minimal method
4. See assertion failure → Implement actual logic
5. Repeat until GREEN

This approach prevents over-engineering and ensures every line of code is justified by a test.

### Behavior-Driven State (Core Principle)

> **Every piece of state should be justified by behavior, and that behavior should be justified by tests.**

This principle is the conjunction of BDD and TDD. It means:

1. **State must be observable** - If a piece of data is stored but never read, compared, displayed, or used in a decision, it doesn't exist meaningfully. Every stored value must eventually manifest as observable behavior somewhere in the system.

2. **Behavior must be tested** - If behavior exists, there must be a test that verifies it. The test is what justifies the behavior's existence.

3. **Tests pull implementation** - Don't add fields, parameters, or properties speculatively. Wait until a failing test _requires_ them.

**Example**: A `User` aggregate shouldn't store `email`, `dateOfBirth`, or `password` until:

- `email` → A test requires checking email uniqueness or displaying it
- `dateOfBirth` → A test requires age verification (e.g., "must be 18+")
- `password` → A test requires login/authentication

**PHPStan as a guide**: Unused parameters, properties, or methods flagged by PHPStan indicate code that isn't yet justified by behavior. Treat these warnings as signals, not problems to suppress.

**The implication**: Every constructor parameter should eventually flow to some observable output (API response, event, decision, etc.). If it doesn't, it shouldn't exist yet.

### MongoDB Over Doctrine ORM

We use MongoDB with the raw `mongodb/mongodb` library instead of Doctrine ODM for these reasons:

1. **Pure domain layer** - No ODM types leaking into domain aggregates
2. **Explicit persistence** - EventStore and projections handle serialization explicitly (no magic)

### Event Serialization

Domain events are serialized using Symfony Serializer in `MongoEventStore`:

```php
// Storing: normalize event to array, store in MongoDB
$eventData = $this->serializer->normalize($event);
$this->collection->insertOne([
    'aggregate_id' => $aggregateId,
    'event_type' => $event::class,
    'event_data' => $eventData,
    // ...
]);

// Loading: denormalize from stored data back to event object
$event = $this->serializer->denormalize($eventData, $eventType);
```

Events must be simple DTOs with public readonly properties - Symfony Serializer handles them automatically without custom normalizers.

### Exception Handling (Domain → HTTP)

Domain exceptions are translated to HTTP exceptions in infrastructure processors:

```php
// Infrastructure/Api/State/RegisterUserProcessor.php
try {
    $this->commandBus->dispatch($command);
} catch (UserAlreadyExistsException $e) {
    throw new ConflictHttpException($e->getMessage(), $e);
}
```

**Pattern**: Catch domain exceptions in processors, wrap them in appropriate Symfony HTTP exceptions (`ConflictHttpException`, `NotFoundHttpException`, `BadRequestHttpException`, etc.).

### ID Generation Strategy

Aggregate IDs are generated in the driving adapter (processor) **before** dispatching the command:

```php
// Infrastructure/Api/State/RegisterUserProcessor.php
$command = new RegisterUserCommand(
    userId: Uuid::v7()->toRfc4122(),  // Generated here
    email: $data->email,
    password: $data->password,
);
$this->commandBus->dispatch($command);
```

**Why generate early?**

- The processor can return the ID immediately in the response
- The ID is available for any follow-up operations
- Commands are fully self-contained (no out-parameters)

### Date/Time Conventions

- **Always store in UTC** - All `DateTimeImmutable` values in the domain and database use UTC
- **Convert on display only** - Timezone conversion to user's local time happens in the presentation layer

### Testing Strategy (Matthias Noback's Approach)

Following Chapter 14 of "Advanced Web Application Architecture", we use **four types of tests**:

**Test Naming Convention**: All test methods must use **camelCase** naming (e.g., `testItCreatesEmailFromValidString()`) for better accessibility with screen readers. Do not use snake_case (e.g., `test_it_creates_email_from_valid_string()`).

#### 1. Unit Tests (PHPUnit)

**What**: Test classes in isolation with mocked dependencies
**Where**: `tests/Unit/`
**Tools**: PHPUnit
**Speed**: Lightning fast (milliseconds)
**Coverage**: Domain invariants, business rules, adapter-specific logic

Unit tests include:

- **Domain objects** (`tests/Unit/Domain/`) - Value objects, aggregates, domain services
- **Infrastructure adapters** (`tests/Unit/Infrastructure/`) - When testing adapter-specific logic with mocked dependencies (e.g., `MessengerCommandBusTest` mocks `MessageBusInterface` to test exception unwrapping)

#### 2. Use Case Tests (Behat)

**What**: Test application core (commands/queries) with business language
**Where**: `features/*.feature`
**Tools**: Behat with TestContainer and spy objects
**Speed**: Fast (no infrastructure)
**Coverage**: Complete use cases, domain logic orchestration

**Key**: Use `TestContainer` with **spy objects** (not mocks) to verify side effects:

#### 3. Adapter Tests (PHPUnit)

**What**: Test infrastructure adapters (API processors, repositories, external clients)
**Where**: `tests/Integration/Infrastructure/`
**Tools**: PHPUnit with real infrastructure
**Speed**: Slower (uses database, HTTP, etc.)
**Coverage**: Port adapters work correctly

**Two types**:

**A. Contract Tests** (for outgoing port adapters like event stores and read models):

Contract tests verify that **all implementations of a port interface behave identically**. Use a data provider to test both the in-memory test double and the real infrastructure adapter with the same test cases.

**Why this pattern matters:**

1. ✅ **In-memory validates test correctness** - If tests pass with in-memory but fail with real adapter, the adapter has a bug
2. ✅ **Ensures test doubles are accurate** - The fake used in Behat use case tests behaves like the real thing
3. ✅ **Single source of truth** - Contract is defined once, all implementations must conform
4. ✅ **Catches behavioral differences** - Subtle differences (e.g., bcrypt limits, case sensitivity) are caught early

**Existing contract tests:**

- `EventStoreContractTest` - Tests `InMemoryEventStore` and `MongoEventStore`
- `UserReadModelContractTest` - Tests `InMemoryUserReadModel` and `MongoUserReadModel`
- `PasswordHasherContractTest` - Tests `FakePasswordHasher` and `NativePasswordHasher`

**Implementation-specific tests:**

Tests for implementation details (not interface contracts) should be in separate files:

- `MongoEventStoreTest` - Tests MongoDB-specific behavior (BSON document structure)
- `MessengerCommandBusTest` - Tests Messenger-specific behavior (exception unwrapping)

Naming convention: `*ContractTest` = interface behavior, `Mongo*Test`/`Messenger*Test` = implementation details.

**B. Driving Tests** (for incoming port adapters like API Platform processors):

Driving tests verify that API processors correctly transform HTTP requests into commands and dispatch them to the application layer. They mock the command bus to verify the correct command is dispatched.

**Key points for driving tests:**

- Mock the `CommandBusInterface` to verify the processor dispatches the correct command
- Test HTTP → Command transformation (correct fields mapped, UUID generated)
- Test error handling (domain exceptions → HTTP status codes)
- Do NOT test business logic here (that's covered by use case tests)

#### 4. End-to-End Tests (Behat + Real Infrastructure)

**What**: Test complete system as black box with real HTTP, database, etc.
**Where**: Same `features/*.feature` files, different suite
**Tools**: Behat with real Symfony kernel, web server
**Speed**: Slowest (full stack)
**Coverage**: Everything works together in production-like environment

**Key**: Reuse the same scenarios from use case tests but with different context:

### Test & Quality Commands

```bash
# Unit tests (fast - run constantly)
composer test:unit

# Use case tests (fast - run frequently)
composer test:usecase

# Adapter/integration tests (slower - run before commit)
composer test:integration

# End-to-end tests (slow - run before deploy)
composer test:e2e

# All PHPUnit tests
composer test:phpunit

# All Behat tests
composer test:behat

# Test with coverage enforcement (fails if not 100%)
composer test:coverage

# Code style check
composer test:cs

# Static analysis (must pass level max)
composer test:types

# Code formatting (auto-fix)
composer fix:cs

# Architecture boundaries
composer test:arch
```

**Note on environment variables**: All `test:*` scripts use `env -u` to unset `APP_ENV` and `JWT_*` variables before running. This ensures Symfony's dotenv loads the correct values from `.env.test` rather than inheriting potentially incorrect values from the shell environment (e.g., from tools that auto-import `.env`).

**Note on handler testing**: Command/Query handlers are thin orchestration code. They are tested via Behat use case tests, not PHPUnit. They are excluded from PHPUnit coverage reports.

### Coverage Policy: 100% for Included Classes

All classes included in PHPUnit coverage must have **100% test coverage**. This is enforced automatically - the build fails if coverage drops below 100%.

**The rule is simple:**

- If a class should be tested → it must have 100% coverage
- If a class shouldn't be tested → add it to the exclusion list in `phpunit.dist.xml`
- If trying to test a method within a class adds no value, ignore the method with `@codeCoverageIgnore` (rare cases: empty constructors with promoted properties, framework boilerplate like `SecurityUser::eraseCredentials()`)

**Currently excluded from coverage** (with rationale):

- `src/Application/*/Command/` - Command DTOs and handlers (tested via Behat)
- `src/Application/*/Query/` - Query DTOs and handlers (tested via Behat)
- `src/Domain/*/Event/` - Event DTOs (simple data carriers)
- `src/Domain/*/Exception/` - Exception classes (trivial logic)

**When to exclude vs. test:**

- **Exclude**: Pure DTOs, simple delegating adapters with no logic, framework boilerplate
- **Test**: Any class with conditional logic, validation, transformation, or business rules

## Development Workflow (TDD with Behat)

Following Noback's top-down approach from Section 14.7, with **tests written BEFORE implementation at each layer**.

### The Outside-In TDD Cycle

```
┌─────────────────────────────────────────────────────────────────────┐
│  1. BEHAT SCENARIO (RED)                                            │
│     Write Gherkin scenario describing the feature                   │
│     Run: composer test:usecase → FAILS                   │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│  2. DROP TO DOMAIN/APPLICATION LAYER                                │
│     a) Write PHPUnit unit tests for domain objects (RED)            │
│     b) Implement domain objects → Unit tests GREEN                  │
│     c) Wire up TestContainer with in-memory adapters                │
│     d) Run: composer test:usecase → GREEN                           │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│  3. GO UP TO INFRASTRUCTURE LAYER                                   │
│     a) Write integration tests for adapters (RED)                   │
│        - Contract tests for event stores/read models                │
│        - Driving tests for API endpoints                            │
│     b) Implement infrastructure adapters → Integration tests GREEN  │
│     c) Run: composer test:e2e → GREEN                               │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│  4. SLICE COMPLETE                                                  │
│     All tests pass: Unit, UseCase, Integration, E2E                 │
│     Run: composer test:phpunit && composer test:behat               │
└─────────────────────────────────────────────────────────────────────┘
```

### Workflow Steps

1. **Write Gherkin scenario** → Describes feature in business language
2. **Create step definitions** → Wire to TestContainer (see Use Case Tests in Testing Strategy)
3. **Drop to Domain** → For each method needed:
    - Write test for ONE method → Stop for feedback
    - Implement with minimal code until test passes → Stop for feedback
    - Repeat for next method
4. **Wire TestContainer** → Run `composer test:usecase` until GREEN
5. **Go up to Infrastructure** → Same one-method-at-a-time cycle for adapters
6. **Implement adapters** → Run `composer test:e2e` until GREEN
7. **Verify slice complete** → All tests pass, static analysis clean

**Key principle**: Write failing tests for one method, implement them with minimal code until they pass, get feedback. Never batch multiple methods.

### Adding Methods to Port Interfaces

When a feature requires adding a new method to a port interface (e.g., `UserReadModelInterface`):

1. **Add the method to the interface** (Domain layer)
2. **Implement ONLY in the in-memory test double** (e.g., `InMemoryUserReadModel`)
3. **Verify UseCase tests pass** → `composer test:usecase`
4. **STOP HERE** if only working on UseCase layer

Only after UseCase tests are GREEN, move to Infrastructure:

5. **Add contract tests** for the new method
6. **Implement in real adapter** (e.g., `MongoUserReadModel`)
7. **Verify contract tests pass** → All implementations behave identically

This ensures you don't prematurely implement infrastructure code before the application layer is working.

## Important Principles

### ❌ DO NOT

- ❌ Create anemic models (data bags with only getters/setters)
- ❌ Put business logic in services
- ❌ Expose entity internals with getters (prefer behavior methods)
- ❌ Use domain objects in API responses (use DTOs)
- ❌ Put framework code in Domain/Application layers
- ❌ Create circular dependencies between layers

### ✅ DO

- ✅ Use named constructors (`User::register()`, not `new User()`)
- ✅ Validate in value object constructors
- ✅ Make value objects immutable (readonly)
- ✅ Put business logic in aggregate methods
- ✅ Define ports in Domain, implement adapters in Infrastructure
- ✅ Use domain events as source of truth (event sourcing)
- ✅ Use `Aggregate::class` for aggregate type (not string literals)
- ✅ Keep handlers thin (orchestration only)
- ✅ Test domain logic without framework
- ✅ Place build tooling in `tools/` (not in 3-layer architecture)

## Useful Console Commands

```bash
php bin/console app:create-indices  # Create MongoDB indices (required for production)
```
