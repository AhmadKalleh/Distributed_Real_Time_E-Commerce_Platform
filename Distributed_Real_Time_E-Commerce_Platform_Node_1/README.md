# Distributed Real-Time E-Commerce Platform

Internet Applications course project (2026). An e-commerce backend built with **Laravel 10 / PHP 8.3 / MySQL 8**.

This README covers **Requirement 1: API Design & Security**.

## Quick start (Docker)

```bash
docker compose up --build
```

Then open:

| What | URL |
|---|---|
| Swagger UI (interactive docs) | http://localhost:8080/docs |
| OpenAPI spec | http://localhost:8080/docs/openapi.yaml |
| Health check | http://localhost:8080/api/v1/health |

On first start the container waits for MySQL, runs migrations, seeds demo data and generates `APP_KEY` and `JWT_SECRET`. The keys are saved in the `app-storage` volume, so tokens stay valid after a restart. To set your own, export `APP_KEY` / `JWT_SECRET` before `docker compose up`.

### Demo accounts

| Role | Email | Password |
|---|---|---|
| Responsible (admin) | `responsible@example.test` | `ChangeThisLocalPassword123!` |
| Customer | `customer1@example.test` | `CustomerDemo123!` |
| Customer | `customer2@example.test` | `CustomerDemo123!` |

## Requirement 1 checklist

| Requirement | Where it is implemented |
|---|---|
| **Language / framework** | PHP 8.3, Laravel 10, MySQL 8 |
| **4 separate modules** | `app/Modules/{Users,Products,Orders,Notifications}`. Each module has its own service provider, routes, controllers, requests, resources and policies. Orders and Notifications talk only through events (`OrderPlaced`, `OrderStatusChanged`). |
| **Runs fully on Docker** | `Dockerfile`, `docker-compose.yml`, `docker/entrypoint.sh` |
| **REST: products** | add with images, show, filter by category, pagination: `app/Modules/Products` |
| **REST: orders** | create, view, change status: `app/Modules/Orders` |
| **Versioning in the path** | every endpoint is under `/api/v1/...` |
| **Correct status codes** | 200, 201 + `Location`, 204, 401, 403, 404, 405, 409, 422, 429 |
| **Unified error format** | `app/Exceptions/Handler.php` (see below) |
| **Documentation** | OpenAPI 3 spec `docs/openapi.yaml` + Swagger UI at `/docs` (has a "Try it out" button and Bearer auth) |
| **JWT authentication** | `app/Modules/Users/Services/JwtService.php` (HS256, `exp`/`nbf`/`iss`/`jti`, revocation on logout), guard in `UsersServiceProvider` |
| **2 roles** | `Customer` sees only their own orders; `Responsible` (admin) is the only role that can change order status or add products: `OrderPolicy`, `ProductPolicy` |
| **Limit login attempts** | 5 failed attempts per email + IP lock the account for 5 minutes (`429` + `Retry-After`), plus 20 auth requests/min per IP: `AuthController::login`, `UsersServiceProvider` |
| **Security headers** | `app/Http/Middleware/SecurityHeaders.php`: CSP, HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, `Cache-Control: no-store` on authenticated responses, `X-Powered-By` removed |
| **Proof of rejection** | `tests/Feature/AuthorizationProofTest.php` |

## API overview

All endpoints are under `/api/v1`. Full details are in Swagger.

| Method | Path | Who |
|---|---|---|
| POST | `/auth/register` | public (always creates a Customer) |
| POST | `/auth/login` | public (rate limited) |
| GET | `/auth/me` | any authenticated user |
| POST | `/auth/logout` | any authenticated user (revokes the token) |
| GET | `/users` | Responsible |
| GET | `/categories` | public |
| GET | `/products?category=&search=&min_price=&max_price=&sort=&page=&per_page=` | public |
| GET | `/products/{id}` | public |
| POST | `/products` (multipart, `images[]`) | Responsible |
| GET | `/orders` | Customer: own orders only. Responsible: all orders |
| POST | `/orders` | Customer |
| GET | `/orders/{id}` | owner or Responsible |
| PATCH | `/orders/{id}/status` | Responsible |
| GET | `/notifications` | own notifications only |
| PATCH | `/notifications/{id}/read` | owner only |

