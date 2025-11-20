# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Symfony 7.3 API application** for weight logging, implementing **strict DDD/Hexagonal Architecture** following **Matthias Noback's principles** from "Advanced Web Application Architecture".

### Key Characteristics

- **Clean Architecture** with enforced layer boundaries
- **Rich Domain Models** (behavior over getters/setters)
- **CQRS** (Command Query Responsibility Segregation)
- **Hexagonal Architecture** (Ports & Adapters pattern)
- **Framework-agnostic domain layer** (pure PHP, zero framework dependencies)
- **JWT-based authentication** (stateless, mobile-friendly)

## Technology Stack

- **PHP**: 8.4+
- **Symfony Framework**: v7.3
- **API Platform**: v4.2 (REST + OpenAPI documentation)
- **Authentication**: LexikJWTAuthenticationBundle (JWT tokens)
- **Database**: Doctrine ORM (PostgreSQL/MySQL/SQLite)
- **Messaging**: Symfony Messenger (CQRS command/query bus)
- **Testing**: PHPUnit 12
- **Static Analysis**: PHPStan Level 10 (maximum strictness)
- **Code Style**: PHP-CS-Fixer
- **Architecture Validation**: Deptrac

## Architecture Principles

Following Matthias Noback's guidance from "Advanced Web Application Architecture":

### 1. Rich Domain Models (Not Anemic!)

❌ **WRONG (Anemic Model)**:
```php
class User {
    private string $email;

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
}
```

✅ **CORRECT (Rich Model)**:
```php
class User {
    private Email $email;  // Value object

    public static function register(Email $email, HashedPassword $password): self {
        // Named constructor - tells a story
    }

    public function changePassword(PlainPassword $current, HashedPassword $new): void {
        // Behavior! Encapsulates business logic
        if (!$this->password->verify($current)) {
            throw InvalidCredentialsException::create();
        }
        $this->password = $new;
        $this->recordEvent(new PasswordWasChanged(...));
    }
}
```

### 2. Value Objects

- **Immutable** - Created once, never modified
- **No identity** - Compared by value equality, not ID
- **Self-validating** - Validation happens at construction
- **Descriptive** - Describe aspects of entities

Examples: `Email`, `UserId`, `PlainPassword`, `HashedPassword`

### 3. Tell, Don't Ask

- Objects should **do things**, not expose their internals
- Methods should **command** behavior, not just get/set data
- Business logic lives **in the entity**, not scattered in services

### 4. Ports & Adapters (Hexagonal Architecture)

- **Domain defines interfaces (ports)** - `UserRepositoryInterface`, `PasswordHasherInterface`
- **Infrastructure provides implementations (adapters)** - `DoctrineUserRepository`, `SymfonyPasswordHasher`
- **Domain never depends on Infrastructure** - only the reverse

## 3-Layer Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Infrastructure Layer                     │
│  (Adapters: API Platform, Doctrine, Security, CLI, etc.)   │
│                                                             │
│  Dependencies: Domain, Application, Symfony, API Platform  │
└─────────────────────────────────────────────────────────────┘
                            ↑
┌─────────────────────────────────────────────────────────────┐
│                     Application Layer                        │
│         (Use Cases: Command/Query Handlers, DTOs)           │
│                                                             │
│  Dependencies: Domain ONLY (no framework!)                  │
└─────────────────────────────────────────────────────────────┘
                            ↑
