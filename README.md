# SGR SeatFlow

A working prototype of the dynamic seat-segment ticketing system described
in your project proposal: passengers pay only for the distance they
actually travel, and a seat freed by an early exit is automatically
re-listed for the remaining distance instead of sitting empty.

Built with **PHP + MySQL/MariaDB + plain HTML/CSS/vanilla JavaScript**
(no frameworks, no build tools -- every file can be opened and read
top to bottom).

---

## 1. Requirements

- PHP 7.4+ (PHP 8.x recommended) with the `pdo_mysql` extension
- MySQL or MariaDB
- Any of: XAMPP / WAMP / MAMP, or PHP's own built-in server

If you're using **XAMPP** (common on university lab machines), you
already have everything you need.

---

## 2. Setup

### Step 1 -- Import the database
1. Start MySQL/MariaDB (in XAMPP: click "Start" next to MySQL in the
   XAMPP Control Panel).
2. Import `schema.sql`:
   - **phpMyAdmin**: open phpMyAdmin -> "Import" tab -> choose `schema.sql` -> Go
   - **Command line**: `mysql -u root -p < schema.sql`

   This creates the `sgr_seatflow` database with all tables and seed data
   (7 stations along the Dar-es-Salaam - Dodoma line, 1 train with 20 seats,
   and 2 demo accounts).

### Step 2 -- Configure the database connection
Open `includes/config.php` and adjust these four lines if your MySQL
setup differs from XAMPP's defaults:
```php
$DB_HOST = "localhost";
$DB_NAME = "sgr_seatflow";
$DB_USER = "root";
$DB_PASS = "";
```

### Step 3 -- Run the app
- **XAMPP**: copy this whole folder into `htdocs/sgr_seatflow`, then visit
  `http://localhost/sgr_seatflow/index.php`
- **PHP's built-in server** (quick testing, no Apache needed): from
  inside this folder run:
  ```
  php -S localhost:8000
  ```
  then visit `http://localhost:8000/index.php`

---

## 3. Demo accounts

| Role      | Phone      | Password  |
|-----------|------------|-----------|
| Passenger | 0700000002 | pass123   |
| Admin     | 0700000001 | admin123  |

The demo passenger starts with 50,000 TZS already in their wallet so
you can book a ticket immediately without needing to top up first.

---

## 4. Notifications (email + SMS)

Every booking, early-exit refund, and wallet top-up triggers a
notification:

- **Email is real.** It sends through your own SMTP account using the
  bundled PHPMailer library (in `lib/PHPMailer/`). To enable it:
  1. Open `includes/mail_config.php`
  2. Fill in your SMTP host/username/password. For Gmail specifically:
     turn on 2-Step Verification, then generate an **App Password** at
     https://myaccount.google.com/apppasswords and use that (Gmail
     rejects your normal password for this).
  3. That's it -- booking confirmations, refund notices, and top-up
     receipts will now land in the passenger's real inbox.

  If you leave the placeholder credentials in place, emails will just
  fail silently (logged to PHP's error log) without breaking booking --
  the site still works fine without email configured.

- **SMS is simulated**, since sending real SMS needs a paid gateway
  account (e.g. Beem Africa, Africa's Talking) with an API key. Every
  "SMS" the system would send is instead logged to the `sms_log` table
  -- log in as admin and open **SMS Log** in the nav bar to see them.
  When you get real gateway credentials, replace the body of
  `send_sms()` in `includes/notifications.php` with an HTTP call to
  that gateway's API -- every place that already calls `send_sms()`
  stays exactly the same.

---

## 5. How to try the core feature (seat harvesting)

1. Log in as the **passenger** and book a seat for a shorter leg, e.g.
   Dar es Salaam -> Morogoro.
2. On your **My Tickets** dashboard, find that active booking. Under
   "Exiting early?", pick a station you're currently at (e.g. Ruvu)
   and click **Exit now**. This is self-service on purpose -- it
   mirrors a real automatic gate scanning your ticket QR code as you
   walk out, so there's no staff member needed to process every single
   passenger (which would just create queues at busy stations).
3. You'll see: the booking status changes to "exited", your wallet is
   automatically refunded for the unused distance, and a transaction
   log entry is created.
4. Start a new booking for Ruvu -> Kilosa (or any segment overlapping
   the freed portion) -- the same seat now shows up as available again,
   proving the leftover distance was successfully "harvested" and
   re-listed.

There's also an **Admin Dashboard -> Scan QR** action that does the
same thing on a passenger's behalf -- useful for staff assisting a
customer (e.g. a lost phone, a call-in) but not the everyday path.

---

## 6. Project structure

```
sgr_php/
├── schema.sql               <- import this into MySQL first
├── includes/
│   ├── config.php           <- database connection settings (edit this)
│   ├── mail_config.php      <- YOUR SMTP credentials go here (edit this)
│   ├── auth.php             <- login/session helper functions
│   ├── notifications.php    <- email (real) + SMS (simulated) senders
│   └── seat_logic.php       <- THE CORE ALGORITHM (read this file first)
├── lib/PHPMailer/           <- bundled email-sending library (no composer needed)
├── index.php                 landing page
├── register.php / login.php / logout.php
├── dashboard.php             passenger's ticket list + self-service "Exit now"
├── exit.php                  handles the passenger's own early-exit request
├── book.php                  interactive booking page (live seat map)
├── book_confirm.php          handles the booking form submission
├── ticket.php                 shows a ticket + client-generated QR code
├── wallet.php                 wallet top-up + transaction history
├── admin/
│   ├── dashboard.php          admin view of active bookings
│   ├── scan.php               handles the simulated QR exit-scan
│   └── sms_log.php            view every simulated SMS that was "sent"
├── api/
│   └── available_seats.php   JSON endpoint the seat map calls live
└── assets/
    ├── css/style.css
    └── js/
        ├── booking.js         makes the seat map interactive
        └── qrcode.min.js      generates QR codes entirely in-browser
```

**Start reading at `includes/seat_logic.php`** -- every other file is
just plumbing (routes, forms, HTML) around that one core idea.

---

## 7. Notes / things you may want to extend for your final submission

- Real SMS integration once you have a gateway API key (see section 4).
- Real QR scanning at physical gates (camera + `getUserMedia`) instead
  of the admin dashboard dropdown simulation.
- Seat maps for multiple trains/coaches instead of one fixed train.
- Password reset flow, and basic input validation hardening for a
  production deployment.
