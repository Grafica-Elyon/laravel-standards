---
name: laravel-architecture
description: Laravel architecture guide for code organization using the Action Pattern, integration Services, and Support classes. Use this skill EVERY TIME you create, refactor, or review Laravel code involving business logic, controllers, services, actions, or any decision about where to place code. Also trigger when the user asks to create an endpoint, feature, CRUD with business rules, external API integration, or when the agent needs to decide between controller, action, service, model, or helper. If the Laravel code has more than a simple Model::create(), this skill must be consulted.
---

# Laravel Architecture Guide

Architectural guide for Laravel projects. Defines where each type of logic should live, how to structure Actions, Services, and Support classes, and which anti-patterns to avoid.

## Directory Structure

```
app/
├── Actions/          # Business operations (one class = one operation)
├── Services/         # External API and SDK integrations
├── Support/          # Stateless helpers and utilities
├── Http/
│   ├── Controllers/  # HTTP bridge: request → action → response
│   └── Requests/     # FormRequests for validation
├── Models/           # Eloquent + scopes + relationships (this IS the repository)
└── DTOs/             # Data Transfer Objects (optional, when arrays aren't enough)
```

## Decision: where does the logic go?

Before writing any code, follow this decision tree:

```
The logic I'm writing...

├─ Is a query with reusable filters or conditions?
│  → Scope on the Model (scopeActive, scopeByStatus, etc.)
│
├─ Is simple CRUD with no side effects (no events, no notifications, no transaction)?
│  → Can stay in the Controller directly
│
├─ Is a business operation with logic beyond simple CRUD?
│  (transaction, events, notifications, conditional validation, side effects)
│  → Action
│
├─ Is communication with an external API, SDK, or third-party service?
│  (Stripe, Correios, Bling, Twilio, HTTP APIs)
│  → Service in app/Services/{Provider}/
│
├─ Is a stateless transformation, formatting, or utility calculation?
│  (format currency, validate CPF, generate slug, convert units)
│  → Support class in app/Support/
│
└─ None of the above?
   → Stop and evaluate. If it doesn't fit any category, it probably
     belongs in the Model (domain behavior) or needs to be broken
     into smaller parts.
```

## Actions

### What it is

An Action is a single-responsibility class that encapsulates a business operation. It receives data, performs the operation, and returns the result. It knows nothing about HTTP.

### Rules

1. **One public method**: `handle()`. Internal private methods are allowed to organize steps.
2. **Never receives Request or Response**. Receives primitive data: array, DTO, or Model.
3. **Never decides HTTP status codes**. Never returns `response()->json()`. Returns the result (Model, bool, DTO) or throws an exception.
4. **Use DB::transaction** when there are multiple write operations or side effects that need to be atomic.
5. **Can inject other Actions** via constructor to compose complex operations.
6. **Does not need an interface**. An Action will never have an alternative implementation.
7. **Does not validate request data**. Validation is the FormRequest's responsibility. The Action assumes data arrives already validated.

### Naming

Pattern: `{Verb}{Resource}` — always describes the operation.

```
CreateOrder.php
CancelSubscription.php
RecalculateOrderTotal.php
ApplyDiscountToCart.php
DeactivateUser.php
```

### Location

`app/Actions/` flat by default. If the project grows beyond ~50 Actions, reorganize by domain:

```
app/Actions/
├── Order/
│   ├── CreateOrder.php
│   ├── CancelOrder.php
│   └── RecalculateOrderTotal.php
├── User/
│   ├── CreateUser.php
│   └── DeactivateUser.php
```

### Simple Action example

```php
<?php

namespace App\Actions;

use App\Models\Order;

class CreateOrder
{
    public function handle(array $data): Order
    {
        return Order::create([
            'user_id' => $data['user_id'],
            'total' => $data['total'],
            'status' => 'pending',
        ]);
    }
}
```

### Action with transaction and composition

