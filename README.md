# [AirSense (Backend)](https://github.com/salimi-my/airsense-backend) &middot; [![Author Salimi](https://img.shields.io/badge/Author-Salimi-%3C%3E)](https://www.salimi.my)

A Laravel-based API server for real-time air quality monitoring, personalized health risk assessments, and user management. The system ingests AQI data from WAQI, exposes station readings and alerts for the Klang Valley, integrates an AI risk classifier, and provides robust authentication with 2FA and OAuth.

## 📋 Overview

AirSense Backend powers the AirSense platform's data and intelligence layer. It tracks air quality stations across the Klang Valley, fetches hourly readings from the World Air Quality Index (WAQI) API, stores historical pollutant data, and delivers personalized health guidance via an external AI service with rule-based fallbacks. The platform includes role-based access control, two-factor authentication, OAuth integration, a user dashboard with preferred-station support, and deployment notifications via webhook.

## ✨ Features

### 🌬️ Air Quality Monitoring

- **WAQI Integration**: Hourly ingestion of AQI and pollutant data via `app:fetch-aqi-data` scheduled command
- **Multi-Pollutant Readings**: AQI, PM2.5, PM10, NO₂, O₃, CO, temperature, humidity, and wind speed
- **Stale Data Detection**: Configurable staleness threshold (`stale_reading_hours`, default 2 hours)
- **AQI Categories**: US EPA-style categories with color classes and hex colors via `AQIHelper`
- **Historical Readings**: Up to 7 days of deduplicated readings per station

### 📍 Station Management

- **Klang Valley Coverage**: Pre-seeded stations (Petaling Jaya, Kuala Lumpur, Shah Alam, Klang, Putrajaya)
- **Station Listing**: Active stations with latest reading, category, and staleness flag
- **Station Detail**: Individual station with full latest reading payload
- **Nearby Lookup**: Find nearest station by lat/lng with coverage radius check (`max_station_distance_km`, default 50 km)
- **Health Alerts**: Stations with AQI > 100 flagged for sensitive groups

### 🧠 Risk Assessment

- **Personalized Assessment**: AI-powered risk classification based on age group, health conditions, and planned activity
- **Rule-Based Fallback**: Automatic fallback when AI service is unavailable or misconfigured
- **AQI Prediction**: Short-term AQI trend prediction from historical readings via AI service
- **Assessment History**: Paginated personal assessment log (`/api/me/assessments`)
- **Confidence Scoring**: Low-confidence flag when AI confidence < 0.5

### 📊 Dashboard

- **User Dashboard**: Preferred station, last assessment, weekly assessment count, active alerts, valley average AQI
- **Admin Metrics**: Assessments today, stale station count, last fetch timestamp (admin role only)
- **Preferred Station**: Users set a preferred station via profile update for personalized dashboard data

### 👥 User Management

- **User CRUD**: Complete user management with profile updates
- **Role-Based Access Control**: Admin and User roles with policy-based permissions
- **Avatar Management**: Upload and delete user profile pictures
- **Email Change Management**: Secure email change with verification
- **Password Management**: Change password with old password verification
- **Bulk User Operations**: Bulk delete users (admin only)

### 🔐 Security & Authentication

- **Multi-Factor Authentication**:
    - Laravel Sanctum token-based API authentication
    - Google 2FA (TOTP) with QR code setup
    - Recovery codes for account recovery
- **OAuth Integration**:
    - Google OAuth login/registration
    - GitHub OAuth login/registration
    - OAuth account linking for existing users
    - OAuth account unlinking
    - Mobile token authentication endpoint
- **Email Verification**: Required email verification for new accounts
- **Password Reset**: Extended password reset with configurable expiration
- **Guest & Verified Middleware**: Protect guest and verified-only routes
- **Throttling**: Rate limiting on sensitive endpoints (login, 2FA, email verification, OAuth)

### 🚢 Deployment

