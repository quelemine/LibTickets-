# 🎫 LibTickets - Event Ticketing Platform

**Liberia's Premier Event Ticketing Platform**

A complete event ticketing and booking management system built with PHP and MySQL.

---

## 🚀 Overview

LibTickets is a full-featured event ticketing platform that allows users to:
- Browse and search events by category, city, county, and date
- Purchase tickets for events with seat selection
- Manage bookings and view tickets with QR codes
- Receive tickets via email delivery
- Admin dashboard for complete event and booking management

---

## ✨ Features

### For Customers
- 🔍 **Event Discovery** - Browse events with advanced filtering
- 🎟️ **Easy Booking** - Guest checkout or account-based purchasing
- 💺 **Seat Selection** - Visual seat map for venue seating
- 📱 **QR Code Tickets** - Scannable tickets for entry
- 📧 **Email Delivery** - Instant ticket delivery via email
- 👤 **Customer Dashboard** - View bookings, tickets, and profile
- 🔐 **2FA Security** - Optional two-factor authentication
- 🔔 **Notifications** - Stay updated on bookings and events

### For Administrators
- 📊 **Dashboard** - Real-time stats on events, bookings, and sales
- 🎭 **Event Management** - Full CRUD for events with categories
- 🎫 **Ticket Types** - Multiple ticket types per event with pricing
- 🏛️ **Venue Management** - Manage venues with capacity and location
- 🎬 **Slideshow** - Homepage carousel management
- ✅ **Ticket Validation** - Scan and validate QR codes at entry
- 📈 **Activity Log** - Track all administrative actions
- ⚙️ **Settings** - Configure site details, social links, and theme
- 💱 **Exchange Rates** - Manage USD/LRD exchange rates

---

## 🛠️ Technology Stack

| Component | Technology |
|-----------|------------|
| **Backend** | PHP 8.2+ |
| **Database** | MySQL 8.0+ with PDO |
| **Web Server** | Apache with mod_rewrite |
| **Email** | PHPMailer |
| **Frontend** | Vanilla JavaScript, HTML5, CSS3 |
| **Dependencies** | Composer (PHP package manager) |
| **Development** | Mailpit (email testing) |

---

## 📋 Requirements

- **PHP** 8.2 or higher
- **MySQL** 8.0 or higher
- **Apache** with mod_rewrite enabled
- **Composer** for dependency management
- **SMTP Server** or Mailpit for email (development)

---

## 🚀 Installation

### 1. Clone or Download the Project
```bash
git clone https://github.com/yourusername/libtickets.git
cd libtickets
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Configure Environment
Copy `.env.example` to `.env` and configure:
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=libtickets
DB_USER=root
DB_PASS=your_password

# SMTP Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_SECURE=true

# Development Mode (set to false in production)
DEV_MODE=true
```

### 4. Import Database Schema
```bash
mysql -u root -p libtickets < database/schema.sql
```

### 5. Configure Apache
Set DocumentRoot to the `public/` directory:
```apache
<VirtualHost *:80>
    DocumentRoot "/path/to/libtickets/public"
    ServerName libtickets.local
    
    <Directory "/path/to/libtickets/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 6. Set Permissions
```bash
chmod 755 public/uploads
chmod 755 cache/ratelimit
```

### 7. Start Mailpit (Development)
```bash
# Windows
.\mailpit\mailpit.exe

# Linux/Mac
mailpit
```

Access Mailpit at: http://localhost:8025

---

## 📁 Project Structure

```
libtickets/
├── public/                  # Public web root
│   ├── api/                # PHP API endpoints
│   │   ├── admin.php       # Admin dashboard (25 endpoints)
│   │   ├── bookings.php    # Booking management (3 endpoints)
│   │   ├── tickets.php     # Ticket operations (4 endpoints)
│   │   ├── customer.php    # Customer dashboard (10 endpoints)
│   │   ├── login.php       # Authentication
│   │   ├── 2fa.php         # Two-factor auth
│   │   └── ...            # Other endpoints
│   ├── js/                # Frontend JavaScript
│   │   ├── app.js         # Main application
│   │   ├── admin.js       # Admin dashboard
│   │   └── dashboard.js   # Customer dashboard
│   ├── css/               # Stylesheets
│   ├── uploads/           # User-uploaded files
│   ├── index.html         # Homepage
│   ├── admin.html         # Admin dashboard page
│   └── dashboard.html     # Customer dashboard page
├── src/                   # PHP core classes
│   ├── Db.php            # Database connection (PDO)
│   ├── Auth.php          # Authentication & sessions
│   ├── Response.php      # JSON response formatting
│   ├── Validator.php     # Input validation
│   ├── Mailer.php        # Email sending (PHPMailer)
│   ├── Token.php         # Token generation & validation
│   └── RateLimiter.php   # Rate limiting protection
├── config/
│   └── config.php        # Application configuration
├── vendor/               # Composer dependencies
├── cache/               # Rate limit cache
├── .htaccess            # Apache URL rewriting
├── .env                 # Environment variables
├── composer.json        # PHP dependencies
└── README.md            # This file
```

---

## 🔌 API Endpoints

LibTickets provides 72 RESTful API endpoints. See [PHP_API_ENDPOINTS.md](PHP_API_ENDPOINTS.md) for complete documentation.

### Quick Reference

#### Authentication
- `POST /api/login` - User login
- `POST /api/register` - New user registration
- `POST /api/logout` - User logout
- `GET /api/session` - Check session status

#### Public
- `GET /api/events` - List all events (with filtering)
- `GET /api/events/:id` - Get event details
- `GET /api/categories` - List categories
- `GET /api/venues` - List venues

#### Bookings (Guest or Authenticated)
- `POST /api/bookings` - Create new booking
- `GET /api/bookings/:reference` - Get booking details
- `POST /api/bookings/retrieve` - Retrieve tickets by email

#### Tickets
- `GET /api/tickets/:id/qr` - Generate QR code
- `GET /api/tickets/:id/html` - View printable ticket
- `POST /api/tickets/validate` - Validate ticket (admin only)

#### Customer Dashboard (Authentication Required)
- `GET /api/customer/dashboard-stats` - Dashboard statistics
- `GET /api/customer/bookings` - List user bookings
- `GET /api/customer/tickets` - List user tickets
- `PUT /api/customer/profile` - Update profile

#### Admin Dashboard (Admin Only)
- `GET /api/admin/dashboard-stats` - Admin statistics
- `POST /api/admin/events` - Create event
- `PUT /api/admin/events/:id` - Update event
- `DELETE /api/admin/events/:id` - Delete event
- `POST /api/admin/ticket-types` - Create ticket type
- ...and 20 more admin endpoints

---

## 🧪 Testing

### Automated PHP Syntax Check
```bash
php check-php-syntax.php
```

### Manual Testing
Follow the comprehensive test plan in [TESTING_CHECKLIST.md](TESTING_CHECKLIST.md):
- Authentication flow
- Booking process
- Ticket generation
- Admin operations
- Security testing

### Test Accounts
Create test accounts via the registration page or use SQL:
```sql
-- Admin account
INSERT INTO users (email, password_hash, role, full_name, is_verified) 
VALUES ('admin@libtickets.com', '$2y$10$...', 'admin', 'Admin User', 1);