```php
<?php

namespace App\Actions;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CreateOrder
{
    public function __construct(
        private CalculateOrderTotal $calculateTotal,
        private NotifyWarehouse $notifyWarehouse,
    ) {}

    public function handle(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = $this->createRecord($data);
            $this->attachItems($order, $data['items']);
            $this->calculateTotal->handle($order);
            $this->notifyWarehouse->handle($order);

            return $order;
        });
    }

    private function createRecord(array $data): Order
    {
        return Order::create([
            'user_id' => $data['user_id'],
            'status' => 'pending',
        ]);
    }

    private function attachItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $order->items()->create($item);
        }
    }
}
```

### Action with custom exception

```php
<?php

namespace App\Actions;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;

class CreateOrder
{
    public function handle(array $data): Order
    {
        $product = Product::findOrFail($data['product_id']);

        if ($product->stock < $data['quantity']) {
            throw new InsufficientStockException($product, $data['quantity']);
        }

        // ... continues with the operation
    }
}
```

The controller handles the exception and decides what to return to the user:

```php
public function store(StoreOrderRequest $request, CreateOrder $action)
{
    try {
        $order = $action->handle($request->validated());

        return response()->json($order, 201);
    } catch (InsufficientStockException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }
}
```

## Controllers

### Role

The controller is the HTTP bridge. It does three things:

1. Receives and validates the request (via FormRequest)
2. Calls the Action (or interacts with the Model for simple CRUD)
3. Handles exceptions and formats the HTTP response

### Rules

1. **Contains no business logic**. If there's an `if/else` deciding a business rule, extract it to an Action.
2. **Uses FormRequest** for validation, never validates inline.
3. **Handles exceptions** from the Action and decides the HTTP status code.
4. **Can call multiple Actions** if the endpoint requires it (rare, but valid).

### Example

```php
<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrder;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CreateOrder $action): JsonResponse
    {
        $order = $action->handle($request->validated());

        return response()->json($order, 201);
    }
}
```

### Simple CRUD without Action

When it's straightforward CRUD with no additional logic, no Action is needed:

```php
public function store(StorePostRequest $request): JsonResponse
{
    $post = Post::create($request->validated());

    return response()->json($post, 201);
}

public function index(): JsonResponse
{
    $posts = Post::active()->latest()->paginate();

    return response()->json($posts);
}
```

## Models

### Role

The Eloquent Model IS the repository layer. Do not create Repository classes to wrap Eloquent.

### What belongs in the Model

- Relationships (`hasMany`, `belongsTo`, etc.)
- Scopes for reusable queries (`scopeActive`, `scopeByStatus`)
- Accessors and Mutators
- Casts
- Table configuration, fillable, etc.

### Scope example

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }
}
```

Usage: `Product::active()->byCategory(3)->get()`

## Services (External integrations)

### Role

Classes that encapsulate communication with external APIs, SDKs, or third-party services. This is the only legitimate use case for "Service" in the architecture.

### Rules

1. Located in `app/Services/{Provider}/`
2. Can have multiple methods (they represent integration capabilities, not business operations)
3. Do not contain business rules — they only translate calls to/from external APIs
4. Are injectable via constructor into Actions that need them

### Structure

```
app/Services/
├── Stripe/
│   ├── StripeClient.php
│   └── StripeWebhookHandler.php
├── Correios/
│   └── CorreiosClient.php
└── Bling/
    └── BlingClient.php
```

### Example

```php
<?php

namespace App\Services\Stripe;

use Stripe\StripeClient as BaseStripeClient;

class StripeClient
{
    private BaseStripeClient $client;

    public function __construct()
    {
        $this->client = new BaseStripeClient(config('services.stripe.secret'));
    }

    public function createCustomer(string $email, string $name): string
    {
        $customer = $this->client->customers->create([
            'email' => $email,
            'name' => $name,
        ]);

        return $customer->id;
    }

    public function charge(string $customerId, int $amountInCents, string $currency = 'brl'): string
    {
        $intent = $this->client->paymentIntents->create([
            'customer' => $customerId,
            'amount' => $amountInCents,
            'currency' => $currency,
        ]);

        return $intent->id;
    }
}
```

### Service used by an Action

```php
<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Subscription;
use App\Services\Stripe\StripeClient;
use Illuminate\Support\Facades\DB;

