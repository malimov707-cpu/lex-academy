# PHASE 9 — SECURITY REMEDIATION & HARDENING SPECIFICATION
**Security Controls, Production Checklist & Defense-in-Depth Measures**
*Date: November 20, 2026*

---

## 1. PRODUCTION DEPLOYMENT HARDENING CHECKLIST

Before launching the platform on institutional staging or production servers:

- [ ] **Environment Configuration:**
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - Generate fresh 256-bit key: `php artisan key:generate --force`
- [ ] **Transport Layer Security (TLS/HTTPS):**
  - Force HTTPS in `AppServiceProvider`: `URL::forceScheme('https')`
  - Secure session cookies: `SESSION_SECURE_COOKIE=true`
  - HTTP Strict Transport Security (HSTS) headers enabled on reverse proxy (Nginx).
- [ ] **Database Hardening:**
  - When migrating from local SQLite to PostgreSQL or MySQL, enforce dedicated non-root database user with least privilege permissions.
- [ ] **Credentials & Secret Management:**
  - Replace default demo passwords (`password`) for administrative accounts (`super@huquq.ai`, `admin@huquq.ai`).
  - Store external AI API keys exclusively in server environment variables or encrypted secret stores, never committed to repository files.

---

## 2. SECURITY REGRESSION TESTING IN AUTOMATION

The test suite continuously asserts security and boundary isolation:
- `tests/Feature/VirtualCourtroomSecurityTest.php` — asserts participants cannot act out of turn, tamper with other students' evidence annotations, or spoof role permissions.
- `tests/Feature/QuizSecurityTest.php` — asserts time-window closures, unassigned question rejection, and score immutability.
- `tests/Feature/AcademyAuthTest.php` — asserts role-gated routes throw HTTP 403 when accessed by unauthorized roles.
