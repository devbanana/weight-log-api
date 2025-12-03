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
// Aggregate implements EventSourcedAggregateInterface
final class User implements EventSourcedAggregateInterface
{
    use RecordsEvents;

    public static function register(
        UserId $id,
        Email $email,
        HashedPassword $password,
        \DateTimeImmutable $registeredAt,
    ): self {
        $user = new self();
        $user->recordThat(new UserRegistered(
            $id->asString(),
            $email->asString(),
            $password->asString(),
            $registeredAt,
        ));
        return $user;
    }

    public static function reconstitute(array $events): static { /* replay events */ }
    private function apply(DomainEventInterface $event): void { /* update state */ }
}
```

### 6. PHP 8.4 Interface Properties

Use PHP 8.4 interface properties instead of methods for simple contracts:

```php
// Interface defines required properties
interface DomainEventInterface
{
    public string $id { get; }
    public \DateTimeImmutable $occurredAt { get; }
}

// Implementation satisfies interface with public readonly properties
final readonly class UserRegistered implements DomainEventInterface
{
    public function __construct(
        public string $id,
        public string $email,
        public string $passwordHash,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
```

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
│   ├── Clock/              # ClockInterface (port)
│   ├── MessageBus/         # CommandBusInterface (port)
│   ├── Security/           # PasswordHasherInterface (port)
│   └── {Context}/
│       ├── Command/        # Commands + Handlers
│       └── Query/          # Queries + Handlers
│
└── Infrastructure/         # Layer 3: Adapters (depends on everything)
    ├── Api/                # API Platform resources + state processors
    ├── Clock/              # SystemClock adapter
    ├── Console/            # CLI commands
    ├── MessageBus/         # Symfony Messenger adapter
    ├── Persistence/        # EventStore + ReadModel adapters (MongoDB)
    ├── Projection/         # Event handlers updating read models
    └── Security/           # Password hasher adapter

features/                   # Behat feature files (Gherkin scenarios)

tests/
├── Unit/                   # PHPUnit: Domain objects in isolation
├── Integration/            # PHPUnit: Adapter contract + driving tests
├── UseCase/                # Behat context + test doubles (in-memory adapters)
└── E2E/                    # Behat context (real infrastructure)

tools/                      # Build tooling (PHPStan extensions, etc.)
```

## CQRS Flow

### Write Operation (Command)

```
HTTP POST /api/auth/register
    ↓
Infrastructure/Api/Resource/UserRegistrationResource.php (input DTO + validation)
    ↓
Infrastructure/Api/State/RegisterUserProcessor.php (driving adapter)
    ↓  (dispatches via Symfony Messenger command.bus)
Application/User/Command/RegisterUserHandler.php
    ↓  (checks read model, creates aggregate)
Domain/User/User::register() → records UserRegistered event
    ↓  (persists via EventStore port)
Infrastructure/Persistence/MongoDB/MongoEventStore.php
    ↓  (dispatches event via event.bus)
Infrastructure/Projection/UserProjection.php → updates read model
```

### Read Operation (Query)

```
HTTP GET /api/users/me (planned)
    ↓
Infrastructure/Api/State/GetCurrentUserProcessor.php (planned)
    ↓  (queries read model directly or via query.bus)
Domain/User/UserReadModelInterface
    ↓  (implemented by adapter)
Infrastructure/Persistence/MongoDB/MongoUserReadModel.php
    ↓  (returns data for API response)
```

## Development Guidelines

### Incremental Development Approach

**IMPORTANT**: Unless explicitly asked to create everything at once, follow this incremental approach:

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

> **"Every piece of state should be justified by behavior, and that behavior should be justified by tests."**

This principle is the conjunction of BDD and TDD. It means:

1. **State must be observable** - If a piece of data is stored but never read, compared, displayed, or used in a decision, it doesn't exist meaningfully. Every stored value must eventually manifest as observable behavior somewhere in the system.

2. **Behavior must be tested** - If behavior exists, there must be a test that verifies it. The test is what justifies the behavior's existence.

3. **Tests pull implementation** - Don't add fields, parameters, or properties speculatively. Wait until a failing test *requires* them.

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
**What**: Test domain objects (aggregates, value objects) in isolation
**Where**: `tests/Unit/Domain/`
**Tools**: PHPUnit
**Speed**: Lightning fast (milliseconds)
**Coverage**: Domain invariants, business rules, edge cases

```php
// tests/Unit/Domain/User/EmailTest.php
final class EmailTest extends TestCase
{
    public function testItRejectsInvalidEmailFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('not-an-email');
    }
}
```

#### 2. Use Case Tests (Behat)
**What**: Test application core (commands/queries) with business language
**Where**: `features/*.feature`
**Tools**: Behat with TestContainer and spy objects
**Speed**: Fast (no infrastructure)
**Coverage**: Complete use cases, domain logic orchestration

```gherkin
# features/user-registration.feature
Scenario: Customer receives confirmation email
  When a customer registers with email "test@example.com"
  Then they should receive a confirmation email
```

**Key**: Use `TestContainer` with **spy objects** (not mocks) to verify side effects:

```php
// tests/UseCase/UserContext.php
final class UserContext implements Context
{
    private TestContainer $container;
    private ?string $registeredUserId = null;

    public function __construct()
    {
        $this->container = new TestContainer();
    }

    #[When('I register with email :email and password :password')]
    public function iRegisterWithEmailAndPassword(string $email, string $password): void
    {
        $this->registeredUserId = Uuid::v7()->toString();
        $command = new RegisterUserCommand(
            userId: $this->registeredUserId,
            email: $email,
            password: $password,
        );
        $this->container->getCommandBus()->dispatch($command);
    }

    #[Then('I should be registered')]
    public function iShouldBeRegistered(): void
    {
        $events = $this->container->getEventStore()->getEvents(
            $this->registeredUserId,
            User::class
        );
        Assert::notEmpty($events, 'No events were stored for the user');
    }
}
```

#### 3. Adapter Tests (PHPUnit)
**What**: Test infrastructure adapters (API processors, repositories, external clients)
**Where**: `tests/Integration/Infrastructure/`
**Tools**: PHPUnit with real infrastructure
**Speed**: Slower (uses database, HTTP, etc.)
**Coverage**: Port adapters work correctly

**Two types**:

**A. Contract Tests** (for outgoing port adapters like event stores and read models):

Contract tests verify that **all implementations of a port interface behave identically**. Use a data provider to test both the in-memory test double and the real infrastructure adapter with the same test cases.

**Pattern:**
```php
// tests/Integration/Infrastructure/Persistence/EventStoreContractTest.php
final class EventStoreContractTest extends TestCase
{
    #[DataProvider('eventStoreProvider')]
    public function testItAppendsEventsToNewAggregate(EventStoreInterface $eventStore): void
    {
        $event = $this->createEvent('user-123');

        $eventStore->append('user-123', User::class, [$event], expectedVersion: 0);

        $storedEvents = $eventStore->getEvents('user-123', User::class);
        self::assertCount(1, $storedEvents);
    }

    public static function eventStoreProvider(): iterable
    {
        // In-memory serves as reference implementation
        yield 'InMemory' => [new InMemoryEventStore()];

        // Real adapter must behave identically
        yield 'MongoDB' => [self::createMongoEventStore()];
    }
}
```

**Why this pattern matters:**
1. ✅ **In-memory validates test correctness** - If tests pass with in-memory but fail with real adapter, the adapter has a bug
2. ✅ **Ensures test doubles are accurate** - The fake used in Behat use case tests behaves like the real thing
3. ✅ **Single source of truth** - Contract is defined once, all implementations must conform
4. ✅ **Catches behavioral differences** - Subtle differences (e.g., bcrypt limits, case sensitivity) are caught early

**Existing contract tests:**
- `EventStoreContractTest` - Tests `InMemoryEventStore` and `MongoEventStore`
- `UserReadModelContractTest` - Tests `InMemoryUserReadModel` and `MongoUserReadModel`
- `PasswordHasherContractTest` - Tests `FakePasswordHasher` and `NativePasswordHasher`

**B. Driving Tests** (for incoming port adapters like API Platform processors):

Driving tests verify that API processors correctly transform HTTP requests into commands and dispatch them to the application layer. They mock the command bus to verify the correct command is dispatched.

```php
// tests/Integration/Infrastructure/Api/RegisterUserEndpointTest.php
final class RegisterUserEndpointTest extends WebTestCase
{
    private KernelBrowser $client;
    private CommandBusInterface&MockObject $commandBus;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        self::getContainer()->set(CommandBusInterface::class, $this->commandBus);
    }

    public function testItRegistersUserSuccessfully(): void
    {
        // Arrange: Expect command bus to be called with RegisterUserCommand
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (RegisterUserCommand $command): bool {
                self::assertTrue(Uuid::isValid($command->userId));
                self::assertSame('test@example.com', $command->email);
                self::assertSame('SecurePass123!', $command->password);
                return true;
            }));

        // Act: POST to registration endpoint
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 201 Created
        self::assertResponseStatusCodeSame(201);
    }
}
```

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

```php
// tests/E2E/UserContext.php (makes real HTTP requests)
final class UserContext implements Context
{
    private KernelBrowser $client;

    /** @When a customer registers with email :email */
    public function aCustomerRegistersWithEmail(string $email): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => $email,
            'password' => 'password123',
        ]));
    }
}
```

### Test & Quality Commands

```bash
# Unit tests (fast - run constantly)
vendor/bin/phpunit tests/Unit

# Use case tests (fast - run frequently)
vendor/bin/behat --suite=usecase

# Adapter tests (slower - run before commit)
vendor/bin/phpunit --testsuite=integration

# End-to-end tests (slow - run before deploy)
vendor/bin/behat --suite=e2e

# All tests (PHPUnit + Behat)
composer test && vendor/bin/behat

# Static analysis (must pass level max)
composer analyze

# Code formatting
vendor/bin/php-cs-fixer fix

# Architecture boundaries
vendor/bin/deptrac analyse --report-uncovered
```

**Note on handler testing**: Command/Query handlers are thin orchestration code. They are tested via Behat use case tests, not PHPUnit. They are excluded from PHPUnit coverage reports.

## Development Workflow (TDD with Behat)

Following Noback's top-down approach from Section 14.7, with **tests written BEFORE implementation at each layer**.

### The Outside-In TDD Cycle

```
┌─────────────────────────────────────────────────────────────────────┐
│  1. BEHAT SCENARIO (RED)                                            │
│     Write Gherkin scenario describing the feature                   │
│     Run: vendor/bin/behat --suite=usecase → FAILS                   │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│  2. DROP TO DOMAIN/APPLICATION LAYER                                │
│     a) Write PHPUnit unit tests for domain objects (RED)            │
│     b) Implement domain objects → Unit tests GREEN                  │
│     c) Wire up TestContainer with in-memory adapters                │
│     d) Run: vendor/bin/behat --suite=usecase → GREEN                │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│  3. GO UP TO INFRASTRUCTURE LAYER                                   │
│     a) Write integration tests for adapters (RED)                   │
│        - Contract tests for event stores/read models                │
│        - Driving tests for API endpoints                            │
│     b) Implement infrastructure adapters → Integration tests GREEN  │
│     c) Run: vendor/bin/behat --suite=e2e → GREEN                    │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────────┐
│  4. SLICE COMPLETE                                                  │
│     All tests pass: Unit, UseCase, Integration, E2E                 │
│     Run: composer test && vendor/bin/behat && composer analyze      │
└─────────────────────────────────────────────────────────────────────┘
```

### Test Pyramid

```
         /\
        /  \  E2E Tests (Behat e2e suite)
       /    \ Few, slow, production-like
      /------\
     / Adapter \ Adapter Tests (PHPUnit integration)
    /  Tests   \ Real database, API processors
   /------------\
  /  Use Case    \ Use Case Tests (Behat usecase suite)
 /     Tests      \ TestContainer + spies
/------------------\
/   Unit Tests      \ Unit Tests (PHPUnit)
--------------------  Many, fast, domain objects
```

### Workflow Steps

1. **Write Gherkin scenario** → Describes feature in business language
2. **Create step definitions** → Wire to TestContainer (see Use Case Tests in Testing Strategy)
3. **Drop to Domain** → Write unit tests FIRST, then implement domain objects
4. **Wire TestContainer** → Run `vendor/bin/behat --suite=usecase` until GREEN
5. **Go up to Infrastructure** → Write contract tests and driving tests FIRST (see Adapter Tests in Testing Strategy)
6. **Implement adapters** → Run `vendor/bin/behat --suite=e2e` until GREEN
7. **Verify slice complete** → All tests pass, static analysis clean

**Key principle**: All domain objects should have unit tests before moving to infrastructure.

## Common Patterns

### Creating a Value Object

```php
// Domain/Shared/ValueObject/Money.php
final readonly class Money
{
    private function __construct(
        private int $amountInCents,
        private Currency $currency,
    ) {
        if ($amountInCents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative');
        }
    }

    public static function fromCents(int $cents, Currency $currency): self
    {
        return new self($cents, $currency);
    }

    public static function fromFloat(float $amount, Currency $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function add(self $other): self
    {
        if (!$this->currency->equals($other->currency)) {
            throw new \InvalidArgumentException('Cannot add different currencies');
        }
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function toFloat(): float
    {
        return $this->amountInCents / 100;
    }
}
```

### Creating a Command Handler (New Aggregate)

```php
// Application/User/Command/RegisterUserHandler.php
final readonly class RegisterUserHandler implements CommandHandlerInterface
{
    public function __construct(
        private EventStoreInterface $eventStore,
        private UserReadModelInterface $userReadModel,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    #[\Override]
    public function __invoke(CommandInterface $command): void
    {
        $email = Email::fromString($command->email);

        // Validate via read model
        if ($this->userReadModel->existsWithEmail($email)) {
            throw UserAlreadyExistsException::withEmail($email);
        }

        // Hash password and create aggregate
        $hashedPassword = $this->passwordHasher->hash(
            PlainPassword::fromString($command->password),
        );
        $user = User::register(
            UserId::fromString($command->userId),
            $email,
            $hashedPassword,
            $this->clock->now(),
        );

        // Persist events (expectedVersion: 0 for new aggregates)
        $this->eventStore->append(
            $command->userId,
            User::class,
            $user->releaseEvents(),
            expectedVersion: 0,
        );
    }
}
```

### Creating a Command Handler (Existing Aggregate)

Key differences from new aggregate: load via `reconstitute()`, use current `$version` for optimistic concurrency.

```php
// Application/User/Command/ChangeEmailHandler.php
#[\Override]
public function __invoke(CommandInterface $command): void
{
    $userId = $command->userId;

    // Load aggregate from events (vs. new aggregate: just `new self()`)
    $events = $this->eventStore->getEvents($userId, User::class);
    $version = $this->eventStore->getVersion($userId, User::class);
    $user = User::reconstitute($events);

    $user->changeEmail(Email::fromString($command->newEmail));

    // expectedVersion: $version (vs. new aggregate: expectedVersion: 0)
    $this->eventStore->append(
        $userId,
        User::class,
        $user->releaseEvents(),
        expectedVersion: $version,
    );
}
```

### Creating a Projection

Projections listen to domain events and update read models. They use `upsert` for idempotency:

```php
// Infrastructure/Projection/UserProjection.php
final readonly class UserProjection
{
    public function __construct(
        private Collection $collection,
    ) {}

    #[AsMessageHandler(bus: 'event.bus')]
    public function onUserRegistered(UserRegistered $event): void
    {
        // Use updateOne with upsert for idempotency - replaying events is safe
        $this->collection->updateOne(
            ['_id' => $event->id],
            ['$set' => [
                'email' => $event->email,
                'registered_at' => new UTCDateTime($event->occurredAt),
            ]],
            ['upsert' => true],
        );
    }
}
```

**Key points**:
- Projections are message handlers on `event.bus`
- Use `upsert: true` so replaying events doesn't cause duplicates
- Use `$set` to update specific fields, making projections idempotent
- One projection class can handle multiple event types

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

## API Development

### Authentication Flow

1. **Register**: `POST /api/auth/register` → Returns 201 Created
2. **Login**: `POST /api/auth/login` → Returns JWT token
3. **Authenticated requests**: Add `Authorization: Bearer <token>` header
4. **Get current user**: `GET /api/users/me`

### Adding a New Endpoint

1. Create Command/Query in Application layer
2. Create Handler implementing `CommandHandlerInterface<YourCommand>` (auto-tagged via `_instanceof` in services.yaml)
3. Create State Processor/Provider in Infrastructure/Api/State
4. Add API Resource or operation in Infrastructure/Api/Resource

Follow outside-in TDD approach: write tests before implementation at each layer (see Development Workflow).

## Configuration

### Environment Variables

```bash
# .env
MONGODB_URL="mongodb://localhost:27017"
MONGODB_DATABASE="weight_log"

# JWT (not yet implemented)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase
JWT_TOKEN_TTL=3600  # 1 hour
```

### MongoDB Persistence

We use **event sourcing** - aggregates are persisted as streams of events, not documents:

```php
// Infrastructure/Persistence/MongoDB/MongoEventStore.php
// Stores events in 'events' collection with structure:
// { aggregate_id, aggregate_type, version, event_type, event_data, occurred_at }

// Infrastructure/Persistence/MongoDB/MongoUserReadModel.php
// Read model in 'users' collection, updated by projections
// { _id: aggregateId, email, registered_at }
```

**Collections**:
- `events` - Event store (unique index on aggregate_id + aggregate_type + version)
- `users` - Read model projection (unique index on email)

**Setup indices** (required for production):
```bash
php bin/console app:create-indices
```

## References

- **Matthias Noback**: "Advanced Web Application Architecture"
- **Matthias Noback**: https://matthiasnoback.nl/book/a-year-with-symfony/
- **API Platform**: https://api-platform.com/docs/
- **Symfony Messenger**: https://symfony.com/doc/current/messenger.html
- **Hexagonal Architecture**: https://alistair.cockburn.us/hexagonal-architecture/
- **DDD**: "Domain-Driven Design" by Eric Evans