- **Deployment Webhook**: `POST /api/deployment-webhook` receives status from deployment scripts
- **Email Notifications**: Notifies configured admins on successful or failed deployments (via Resend)
- **Token Authentication**: Secured via `DEPLOYMENT_TOKEN` header (`X-Deployment-Token`)
- **Commit Metadata**: Supports `commit_url` and `commit_author` in webhook payload

### 🛠️ Developer Tools

- **Laravel Boost**: AI-assisted development tools and guidelines (`AGENTS.md`)
- **IDE Helper**: Laravel IDE helper for better autocomplete
- **Database Seeders**:
    - Role seeder (admin, user)
    - Default admin/user accounts
    - Klang Valley station seeder
    - Historical reading seeder

## 🏗️ Architecture

### Tech Stack

- **Framework**: Laravel 13
- **PHP**: 8.3+
- **Authentication**: Laravel Sanctum
- **2FA**: PragmaRX Google2FA
- **OAuth**: Laravel Socialite (Google, GitHub)
- **Starter Kit**: smarttechtank/larastarter
- **Email**: Resend (via resend/resend-laravel)
- **QR Codes**: Bacon QR Code
- **Recovery Codes**: PragmaRX Recovery
- **External APIs**: WAQI (World Air Quality Index), Hugging Face AI Space
- **Database**: MySQL (default; SQLite supported for local dev)

### Database Schema

#### Users Table

- Personal information (name, email, phone, gender)
- Password and email verification
- Two-factor authentication fields (secret, recovery codes)
- OAuth provider fields (Google ID, GitHub ID, tokens)
- Email change management fields
- Avatar storage path
- Foreign key to roles table
- `preferred_station_id` (nullable FK to stations): user's preferred air quality station

#### Roles Table

- Role name and description
- Relationships: one-to-many with users

#### Stations Table

- Name, city, latitude, longitude
- `waqi_slug`: unique WAQI feed identifier
- `is_active` boolean
- Timestamps

#### Readings Table

- Station ID (foreign key)
- AQI, PM2.5, PM10, NO₂, O₃, CO
- Temperature, humidity, wind speed
- `fetched_at`: WAQI observation timestamp
- Timestamps; indexed by `(station_id, fetched_at)`

#### Assessments Table

- User ID, station ID (foreign keys)
- `age_group`, `conditions` (JSON), `activity`
- `risk_level`, `advice`, `precautions` (JSON)
- `confidence`, `used_fallback`, `assessed_at`
- Timestamps

### Key Models

#### Station Model

- Relationships: has many Readings, has many Assessments, has one latestReading
- Active scope via `is_active` flag

#### Reading Model

- Belongs to Station
- `dedupeByFetchedAt()` for deduplicating hourly observations
- `latestFirst` scope for chronological queries

#### Assessment Model

- Belongs to User and Station
- Conditions and precautions cast as arrays
- Tracks AI confidence and fallback usage

#### User Model

- Role-based authorization, 2FA, OAuth linking, email change flow
- `preferredStation` relationship (belongs to Station via `preferred_station_id`)
- Relationships: belongs to Role, belongs to preferred Station

## 🚀 Getting Started

### Prerequisites

- PHP 8.3 or higher
- Composer
- MySQL (recommended) or PostgreSQL/SQLite
- Node.js & npm (for Vite assets, if needed)
- Resend API key (for production email sending)
- WAQI API token (for live air quality data)
- AI service URL (Hugging Face Space; optional — fallback rules apply when unset)

### Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/salimi-my/airsense-backend.git
    cd airsense-backend
    ```

2. **Install dependencies**

    ```bash
    composer install
    ```

3. **Environment configuration**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4. **Configure your `.env` file**
    - Database credentials (`DB_*`)
    - `FRONTEND_URL` for CORS and email links
    - `RESEND_API_KEY` (for email sending)
    - OAuth credentials (Google, GitHub)
    - `REGISTRATION_ENABLED`, `SOCIAL_AUTH_ENABLED`
    - `WAQI_API_TOKEN` — get from [aqicn.org/data-platform/token](https://aqicn.org/data-platform/token/)
    - `AI_SERVICE_URL` — Hugging Face Space URL (no trailing slash)
    - `DEPLOYMENT_TOKEN` and `DEPLOYMENT_NOTIFICATION_EMAILS` (for deployment webhook)

5. **Run migrations and seeders**

    ```bash
    php artisan migrate
    php artisan db:seed
    ```

6. **Fetch live AQI data** (optional, requires `WAQI_API_TOKEN`)

    ```bash
    php artisan app:fetch-aqi-data
    ```

### Development Server

```bash
# All-in-one dev environment (server, queue, logs, Vite)
composer run dev
```

Or run services individually:

```bash
php artisan serve
php artisan queue:listen --tries=1
php artisan pail --timeout=0
```

## 📡 API Documentation

API routes are defined in `routes/api.php`. All authenticated routes require Sanctum token auth and verified email unless noted.

### Authentication Endpoints

#### Public Routes (Guest)

- `POST /api/login` - User login
- `POST /api/register` - User registration (if `REGISTRATION_ENABLED`)
- `POST /api/forgot-password` - Request password reset
- `POST /api/reset-password` - Reset password with token
- `POST /api/two-factor/verify` - Verify 2FA code
- `POST /api/auth/{provider}/token` - OAuth token authentication (google|github, mobile)

#### Protected Routes (Authenticated)

- `GET /api/user` - Get current user info
- `POST /api/logout` - Logout user
- `GET /api/verify-email/{id}/{hash}` - Verify email address
- `POST /api/email/verification-notification` - Resend verification email

### User Management Endpoints

- `GET /api/users` - List users (filtering, sorting, pagination)
- `POST /api/users` - Create user (admin)
- `GET /api/users/{id}` - Get user details
- `PUT/PATCH /api/users/{id}` - Update user (admin)
- `DELETE /api/users/{id}` - Delete user (admin)
- `DELETE /api/users/bulk-delete` - Bulk delete users (admin)
- `PUT/PATCH /api/users/update-profile` - Update own profile (includes `preferred_station_id`)
- `PUT/PATCH /api/users/update-password` - Update own password
- `PUT/PATCH /api/users/upload-avatar` - Upload avatar
- `DELETE /api/users/delete-avatar` - Delete avatar
- `POST /api/users/resend-password-reset` - Resend password reset email

### Email Change Endpoints

- `POST /api/users/email-change/request` - Request email change
- `GET /api/email-change/verify/{id}/{token}/{email}` - Verify new email
- `POST /api/users/email-change/resend` - Resend verification email
- `DELETE /api/users/email-change/cancel` - Cancel email change request
- `GET /api/users/email-change/status` - Get email change status

### Two-Factor Authentication Endpoints

- `GET /api/two-factor/setup` - Get QR code for 2FA setup
- `POST /api/two-factor/enable` - Enable 2FA
- `POST /api/two-factor/disable` - Disable 2FA
- `GET /api/two-factor/recovery-codes` - Get recovery codes
- `POST /api/two-factor/recovery-codes/regenerate` - Regenerate recovery codes

### OAuth Endpoints

- `DELETE /api/auth/{provider}/unlink` - Unlink OAuth provider (google|github)

### Role Management Endpoints

- `GET /api/roles` - List roles
- `POST /api/roles` - Create role (admin)
- `GET /api/roles/{id}` - Get role details
- `PUT/PATCH /api/roles/{id}` - Update role (admin)
- `DELETE /api/roles/{id}` - Delete role (admin)
- `DELETE /api/roles/bulk-delete` - Bulk delete roles (admin)

### Air Quality Endpoints

- `GET /api/dashboard` - User dashboard (preferred station, assessments, alerts, valley avg AQI; admin metrics for admins)
- `GET /api/stations` - List active stations with latest readings
- `GET /api/stations/alerts` - Stations with AQI > 100
- `GET /api/stations/nearby?lat=&lng=` - Nearest station by coordinates
- `GET /api/stations/{id}` - Station detail with latest reading
- `GET /api/stations/{id}/readings?days=7` - Historical readings (max 7 days)
- `GET /api/stations/{id}/prediction` - AQI trend prediction from historical data
- `POST /api/assessments` - Submit personalized risk assessment
    - Body: `{ "station_id": 1, "age_group": "adult", "conditions": ["none"], "activity": "light_outdoor" }`
    - `age_group`: `child`, `teen`, `adult`, `elderly`
    - `conditions`: `none`, `asthma`, `heart_disease`, `respiratory`, `diabetes`
    - `activity`: `indoor`, `light_outdoor`, `moderate_exercise`, `strenuous_exercise`
- `GET /api/me/assessments` - Paginated personal assessment history

### Admin Log Endpoints (Admin Only)

- `GET /api/admin/readings` - Paginated readings log
- `GET /api/admin/assessments` - Paginated assessments log

### Deployment Endpoints

- `POST /api/deployment-webhook` - Receive deployment status (token required, no auth)

### Query Parameters

#### Filtering & Search

- `search` - Search across relevant text fields (users, roles)
- `genders` - Filter users by gender (comma-separated)
- `roles` - Filter users by role IDs (comma-separated)

#### Pagination

- `page` - Page number
- `per_page` - Items per page (default varies by endpoint)

#### Sorting

- `sort` - Sort by field (format: `field.direction`)
    - Examples: `name.asc`, `created_at.desc`, `assessed_at.desc`

## 📁 Project Structure

```
airsense-backend/
├── app/
│   ├── Console/Commands/
│   │   └── FetchAQIData.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── API/
│   │   │   │   ├── AdminLogController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── DeploymentWebhookController.php
│   │   │   │   ├── MeAssessmentController.php
│   │   │   │   ├── RiskAssessmentController.php
│   │   │   │   ├── RoleAPIController.php
│   │   │   │   ├── StationController.php
│   │   │   │   └── UserAPIController.php
│   │   │   ├── Auth/
│   │   │   └── AppBaseController.php
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   │   ├── Assessment.php
│   │   ├── Reading.php
│   │   ├── Role.php
│   │   ├── Station.php
│   │   └── User.php
│   ├── Notifications/
│   ├── Policies/
│   ├── Repositories/
│   │   ├── BaseRepository.php
│   │   ├── RoleRepository.php
│   │   └── UserRepository.php
│   ├── Services/
│   │   ├── AIRiskService.php
│   │   └── WAQIService.php
│   └── Support/
│       ├── AQIHelper.php
│       └── GeoHelper.php
├── config/
│   └── airsense.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   ├── auth.php
│   ├── console.php
│   └── web.php
├── deployment-script-client.sh
├── tests/
├── composer.json
└── README.md
```

## 🔐 Security Features

### Authentication & Authorization

- **Token-Based API Auth**: Laravel Sanctum for stateless API authentication
- **Role-Based Access Control (RBAC)**: Policies for User and Role; admin checks on admin log endpoints
- **Email Verification**: Required for account activation
- **2FA Support**: Google Authenticator compatible TOTP
- **Recovery Codes**: Encrypted backup codes for 2FA recovery
- **Rate Limiting**: Throttling on login, 2FA, email verification, and OAuth endpoints

### Data Protection

- **Password Hashing**: Bcrypt hashing
- **Signed URLs**: Email verification and email change verification
- **Deployment Token**: Webhook secured with `X-Deployment-Token` middleware
- **SQL Injection Protection**: Eloquent ORM with parameter binding

## 🧪 Testing

```bash
# Run all tests
composer test