┌─────────────────────────────────────────────────────────────┐
│                       Domain Layer                           │
│   (Entities, Value Objects, Events, Repository Interfaces)  │
│                                                             │
│  Dependencies: NONE (pure PHP!)                             │
└─────────────────────────────────────────────────────────────┘
```

## Directory Structure

```
src/
├── Domain/                          # Layer 1: Pure Business Logic
│   └── User/
│       ├── User.php                 # Aggregate root (rich model!)
│       ├── UserRepositoryInterface.php    # Port (interface)
│       ├── PasswordHasherInterface.php    # Port (interface)
│       ├── ValueObject/
│       │   ├── UserId.php           # Typed identifier
│       │   ├── Email.php            # Self-validating
│       │   ├── PlainPassword.php    # Input
│       │   └── HashedPassword.php   # With verify() method
│       ├── Event/
│       │   ├── UserWasRegistered.php
│       │   └── PasswordWasChanged.php
│       └── Exception/
│           ├── UserAlreadyExistsException.php
│           └── InvalidCredentialsException.php
│
├── Application/                     # Layer 2: Use Cases (CQRS)
│   └── User/
│       ├── Command/                 # Write operations
│       │   ├── RegisterUser.php            # Command (DTO)
│       │   ├── RegisterUserHandler.php     # Handler
│       │   ├── ChangePassword.php
│       │   └── ChangePasswordHandler.php
│       ├── Query/                   # Read operations
│       │   ├── FindUserById.php            # Query (DTO)
│       │   ├── FindUserByIdHandler.php     # Handler
│       │   ├── FindUserByEmail.php
│       │   └── FindUserByEmailHandler.php
│       └── DTO/                     # Response DTOs
│           ├── UserResponse.php
│           └── RegisterUserRequest.php
│
└── Infrastructure/                  # Layer 3: Adapters
    ├── Api/                         # HTTP API adapter
    │   ├── Resource/
    │   │   └── UserResource.php     # API Platform resource
    │   └── State/
    │       ├── RegisterUserProcessor.php    # Dispatches commands
    │       └── UserProvider.php             # Dispatches queries
    ├── Persistence/                 # Database adapter
    │   └── Doctrine/
    │       ├── Repository/
    │       │   └── DoctrineUserRepository.php  # Implements UserRepositoryInterface
    │       ├── Mapping/
    │       │   └── User.orm.xml     # Doctrine XML mapping
    │       └── Type/
    │           └── UserIdType.php   # Custom Doctrine type
    └── Security/                    # Auth adapter
        ├── SymfonyPasswordHasher.php        # Implements PasswordHasherInterface
        ├── SecurityUser.php                 # Symfony UserInterface adapter
        └── UserProvider.php                 # Symfony UserProviderInterface
```

## Layer Rules (Enforced by Deptrac)

### Domain Layer

✅ **Can depend on**: NOTHING (pure PHP)
❌ **Cannot depend on**: Symfony, Doctrine, API Platform, Application, Infrastructure
📦 **Contains**: Entities, Value Objects, Events, Exceptions, Repository Interfaces

**Rules**:
- No framework dependencies
- No annotations/attributes (except for documentation)
- All validation in value object constructors
- Business logic in entity methods, not services
- Repository interfaces defined here, implemented in Infrastructure

### Application Layer

✅ **Can depend on**: Domain ONLY
❌ **Cannot depend on**: Symfony, Doctrine, API Platform, Infrastructure
📦 **Contains**: Commands, Queries, Handlers, DTOs

**Rules**:
- Orchestrates domain logic
- No business rules (those belong in Domain)
- Handlers are thin - call domain objects, dispatch events
- DTOs use primitives (string, int, array) - no domain objects

### Infrastructure Layer

✅ **Can depend on**: Domain, Application, Symfony, Doctrine, API Platform
❌ **Cannot depend on**: Nothing (top layer)
📦 **Contains**: All framework-specific code

**Rules**:
- Implements interfaces defined in Domain
- Never leak framework types to Application/Domain
- Use adapters to convert between framework and domain types

## CQRS Flow

### Write Operation (Command)

```
HTTP POST /auth/register
    ↓
Infrastructure/Api/Resource/UserResource.php
    ↓
Infrastructure/Api/State/RegisterUserProcessor.php
    ↓  (dispatches via Symfony Messenger)
Application/User/Command/RegisterUserHandler.php
    ↓  (uses domain)
