# ChefLagbe – Chef Booking Platform

ChefLagbe is a web-based marketplace in Bangladesh that connects customers with professional chefs. Users can explore chefs, book cooking services, manage bookings, and share reviews through a simple and responsive platform.

## Features

- User Authentication (Customer, Chef & Admin)
- Chef Profiles & Service Listings
- Online Booking Management
- Payment Simulation
- Customer & Chef Dashboards
- Admin Dashboard
- Reviews & Ratings
- REST API Integration
- Responsive Design

## Technology Stack

| Category | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12 |
| Frontend | HTML5, CSS3, JavaScript |
| Database | MySQL 8.0 |
| API | REST API, Laravel Sanctum |
| API Testing | Postman |
| Code Editor | VS Code |

## Installation

**1. Clone the repository**

```bash
git clone YOUR_REPOSITORY_URL
cd ChefLagbe
```

**2. Install dependencies**

```bash
composer install
```

**3. Configure the environment**

```bash
cp .env.example .env
php artisan key:generate
```

**4. Create a MySQL database**

```sql
CREATE DATABASE cheflagbe_simple;
```

Update your database credentials in `.env`.

**5. Run migrations and seed data**

```bash
php artisan migrate --seed
php artisan storage:link
```

**6. Start the server**

```bash
php artisan serve
```

Visit: `http://127.0.0.1:8000`

## Demo Accounts

Password for all accounts: `DemoPass123!`

| Role | Email |
|---|---|
| Customer | customer1@cheflagbe.test |
| Chef | chef1@cheflagbe.test |
| Admin | admin@cheflagbe.test |

*Demo accounts are for local development only.*

## Project Structure

```text
app/          Controllers, Models & Services
database/     Migrations & Seeders
resources/    Blade Views
routes/       Web & API Routes
public/       CSS, JavaScript & Images
docs/         API Documentation & ER Diagram
tests/        Automated Tests
```

## Important Notes

- Payments are simulated; no real transactions are processed.
- Demo data is fictional.
- The project is under development and requires testing before production deployment.
- A separate offline HTML preview is also included.

## Developer

**Naimul Islam**

**Project:** ChefLagbe – Chef Booking & Marketplace Platform