# Or manually
php artisan test --compact

# Run a specific file or filter
php artisan test --compact tests/Feature/StationApiTest.php
php artisan test --compact --filter=testName
```

## 🚢 Deployment

### Frontend Deployment Script

`deployment-script-client.sh` deploys the AirSense frontend and POSTs status to `/api/deployment-webhook`. Configure:

- `PROJECT_DIR`, `WEBHOOK_URL`, `DEPLOYMENT_TOKEN`, `GITHUB_REPO_URL`
- Match `DEPLOYMENT_TOKEN` in the API `.env`

### Backend Deployment

Deploy the API host with the same webhook pattern: merge latest code, run `composer install`, migrations, `php artisan optimize`, reload the app, and POST status to `/api/deployment-webhook` with the `X-Deployment-Token` header.

### Production Checklist

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Configure production database (MySQL recommended)
3. Configure Resend for email (`RESEND_API_KEY`)
4. Set OAuth credentials for production callback URLs
5. Set `WAQI_API_TOKEN` and `AI_SERVICE_URL`
6. Set `DEPLOYMENT_TOKEN` and `DEPLOYMENT_NOTIFICATION_EMAILS`
7. Configure queue worker (supervisor, systemd)
8. Set up cron for scheduled tasks (`app:fetch-aqi-data` runs hourly)
9. Optimize application:

    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    composer install --optimize-autoloader --no-dev
    ```

