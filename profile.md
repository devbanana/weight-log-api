# User Profile Implementation Plan - Backend

## Overview

Implement user registration with date of birth validation and post-registration onboarding to collect profile statistics. Users register with email, DOB, display name, and password, then complete their profile with sex, current weight, height, activity level, and goal type.

## Requirements

### Registration (Fortify)
- **Collection Timing**: During initial registration
- **Required Fields**: email, date_of_birth, display_name, password
- **Age Validation**: Minimum 18 years old
- **Field Order**: email → date_of_birth → display_name → password

### Onboarding (Post-Registration)
- **Collection Timing**: Immediately after registration before accessing app
- **Required Fields**: sex, starting_weight_kg, height_cm, weight_unit, height_unit, activity_level, goal_type
- **Storage Format**: Metric (cm/kg) in database, convert from user's selected units
- **Sex Field**: Male/Female only (for biological calculations), with respectful explanation
- **Unit Preferences**: Separate for weight and height (not global)
- **Weight Units**: kg, lbs, or stone (stone shown as two fields: stones + pounds)
- **Height Units**: cm or ft_in (feet+inches shown as two fields)

## Database Schema

### Migration 1: Rename name to display_name

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_rename_name_to_display_name_in_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('name', 'display_name');
});
```

### Migration 2: Add date_of_birth to users table

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_date_of_birth_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->date('date_of_birth')->nullable()->after('password');
});
```

**Note**: Added separately so existing users can be handled gracefully.

### Migration 3: Add profile fields to users table

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_profile_fields_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->enum('sex', ['male', 'female'])->nullable()->after('date_of_birth');
    $table->decimal('height_cm', 6, 2)->nullable()->after('sex');
    $table->decimal('starting_weight_kg', 8, 4)->nullable()->after('height_cm');
    $table->enum('weight_unit', ['kg', 'lbs', 'stone'])->default('lbs')->after('starting_weight_kg');
    $table->enum('height_unit', ['cm', 'ft_in'])->default('ft_in')->after('weight_unit');
    $table->enum('activity_level', ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active'])->nullable()->after('height_unit');
    $table->enum('goal_type', ['lose_weight', 'maintain_weight', 'gain_weight'])->nullable()->after('activity_level');
    $table->timestamp('profile_completed_at')->nullable()->after('goal_type');
});
```

### Field Specifications

**Registration Fields**:
- `email`: String, unique, required
- `date_of_birth`: Date, required, must be 18+ years old
- `display_name`: String, required (renamed from `name`)
- `password`: String, hashed, required

**Profile Fields**:
- `sex`: Enum (male, female) - for biological calculations (TDEE, BMR)
- `height_cm`: Decimal(6,2) - stores height in centimeters (max 9999.99 cm)
- `starting_weight_kg`: Decimal(8,4) - stores weight in kilograms with 4 decimal precision
- `weight_unit`: Enum (kg, lbs, stone) - user's preferred weight display unit
- `height_unit`: Enum (cm, ft_in) - user's preferred height display unit
- `activity_level`: Enum (sedentary, lightly_active, moderately_active, very_active, extremely_active)
- `goal_type`: Enum (lose_weight, maintain_weight, gain_weight)
- `profile_completed_at`: Timestamp - tracks when onboarding was completed

### Precision Rationale
- **Height**: 2 decimals (72.5 in = 184.15 cm converts exactly)
- **Weight**: 4 decimals prevents rounding errors (12 stone 7 lbs = 79.3787 kg)

### Unit Conversion Reference
- **Pounds to kg**: 1 lb = 0.453592 kg
- **Stone to kg**: 1 stone = 6.35029 kg
- **Stone + pounds to kg**: (stones × 6.35029) + (pounds × 0.453592)
- **Inches to cm**: 1 inch = 2.54 cm
- **Feet to cm**: 1 foot = 30.48 cm
- **Feet + inches to cm**: (feet × 30.48) + (inches × 2.54)

## Backend Implementation

### 1. Update Fortify Registration

**File**: `app/Actions/Fortify/CreateNewUser.php`

```php
use Illuminate\Validation\Rules\Password;