### Email notifications

The Notifications module listens to `OrderPlaced` and `OrderStatusChanged`. For each one it saves an in-app notification and emails a copy through SMTP:

- order placed: the customer and every Responsible get an email;
- status changed: the order owner gets an email.

The mail settings come from `MAIL_*` in `.env` (Gmail SMTP: `smtp.gmail.com:587`, TLS, with a Google **app password**). Docker Compose reads the same values from the project `.env`. Set `MAIL_NOTIFICATIONS_ENABLED=false` to turn the emails off. If SMTP fails, the error is logged and the API request still succeeds.

Emails are queued. With `QUEUE_CONNECTION=database` you need a worker running: `php artisan queue:work`. Docker uses `sync`, so emails go out immediately there.

Order lifecycle: `Pending → Confirmed → Shipped → Delivered`. `Pending` and `Confirmed` orders can also go to `Cancelled`, which returns the stock. Any other transition gets `422 INVALID_STATUS_TRANSITION`.

### Unified error format

```json
{
  "error": {
    "status": 422,
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": { "email": ["The email field is required."] }
  }
}
```

Codes: `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `VALIDATION_ERROR`, `INVALID_CREDENTIALS`, `TOO_MANY_LOGIN_ATTEMPTS`, `TOO_MANY_REQUESTS`, `INSUFFICIENT_STOCK`, `INVALID_STATUS_TRANSITION`, `INTERNAL_ERROR`.

## Proof: unauthorized access is rejected

Run the tests (needs PHP 8.1+ with `pdo_sqlite`):

```bash
composer install
php artisan test
```

`AuthorizationProofTest` checks that:

- customer B gets **403** when opening customer A's order, and their order list contains only their own orders;
- a customer gets **403** when changing order status, even on their own order;
- a customer gets **403** when adding products or listing users;
- a user gets **403** when marking someone else's notification as read (this applies to the admin too);
- requests with no token or an invalid token get **401**;
- a token whose payload was edited to claim `Responsible` gets **401** because the signature check fails;
- an `alg: none` token gets **401**.

`AuthTest` covers login lockout, the per-IP rate limit, logout revocation, expired tokens, and that a demoted admin loses access right away (the role is read from the database on every request, not trusted from the token).

### Manual demo with curl

```bash
# 1. Log in as two customers and the admin
C1=$(curl -s localhost:8080/api/v1/auth/login -H 'Content-Type: application/json' -d '{"email":"customer1@example.test","password":"CustomerDemo123!"}' | sed -E 's/.*"access_token":"([^"]+)".*/\1/')
C2=$(curl -s localhost:8080/api/v1/auth/login -H 'Content-Type: application/json' -d '{"email":"customer2@example.test","password":"CustomerDemo123!"}' | sed -E 's/.*"access_token":"([^"]+)".*/\1/')

# 2. Customer 1 places an order (note the id)
curl -s localhost:8080/api/v1/orders -H "Authorization: Bearer $C1" -H 'Content-Type: application/json' -d '{"items":[{"product_id":1,"quantity":1}]}'

# 3. Customer 2 tries to read it  -> 403 FORBIDDEN
curl -i localhost:8080/api/v1/orders/1 -H "Authorization: Bearer $C2" -H 'Accept: application/json'

# 4. Customer 1 tries to mark it delivered  -> 403 FORBIDDEN
curl -i -X PATCH localhost:8080/api/v1/orders/1/status -H "Authorization: Bearer $C1" -H 'Content-Type: application/json' -d '{"status":"Delivered"}'
```

## Project layout

```
app/
  Exceptions/Handler.php          unified JSON error format
  Http/Middleware/SecurityHeaders.php
  Http/Middleware/EnsureUserHasRole.php
  Modules/
    Users/          auth (JWT), roles, user listing
    Products/       categories, products, image upload
    Orders/         order placement, stock reservation, status lifecycle, events
    Notifications/  listens to order events, per-user inbox
docs/openapi.yaml   API documentation
docker/             container entrypoint
tests/Feature/      authorization proof and API tests
```

## Running without Docker

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* and JWT_SECRET in .env
php artisan migrate --seed
php artisan storage:link
php artisan serve
```