### Queue Configuration

```
QUEUE_CONNECTION=database  # or redis, sqs, etc.
```

Start queue worker:

```bash
php artisan queue:work --tries=3
```

### Scheduled Tasks

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs `app:fetch-aqi-data` hourly to ingest WAQI readings for all active stations.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow PSR-12 coding standards
- Use Laravel best practices and repository pattern for user/role data access
- Write tests for new features
- Run Laravel Pint before committing
- Air quality endpoints may query models directly; user/role CRUD uses repositories

### Code Quality Tools

```bash
# Format code with Laravel Pint
vendor/bin/pint --dirty

# Generate IDE helper files
php artisan ide-helper:generate
php artisan ide-helper:meta
```

## 📝 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙋 Support

For issues, questions, or contributions, please open an issue on the [repository](https://github.com/salimi-my/airsense-backend/issues).

## 🔄 Version History

### Current Version

- Laravel 13.x
- PHP 8.3+
- Klang Valley air quality monitoring via WAQI
- AI-powered personalized risk assessments with rule-based fallback
- User dashboard with preferred station and admin metrics
- AQI prediction from historical readings
- 2FA and OAuth support
- Deployment webhook notifications

## 👨‍💻 Developer Notes

### Repository Pattern

User and role data access is decoupled from controllers via repositories:

- `BaseRepository` — shared CRUD and query helpers
- `UserRepository`, `RoleRepository`

Air quality controllers (`StationController`, `DashboardController`, etc.) query Eloquent models directly for domain-specific logic.

Controllers use `AppBaseController::sendResponse()` / `sendError()`.

### Policy-Based Authorization

Authorization is handled through Laravel Policies:

- `UserPolicy`, `RolePolicy`

Admin-only endpoints (admin logs) additionally check `role.name === 'admin'`.

### WAQI Data Ingestion

`app:fetch-aqi-data` (scheduled hourly) fetches readings for all active stations via `WAQIService`, parses pollutant data, and inserts new `Reading` records. Run manually after seeding or when configuring a new WAQI token:

```bash
php artisan app:fetch-aqi-data
```

### AI Risk Service

`AIRiskService` calls the configured Hugging Face Space at `{AI_SERVICE_URL}/assess` and `{AI_SERVICE_URL}/predict`. When the service is unavailable or `AI_SERVICE_URL` is empty, assessments fall back to `AQIHelper::fallbackAdvisory()` with `used_fallback: true`.

### Preferred Station

Users set their preferred station via `PUT/PATCH /api/users/update-profile` with `{ "preferred_station_id": 1 }`. The dashboard surfaces this station's latest reading, category, and staleness status.

### Configuration

AirSense-specific settings live in `config/airsense.php`:

- `waqi.base_url`, `waqi.token`, `waqi.timeout`
- `ai_service.url`, `ai_service.timeout`
- `stale_reading_hours` (default 2)
- `max_station_distance_km` (default 50)

---

**Built with ❤️ using Laravel**