Domain/User/User::register()
    ↓  (persists via port)
Domain/User/UserRepositoryInterface
    ↓  (implemented by adapter)
Infrastructure/Persistence/Doctrine/Repository/DoctrineUserRepository.php
```

### Read Operation (Query)

```
HTTP GET /api/users/me
    ↓
Infrastructure/Api/Resource/UserResource.php
    ↓
Infrastructure/Api/State/UserProvider.php
    ↓  (dispatches via Symfony Messenger)
Application/User/Query/FindUserByIdHandler.php
    ↓  (queries via port)
Domain/User/UserRepositoryInterface
    ↓  (implemented by adapter)
Infrastructure/Persistence/Doctrine/Repository/DoctrineUserRepository.php
    ↓  (returns DTO)
Application/User/DTO/UserResponse.php
```

## Development Guidelines

### Creating a New Feature

1. **Start with Domain** - What's the business concept?
2. **Create Value Objects** - Identify immutable aspects
3. **Create Entity** - Add behavior (methods that do things)
4. **Define Ports** - What interfaces does domain need?
5. **Create Commands/Queries** - What are the use cases?
6. **Create Handlers** - Orchestrate domain logic
7. **Implement Adapters** - Connect to framework

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

### Testing Strategy (Matthias Noback's Approach)

Following Chapter 14 of "Advanced Web Application Architecture", we use **four types of tests**:

#### 1. Unit Tests (PHPUnit)
**What**: Test domain objects (entities, value objects) in isolation
**Where**: `tests/Unit/Domain/`
**Tools**: PHPUnit
**Speed**: Lightning fast (milliseconds)
**Coverage**: Domain invariants, business rules, edge cases

```php
// tests/Unit/Domain/User/EmailTest.php
final class EmailTest extends TestCase {
    public function test_it_validates_email_format(): void {
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('not-an-email');
    }
}
```

#### 2. Use Case Tests (Behat)
**What**: Test application core (commands/queries) with business language
**Where**: `features/*.feature`
**Tools**: Behat with TestServiceContainer and spy objects
**Speed**: Fast (no infrastructure)
**Coverage**: Complete use cases, domain logic orchestration

```gherkin
# features/user-registration.feature
Scenario: Customer receives confirmation email
  When a customer registers with email "test@example.com"
  Then they should receive a confirmation email
```

**Key**: Use `TestServiceContainer` with **spy objects** (not mocks) to verify side effects:

```php
// features/bootstrap/UseCaseContext.php
final class UseCaseContext implements Context {
    private TestServiceContainer $container;

    public function __construct() {
        $this->container = new TestServiceContainer();
    }

    /** @When a customer registers with email :email */
    public function aCustomerRegistersWithEmail(string $email): void {
        $this->container->application()->registerUser(
            new RegisterUser($email, 'password123')
        );
    }

    /** @Then they should receive a confirmation email */
    public function theyShouldReceiveAConfirmationEmail(): void {
        Assert::assertNotEmpty(
            $this->container->mailer()->emailsSentFor()
        );
    }
}
```

#### 3. Adapter Tests (PHPUnit)
**What**: Test infrastructure adapters (repositories, controllers, API clients)
**Where**: `tests/Integration/Infrastructure/`
**Tools**: PHPUnit with real infrastructure
**Speed**: Slower (uses database, HTTP, etc.)
**Coverage**: Port adapters work correctly

**Two types**:

**A. Contract Tests** (for outgoing port adapters like repositories):
```php
// tests/Integration/Infrastructure/Persistence/OrderRepositoryContractTest.php
final class OrderRepositoryContractTest extends TestCase {
    /** @dataProvider repositories */
    public function test_it_can_save_and_retrieve_orders(
        OrderRepository $repository
    ): void {
        $order = Order::create(/* ... */);
        $repository->save($order);

        $retrieved = $repository->ofId($order->id());

        $this->assertEquals($order, $retrieved);
    }

