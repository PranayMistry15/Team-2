# Team 2 | Laptro E-commerce

Laptro is a student-focused laptop e-commerce web application built using PHP, MySQL, Bootstrap, and JavaScript. It includes a full customer storefront and an admin panel, covering product browsing, ordering, returns, and support.

## Features

- Product catalogue with search, filters, and pagination
- Product pages with specifications, ratings, and reviews
- Cart and checkout with stock management
- User accounts (login, profile, orders, password changes)
- Returns system for completed orders
- Customer dashboard with order history and service reviews
- Support chat system with admin queue
- Admin panel for managing products, orders, customers, returns, and inventory
- Clean routing using `.htaccess`

## Tech Stack

- PHP
- MySQL / MariaDB
- Apache
- Bootstrap
- JavaScript
- Composer (optional Eloquent ORM support)

## Setup

1. Place the project in your web root (e.g. `htdocs`)
2. Install dependencies with `composer install`
3. Create a database and import `database/database.sql`
4. Import any optional patches from `database/patches/` if needed
5. Update `.env` if needed
6. Start Apache and MySQL, then open `http://localhost/laptro-ecommerce/`

## Demo Admin

- Email: `admin@laptro.com`
- Password: `password`

Change these after setup.

## Notes

- Must be run through Apache (uses `.htaccess` routing)
- Logs are stored in `storage/logs/`
- Payment system is demo only (no real transactions)
