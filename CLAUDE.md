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
- **Static Analysis**: PHPStan Level 9
- **Code Style**: Easy Coding Standard (ECS)
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

### Writing Tests

- **Domain tests** - Unit tests, no framework, fast
- **Application tests** - Unit tests with mocked repositories
- **Infrastructure tests** - Integration tests with real database
- **API tests** - Functional tests hitting HTTP endpoints

### Code Style

```bash
# Format code
vendor/bin/ecs check src --fix

# Static analysis (MUST pass level 9)
vendor/bin/phpstan analyse

# Run tests
vendor/bin/phpunit

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