    public function repositories(): Generator {
        yield [new InMemoryOrderRepository()];
        yield [new DoctrineOrderRepository(/* real DB */)];
    }
}
```

**B. Driving Tests** (for incoming port adapters like controllers):
```php
// tests/Integration/Infrastructure/Api/RegisterUserControllerTest.php
final class RegisterUserControllerTest extends WebTestCase {
    public function test_it_correctly_invokes_register_user(): void {
        $application = $this->createMock(ApplicationInterface::class);
        $application->expects($this->once())
            ->method('registerUser')
            ->with(new RegisterUser('test@example.com', 'pass'));

        $client = self::createClient();
        $client->getContainer()->set(ApplicationInterface::class, $application);

        $client->request('POST', '/auth/register', [
            'email' => 'test@example.com',
            'password' => 'pass'
        ]);

        $this->assertResponseIsSuccessful();
    }
}
```

#### 4. End-to-End Tests (Behat + Real Infrastructure)
**What**: Test complete system as black box with real HTTP, database, etc.
**Where**: Same `features/*.feature` files, different suite
**Tools**: Behat with real Symfony kernel, web server
**Speed**: Slowest (full stack)
**Coverage**: Everything works together in production-like environment

**Key**: Reuse the same scenarios from use case tests but with different context:

```php
// features/bootstrap/E2EContext.php (makes real HTTP requests)
final class E2EContext implements Context {
    private KernelInterface $kernel;
    private ?Response $response = null;

    /** @When a customer registers with email :email */
    public function aCustomerRegistersWithEmail(string $email): void {
        $this->response = $this->kernel->handle(
            Request::create('/auth/register', 'POST', [
                'email' => $email,
                'password' => 'password123'
            ])
        );
    }
}
```

### Test Execution

```bash
# Unit tests (fast - run constantly)
vendor/bin/phpunit --testsuite=unit

# Use case tests (fast - run frequently)
vendor/bin/behat --suite=usecase

# Adapter tests (slower - run before commit)
vendor/bin/phpunit --testsuite=integration

# End-to-end tests (slow - run before deploy)
vendor/bin/behat --suite=e2e

# All tests
composer test
```

## Development Workflow (TDD with Behat)

Following Noback's top-down approach from Section 14.7:

### Step 1: Write the Scenario (Gherkin)
```gherkin
# features/user-registration.feature
Feature: User Registration
  Scenario: Successfully register a new user
    Given no user exists with email "john@example.com"
    When I register with email "john@example.com" and password "SecurePass123!"
    Then the user should be registered
```

### Step 2: Create Step Definitions (RED)
```php
// features/bootstrap/UseCaseContext.php
final class UseCaseContext implements Context {
    private TestServiceContainer $container;

    public function __construct() {
        $this->container = new TestServiceContainer();
    }

    /** @When I register with email :email and password :password */
    public function iRegisterWith(string $email, string $password): void {
        // This will be RED - code doesn't exist yet
        $this->container->application()->registerUser(
            new RegisterUser($email, $password)
        );
    }
}
```

### Step 3: Implement Domain Model (TDD)
As you implement, **write PHPUnit unit tests** for domain objects:

```php
// tests/Unit/Domain/User/EmailTest.php - Write these AS YOU BUILD
final class EmailTest extends TestCase {
    public function test_it_validates_format(): void {
        $this->expectException(\InvalidArgumentException::class);
        Email::fromString('invalid');
    }