class CreateSubscription
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function handle(User $user, array $planData): Subscription
    {
        return DB::transaction(function () use ($user, $planData) {
            $stripeCustomerId = $this->stripe->createCustomer(
                $user->email,
                $user->name,
            );

            return $user->subscriptions()->create([
                'plan_id' => $planData['plan_id'],
                'stripe_customer_id' => $stripeCustomerId,
                'status' => 'active',
            ]);
        });
    }
}
```

## Support (Helpers and Utilities)

### Role

Stateless classes for transformations, formatting, and utility calculations. No dependency on HTTP or database.

### Location

`app/Support/`

### Example

```php
<?php

namespace App\Support;

class CurrencyFormatter
{
    public static function toReais(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    public static function toCents(float $reais): int
    {
        return (int) round($reais * 100);
    }
}
```

## Anti-patterns — what NOT to do

### 1. Service as sub-controller

```php
// ❌ WRONG: the Service became a sub-controller
class OrderService
{
    public function store(array $data): Order { /* all logic here */ }
    public function update(Order $order, array $data): Order { /* ... */ }
    public function cancel(Order $order): void { /* ... */ }
}

// Controller becomes an empty shell
public function store(Request $request, OrderService $service)
{
    return $service->store($request->validated());
}
```

The problem: you moved code around but solved nothing. The Service bloats with methods, unnecessary constructor dependencies, and each method deals with different concerns.

```php
// ✅ CORRECT: each operation is an Action
public function store(StoreOrderRequest $request, CreateOrder $action)
{
    $order = $action->handle($request->validated());
    return response()->json($order, 201);
}
```

### 2. Repository wrapping Eloquent

```php
// ❌ WRONG: useless wrapper
class UserRepository
{
    public function findActive(): Collection
    {
        return User::where('active', true)->get();
    }
}
```

```php
// ✅ CORRECT: scope on the Model
class User extends Model
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
// Usage: User::active()->get()
```

### 3. Interface for each Action

```php
// ❌ WRONG: bureaucracy with no benefit
interface CreateOrderInterface
{
    public function handle(array $data): Order;
}

class CreateOrder implements CreateOrderInterface { /* ... */ }
```

Interfaces only make sense when there are multiple implementations. `CreateOrder` will never have an alternative implementation.

### 4. HTTP concerns inside an Action

```php
// ❌ WRONG: Action knows about HTTP
class CreateOrder
{
    public function handle(Request $request): JsonResponse
    {
        $order = Order::create($request->validated());
        return response()->json($order, 201);
    }
}
```

```php
// ✅ CORRECT: Action receives pure data, returns pure data
class CreateOrder
{
    public function handle(array $data): Order
    {
        return Order::create($data);
    }
}
```

### 5. firstOrFail() in Action without controller handling

```php
// ❌ WRONG: firstOrFail throws 404 that the controller doesn't handle
class CreateSubscription
{
    public function handle(array $data): Subscription
    {
        $plan = Plan::where('slug', $data['plan_slug'])->firstOrFail();
        // if plan doesn't exist, user sees a generic 404
    }
}
```

```php
// ✅ CORRECT option A: use first() and throw a custom exception
class CreateSubscription
{
    public function handle(array $data): Subscription
    {
        $plan = Plan::where('slug', $data['plan_slug'])->first();

        if (! $plan) {
            throw new PlanNotFoundException($data['plan_slug']);
        }

        // ...
    }
}

// ✅ CORRECT option B: use firstOrFail() but handle it in the controller
public function store(Request $request, CreateSubscription $action)
{
    try {
        $subscription = $action->handle($request->validated());
        return response()->json($subscription, 201);
    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Plan not found'], 404);
    } catch (SubscriptionException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}
```

### 6. Validation inside the Action

```php
// ❌ WRONG: Action validating data
class CreateUser
{
    public function handle(array $data): User
    {
        $validated = Validator::make($data, [
            'email' => 'required|email|unique:users',
        ])->validate();

        return User::create($validated);
    }
}
```

Validation is the FormRequest's responsibility. The Action receives already validated data.