public function create(array $input): User
{
    Validator::make($input, [
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'date_of_birth' => [
            'required',
            'date',
            'before:today',
            'before_or_equal:' . now()->subYears(18)->format('Y-m-d')
        ],
        'display_name' => ['required', 'string', 'max:255'],
        'password' => ['required', 'string', Password::default(), 'confirmed'],
    ], [
        'date_of_birth.before_or_equal' => 'You must be at least 18 years old to use this application.',
    ])->validate();

    return User::create([
        'email' => $input['email'],
        'date_of_birth' => $input['date_of_birth'],
        'display_name' => $input['display_name'],
        'password' => Hash::make($input['password']),
    ]);
}
```

### 2. User Model Updates

**File**: `app/Models/User.php`

**Update `$fillable`**:
```php
protected $fillable = [
    'display_name',
    'email',
    'password',
    'date_of_birth',
    'sex',
    'height_cm',
    'starting_weight_kg',
    'weight_unit',
    'height_unit',
    'activity_level',
    'goal_type',
    'profile_completed_at',
];
```

**Update `$casts`**:
```php
protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
    'date_of_birth' => 'date',
    'profile_completed_at' => 'datetime',
    'height_cm' => 'decimal:2',
    'starting_weight_kg' => 'decimal:4',
];
```

**Add helper methods**:
```php
public function hasCompletedProfile(): bool
{
    return !is_null($this->profile_completed_at);
}

public function getAgeAttribute(): ?int
{
    if (!$this->date_of_birth) {
        return null;
    }

    return $this->date_of_birth->diffInYears(now());
}
```

### 3. Form Request Validation

**File**: `app/Http/Requests/CompleteProfileRequest.php`

```php
public function rules(): array
{
    return [
        'sex' => ['required', 'in:male,female'],
        'height_cm' => ['required', 'numeric', 'min:30', 'max:300'],
        'starting_weight_kg' => ['required', 'numeric', 'min:2', 'max:700'],
        'weight_unit' => ['required', 'in:kg,lbs,stone'],
        'height_unit' => ['required', 'in:cm,ft_in'],
        'activity_level' => ['required', 'in:sedentary,lightly_active,moderately_active,very_active,extremely_active'],
        'goal_type' => ['required', 'in:lose_weight,maintain_weight,gain_weight'],
    ];
}
```

**Validation Rules**:
- **Sex**: Required, male or female (for biological calculations)
- **Height**: 30-300 cm (roughly 1-10 feet)
- **Weight**: 2-700 kg (roughly 4-1500 lbs, or 0.3-110 stone)
- **Weight Unit**: Required, kg/lbs/stone
- **Height Unit**: Required, cm/ft_in
- **Activity Level**: Required, one of 5 levels
- **Goal Type**: Required, one of 3 goal types

### 4. API Resource

**File**: `app/Http/Resources/UserResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'display_name' => $this->display_name,
        'email' => $this->email,
        'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
        'age' => $this->age,
        'sex' => $this->sex,
        'height_cm' => $this->height_cm,
        'starting_weight_kg' => $this->starting_weight_kg,
        'weight_unit' => $this->weight_unit,
        'height_unit' => $this->height_unit,
        'activity_level' => $this->activity_level,
        'goal_type' => $this->goal_type,
        'profile_completed' => $this->hasCompletedProfile(),
        'profile_completed_at' => $this->profile_completed_at,
    ];
}
```

### 5. API Controller

**File**: `app/Http/Controllers/Api/ProfileController.php`

```php
public function completeProfile(CompleteProfileRequest $request)
{
    $user = $request->user();

    $user->update([
        'sex' => $request->sex,
        'height_cm' => $request->height_cm,
        'starting_weight_kg' => $request->starting_weight_kg,
        'weight_unit' => $request->weight_unit,
        'height_unit' => $request->height_unit,
        'activity_level' => $request->activity_level,
        'goal_type' => $request->goal_type,
        'profile_completed_at' => now(),
    ]);

    return new UserResource($user);
}