    public function test_it_normalizes_email(): void {
        $email = Email::fromString('  TEST@Example.COM  ');
        $this->assertEquals('test@example.com', $email->toString());
    }
}
```

### Step 4: Continue Until GREEN
- Implement command handler
- Create repository interface + in-memory implementation
- Add spy objects to TestServiceContainer
- Keep running `vendor/bin/behat --suite=usecase`
- Keep running `vendor/bin/phpunit --testsuite=unit`
- **Both must be GREEN before moving on**

### Step 5: Write Adapter Tests
Now test the infrastructure layer:

```php
// tests/Integration/Infrastructure/Persistence/UserRepositoryContractTest.php
final class UserRepositoryContractTest extends TestCase {
    /** @dataProvider repositories */
    public function test_it_persists_users(UserRepository $repository): void {
        $user = User::register(/* ... */);
        $repository->save($user);

        $retrieved = $repository->ofId($user->id());

        $this->assertEquals($user, $retrieved);
    }

    public function repositories(): Generator {
        yield [new InMemoryUserRepository()];
        yield [new DoctrineUserRepository(/* real DB */)];
    }
}
```

### Step 6: Write End-to-End Tests
Reuse the SAME scenarios with a different context:

```php
// features/bootstrap/E2EContext.php
final class E2EContext implements Context {
    private KernelInterface $kernel;

    /** @When I register with email :email and password :password */
    public function iRegisterWith(string $email, string $password): void {
        // Real HTTP request to real API
        $this->response = $this->kernel->handle(
            Request::create('/auth/register', 'POST', [
                'email' => $email,
                'password' => $password
            ])
        );
    }
}
```

### Summary: Test Pyramid

```
         /\
        /  \  E2E Tests (Behat e2e suite)
       /    \ Few, slow, production-like
      /------\
     / Adapter \ Adapter Tests (PHPUnit integration)
    /  Tests   \ Real database, controllers
   /------------\
  /  Use Case    \ Use Case Tests (Behat usecase suite)
 /     Tests      \ TestServiceContainer + spies
/------------------\
/   Unit Tests      \ Unit Tests (PHPUnit)
--------------------  Many, fast, domain objects
```

**Key Principles**:
1. ✅ Start with scenarios (collaboration, shared understanding)
2. ✅ Test-drive implementation (RED → GREEN → Refactor)
3. ✅ Unit tests for domain objects (zoom in on invariants)
4. ✅ Use case tests document features (living documentation)
5. ✅ Adapter tests verify infrastructure (contract + driving tests)
6. ✅ Few E2E tests for confidence (same scenarios, different context)

### Code Style

```bash
# Format code
vendor/bin/php-cs-fixer fix

# Check without fixing
vendor/bin/php-cs-fixer fix --dry-run --diff

# Static analysis (MUST pass level 10)
composer analyze

# Run tests
composer test

# Validate architecture boundaries
vendor/bin/deptrac analyse
```

## Common Patterns

### Creating a New Entity

```php
// Domain/Order/Order.php
class Order {
    private OrderId $id;
    private UserId $userId;
    private Money $total;
    private OrderStatus $status;
    private array $domainEvents = [];

    // Named constructor - tells a story
    public static function place(OrderId $id, UserId $userId, Money $total): self {
        $order = new self($id, $userId, $total, OrderStatus::pending());
        $order->recordEvent(new OrderWasPlaced($id, $userId, $total));
        return $order;
    }

