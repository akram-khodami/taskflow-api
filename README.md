<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>
# TaskFlow API

TaskFlow is a lightweight Laravel-based API for managing projects, tasks, and comments with user authentication (Sanctum). It provides RESTful endpoints for registering/logging-in users, creating and organizing projects, assigning and tracking tasks, and commenting on tasks — suitable as a backend for a task/kanban frontend or mobile client.

## Stack
- Language(s): PHP (Laravel) + Blade (views/assets)
- Framework / runtime: Laravel 13 (Laravel framework ^13.x)
- Notable libraries: Laravel Sanctum (token authentication), Laravel Tinker, PHPUnit (testing), Vite (frontend tooling)

## Key features
- User registration, login, logout (Sanctum)
- Projects: create, list, show, update, delete, restore, force-delete
- Tasks: per-project and global task CRUD, task status updates
- Comments: create, update, delete comments and replies
- "My tasks" view for authenticated users

## Repo layout (top-level)
```
app/                Application source (Models, Http/Controllers, Requests, Resources, Policies)
bootstrap/          Framework bootstrap
config/             Configuration files
database/           Migrations & seeders
public/             Public assets
resources/          Blade views / frontend assets
routes/             route definitions (routes/api.php, routes/web.php, routes/console.php)
tests/              PHPUnit / feature tests
composer.json       PHP dependencies and project metadata
package.json        npm / frontend tooling (Vite)
phpunit.xml         PHPUnit config
.env.example        Example environment variables
artisan             Laravel CLI
vite.config.js      Vite configuration
```

How it fits together:
- Incoming HTTP requests hit routes defined in routes/api.php.
- API controllers live under app/Http/Controllers/Api/V1 and delegate to Requests, Resources and Models in app/.
- Persistence is handled via Eloquent Models (app/Models: User, Project, Task, Comment).
- Authentication uses Laravel Sanctum; protected endpoints are grouped by `auth:sanctum` middleware.

---

## Getting started — local development

Prerequisites
- PHP (>= 8.3)
- Composer
- MySQL/Postgres (or other supported DB)
- Node.js + npm
- Git

Install and run
```bash
# clone
git clone https://github.com/akram-khodami/taskflow-api.git
cd taskflow-api

# PHP dependencies
composer install

# Node dependencies (for frontend or asset build)
npm install

# env
cp .env.example .env
# edit .env and set DB_*, APP_URL, and other values

# application key
php artisan key:generate

# migrate & seed
php artisan migrate
php artisan db:seed    # if seeds exist and you want sample data

# run frontend dev server (if developing assets)
npm run dev

# serve API (or use your preferred webserver)
php artisan serve
```

Notes
- If you have Docker or a Sail setup, you can adapt these steps to that environment.
- The project uses Vite for asset building (see package.json / vite.config.js).

Environment variables
- Copy .env.example to .env and set at minimum:
  - APP_NAME, APP_URL
  - DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
  - MAIL settings if you need email features
- Sanctum tokens/cookies: the API protects routes using Sanctum; ensure APP_URL and SANCTUM state are configured for your client if using cookie-based auth.

Running tests
```bash
# Run the test suite
./vendor/bin/phpunit
# or
php artisan test
```

---

## Authentication (brief)
- Register: POST /api/v1/register
- Login: POST /api/v1/login
- Logout: POST /api/v1/logout (protected)
- Get current user: GET /api/v1/user (protected)

Authentication uses Laravel Sanctum — the API expects a valid bearer token or configured sanctum cookie session for protected routes.

Example: include header
```
Authorization: Bearer <token>
```

---

## API overview (major endpoints)
The API routes are defined in routes/api.php (versioned under `v1`). Below is a concise summary of the main groups and resources:

- Projects
  - GET /api/v1/projects — list projects
  - POST /api/v1/projects — create project
  - GET /api/v1/projects/{project} — show project
  - PUT /api/v1/projects/{project} — update project
  - DELETE /api/v1/projects/{project} — soft delete
  - POST /api/v1/projects/{id}/restore — restore soft-deleted project
  - DELETE /api/v1/projects/{id}/force — permanently delete

- Tasks
  - GET /api/v1/tasks — list all tasks
  - POST /api/v1/tasks — create task
  - GET /api/v1/tasks/{task} — show task
  - PUT /api/v1/tasks/{task} — update task
  - DELETE /api/v1/tasks/{task} — delete task
  - Project-scoped tasks: endpoints under /api/v1/projects/{project}/tasks for tasks belonging to a project
  - PATCH /api/v1/tasks/{task}/status — update task status (example pattern)

- Comments
  - GET /api/v1/comments — list comments
  - POST /api/v1/comments — create comment
  - PUT /api/v1/comments/{comment} — update
  - DELETE /api/v1/comments/{comment} — delete
  - Replies are handled as nested/comment-reply endpoints

- My Tasks
  - GET /api/v1/my-tasks — tasks assigned to the authenticated user

All protected routes require authentication (Sanctum).

For exact controller names and available actions, see routes/api.php and controllers under app/Http/Controllers/Api/V1.

---

## Models
Inspect app/Models for the core domain models:
- User
- Project
- Task
- Comment

These models implement relationships and business rules used by controllers and resources.

---

## Contributing
- Open issues for bugs or feature requests.
- For code contributions, fork the repo, create a branch, add tests where applicable, and open a PR describing the change.
- Keep code consistent with existing patterns (Requests for validation, Resources for responses, Policies for authorization).

---

## Useful files
- routes/api.php — API route definitions (versioned v1)
- app/Http/Controllers/Api/V1 — API controllers (resources & actions)
- app/Models — Eloquent models
- database/migrations — DB schema
- phpunit.xml — test config
- .env.example — example env

---

## License
MIT (see composer.json)

---

If you'd like, I can:
- Generate a more detailed API reference (endpoint parameters and example requests/responses) by reading controllers and Request classes.
- Create Postman/HTTP client collection for quick testing.
- Add developer setup for Docker / Sail.