public function status(Request $request)
{
    return response()->json([
        'completed' => $request->user()->hasCompletedProfile(),
    ]);
}
```

### 6. API Routes

**File**: `routes/api.php`

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return new UserResource($request->user());
    });

    Route::post('/user/complete-profile', [ProfileController::class, 'completeProfile']);
    Route::get('/user/profile-status', [ProfileController::class, 'status']);
});
```

### 7. Testing

**File**: `tests/Feature/Auth/RegistrationTest.php`

**Test Cases**:
- ✓ User can register with valid data (email, DOB, display_name, password)
- ✓ Registration fails with missing fields
- ✓ Registration fails when user is under 18 years old
- ✓ Registration fails with future date of birth
- ✓ Registration fails with duplicate email
- ✓ Display name is stored instead of name
- ✓ Date of birth is stored correctly

**File**: `tests/Feature/ProfileCompletionTest.php`

**Test Cases**:
- ✓ Authenticated user can complete profile with valid data
- ✓ Profile completion sets profile_completed_at timestamp
- ✓ Profile completion returns updated user resource
- ✓ Validation fails with missing fields
- ✓ Validation fails with invalid sex value
- ✓ Validation fails with invalid activity_level
- ✓ Validation fails with invalid goal_type
- ✓ Validation fails with out-of-range height/weight
- ✓ Age accessor correctly calculates age from date of birth
- ✓ Profile status endpoint returns correct completion status
- ✓ Unauthenticated users cannot access endpoints

## API Response Format

### User Resource Response

```json
{
  "id": "1",
  "display_name": "John",
  "email": "john@example.com",
  "date_of_birth": "1990-01-15",
  "age": 35,
  "sex": "male",
  "height_cm": 180.34,
  "starting_weight_kg": 79.3787,
  "weight_unit": "stone",
  "height_unit": "ft_in",
  "activity_level": "moderately_active",
  "goal_type": "lose_weight",
  "profile_completed": true,
  "profile_completed_at": "2025-01-15T10:30:00.000000Z"
}
```

**Field Notes**:
- `display_name`: User's chosen display name (less formal than "name")
- `date_of_birth`: ISO date format (YYYY-MM-DD)
- `age`: Calculated automatically by backend
- `sex`: male/female for biological calculations
- `height_cm`: Always stored in cm, converted from user's height_unit
- `starting_weight_kg`: Always stored in kg, converted from user's weight_unit
- `weight_unit`: User's preferred weight display unit (kg/lbs/stone)
- `height_unit`: User's preferred height display unit (cm/ft_in)
- `activity_level`: One of 5 activity levels
- `goal_type`: lose_weight, maintain_weight, or gain_weight

## Implementation Order

1. ✓ Create migration to rename `name` to `display_name`
2. ✓ Create migration to add `date_of_birth` column
3. ✓ Create migration to add profile fields
4. ✓ Update User model (fillable, casts, helper methods)
5. ✓ Update Fortify CreateNewUser action with DOB validation
6. ✓ Create CompleteProfileRequest form request
7. ✓ Create UserResource API resource
8. ✓ Create ProfileController with completeProfile and status methods
9. ✓ Add API routes for profile endpoints
10. ✓ Update `/api/user` endpoint to use UserResource
11. ✓ Write Pest tests for registration with DOB
12. ✓ Write Pest tests for profile completion
13. ✓ Run `vendor/bin/pint --dirty` for code formatting

## Notes

- **Display Name**: Less formal than "name", users don't need to provide full legal name
- **Date of Birth**: Collected during registration (not onboarding) with 18+ validation
- **Sex vs Gender**: Using "sex" for biological/metabolic calculations, respecting gender identity
- **Age Calculation**: Automatic from date_of_birth for TDEE/BMR formulas
- **Metric Storage**: All measurements stored in metric (cm/kg) for calculation consistency
- **Unit Preferences**: Separate for weight and height, not a global preference
- **Stone Support**: UK imperial weight unit (1 stone = 14 pounds = 6.35029 kg)
- **Precision**: 4 decimals for weight prevents rounding errors across all unit conversions
- **Profile Completion**: Tracked via timestamp, required before accessing main app features
- **Privacy**: Display calculated age only on profile, not full date of birth