-- Customer account
INSERT INTO users (email, password_hash, role, full_name, is_verified) 
VALUES ('customer@libtickets.com', '$2y$10$...', 'customer', 'Test Customer', 1);
```

---

## 🔒 Security Features

- **Rate Limiting** - Protects against brute force attacks (10 attempts per 15 minutes)
- **Input Validation** - All inputs sanitized and validated
- **SQL Injection Protection** - PDO prepared statements
- **XSS Protection** - Output escaping
- **CSRF Protection** - Session-based validation
- **Password Hashing** - bcrypt with salt
- **File Upload Security** - Type validation, size limits, random filenames
- **Two-Factor Authentication** - Optional 2FA via email codes
- **Session Security** - HTTP-only cookies, session regeneration

---

## 📧 Email Configuration

### Development (Mailpit)
LibTickets uses Mailpit for email testing in development:
```env
DEV_MODE=true
# Mailpit automatically uses localhost:1025
```

### Production (SMTP)
Configure real SMTP server for production:
```env
DEV_MODE=false
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_SECURE=true
```

**Gmail Setup:**
1. Enable 2FA on your Google account
2. Generate an "App Password"
3. Use the app password in SMTP_PASS

---

## 🎨 Customization

### Theme Colors
Update via Admin Dashboard → Settings → Theme:
- Primary Color (default: #002059)
- Accent Color

### Site Information
Update via Admin Dashboard → Settings → General:
- Site Name
- Tagline
- Contact Information

### Logo
Upload via Admin Dashboard → Logo section

---

## 🐛 Troubleshooting

### API Returns 404
**Cause:** `.htaccess` not working  
**Solution:** Enable mod_rewrite in Apache
```bash
# Enable module
a2enmod rewrite

# Restart Apache
systemctl restart apache2
```

### Database Connection Fails
**Cause:** Wrong credentials or MySQL not running  
**Solution:** Check `.env` file and verify MySQL is running

### Emails Not Sending
**Cause:** SMTP misconfigured  
**Solution:** 
- Check SMTP credentials in `.env`
- Verify Mailpit is running (development)
- Test SMTP connection

### Session Not Persisting
**Cause:** Session directory not writable  
**Solution:** 
```bash
chmod 755 /tmp
# or configure custom session path
```

### Upload Directory Not Writable
**Cause:** Permission issues  
**Solution:**
```bash
chmod 755 public/uploads
```

---

## 📊 Database Schema

### Main Tables
- `users` - User accounts (admin, customer)
- `events` - Event listings
- `venues` - Event venues with capacity
- `categories` - Event categories
- `ticket_types` - Ticket types per event with pricing
- `bookings` - Customer bookings
- `booking_items` - Line items in booking
- `tickets` - Individual tickets with QR codes
- `seats` - Venue seating
- `event_seats` - Seat availability per event
- `notifications` - User notifications
- `activity_log` - Admin activity tracking
- `settings` - Site configuration

---

## 🚀 Deployment to Production

1. **Upload files** to web server
2. **Install dependencies**: `composer install --no-dev`
3. **Configure `.env`** with production settings
4. **Set DEV_MODE** to `false`
5. **Import database** schema
6. **Set permissions** on uploads and cache directories
7. **Configure Apache** DocumentRoot to `public/`
8. **Enable HTTPS** (recommended)
9. **Test critical paths** (see TESTING_CHECKLIST.md)
10. **Monitor logs** for errors

---

## 📝 License

This project is proprietary software developed for LibTickets.

---

## 👥 Support

For support, please contact:
- **Email:** support@libtickets.com
- **Phone:** +231 777 683 875
- **Location:** Monrovia, Liberia

---

## 🎉 Migration History

This project was successfully migrated from Node.js/Express to pure PHP on November 2, 2026.

**Migration Stats:**
- ✅ 72 endpoints migrated (100%)
- ✅ Node.js completely removed
- ✅ API contracts maintained (frontend unchanged)
- ✅ All features preserved

See [HANDOFF.md](HANDOFF.md) for complete migration details.

---

**Built with ❤️ in Liberia**