    // Behavior - business logic lives here
    public function complete(): void {
        if (!$this->status->canTransitionTo(OrderStatus::completed())) {
            throw new InvalidOrderStateException();
        }
        $this->status = OrderStatus::completed();
        $this->recordEvent(new OrderWasCompleted($this->id));
    }
}
```

### Creating a Value Object

```php
// Domain/Shared/ValueObject/Money.php
final readonly class Money {
    private function __construct(
        private int $amountInCents,
        private Currency $currency
    ) {
        if ($amountInCents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative');
        }
    }

    public static function fromCents(int $cents, Currency $currency): self {
        return new self($cents, $currency);
    }

    public static function fromFloat(float $amount, Currency $currency): self {
        return new self((int) round($amount * 100), $currency);
    }

    public function add(self $other): self {
        if (!$this->currency->equals($other->currency)) {
            throw new \InvalidArgumentException('Cannot add different currencies');
        }
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function toFloat(): float {
        return $this->amountInCents / 100;
    }
}
```

### Creating a Command Handler

```php
// Application/Order/Command/PlaceOrderHandler.php
#[AsMessageHandler]
final readonly class PlaceOrderHandler {
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    public function __invoke(PlaceOrder $command): void {
        // Load domain objects
        $user = $this->userRepository->ofId(UserId::fromString($command->userId));
        if (!$user) {
            throw new UserNotFoundException();
        }

        // Create value objects
        $orderId = $this->orderRepository->nextIdentity();
        $total = Money::fromCents($command->totalInCents, Currency::usd());

        // Use domain to create entity
        $order = Order::place($orderId, $user->id(), $total);

        // Persist
        $this->orderRepository->add($order);

        // Domain events are dispatched automatically by Doctrine event listeners
    }
}
```

## Important Principles

### ❌ DO NOT

- ❌ Use `doctrine:generate:entity` (creates anemic models)
- ❌ Put business logic in services
- ❌ Expose entity internals with getters (prefer behavior methods)
- ❌ Use domain objects in API responses (use DTOs)
- ❌ Put framework code in Domain/Application layers
- ❌ Create circular dependencies between layers

### ✅ DO

- ✅ Use named constructors (`User::register()`, not `new User()`)
- ✅ Validate in value object constructors
- ✅ Make value objects immutable (readonly)
- ✅ Put business logic in entity methods
- ✅ Define repository interfaces in Domain, implement in Infrastructure
- ✅ Use domain events for side effects
- ✅ Keep handlers thin (orchestration only)
- ✅ Test domain logic without framework

## API Development

### Authentication Flow

1. **Register**: `POST /auth/register` → Returns 201 Created
2. **Login**: `POST /auth/login` → Returns JWT token
3. **Authenticated requests**: Add `Authorization: Bearer <token>` header
4. **Get current user**: `GET /api/users/me`

### Adding a New Endpoint

1. Create Command/Query in Application layer
2. Create Handler with `#[AsMessageHandler]`
3. Create State Processor/Provider in Infrastructure/Api/State
4. Add API Resource or operation in Infrastructure/Api/Resource
5. Write functional test

## Configuration

### Environment Variables

```bash
# .env
DATABASE_URL="postgresql://user:pass@localhost:5432/weightlog?serverVersion=16"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase
JWT_TOKEN_TTL=3600  # 1 hour
```

### Doctrine Mapping

We use **XML mapping** (not annotations/attributes) to keep domain entities pure:

```xml
<!-- Infrastructure/Persistence/Doctrine/Mapping/User.orm.xml -->
<entity name="App\Domain\User\User" table="users">
    <id name="id" type="user_id" column="id"/>
    <embedded name="email" class="App\Domain\User\ValueObject\Email" use-column-prefix="false">
        <field name="value" type="string" column="email"/>
    </embedded>
</entity>
```

## References

- **Matthias Noback**: "Advanced Web Application Architecture"
- **Matthias Noback**: https://matthiasnoback.nl/book/a-year-with-symfony/
- **API Platform**: https://api-platform.com/docs/
- **Symfony Messenger**: https://symfony.com/doc/current/messenger.html
- **Hexagonal Architecture**: https://alistair.cockburn.us/hexagonal-architecture/
- **DDD**: "Domain-Driven Design" by Eric Evans

## Migration Notes

This project was migrated from Laravel 12 to Symfony 7.3. See `migration.md` for the full migration plan and implementation steps.

**Key architectural changes**:
- Cookie-based auth → JWT tokens
- Anemic models → Rich domain models
- Inline validation → Value objects
- Service classes → CQRS with command/query handlers
- Direct Eloquent → Repository pattern with Doctrine

---

**IMPORTANT**: When working on this codebase, always respect the architectural boundaries. The domain layer must remain pure PHP with zero framework dependencies. Use Deptrac to validate: `vendor/bin/deptrac analyse`
