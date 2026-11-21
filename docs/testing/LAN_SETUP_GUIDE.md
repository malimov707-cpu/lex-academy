# PHASE 5 — LOCAL AREA NETWORK (LAN) MULTI-DEVICE SETUP GUIDE
**Running Multi-User Virtual Courtroom Simulations over Local Wi-Fi or Mobile Hotspot**
*Date: November 20, 2026 | Environment: Host (macOS/Windows) + Multi-Client (Laptops/Smartphones)*

---

## 1. PURPOSE & ARCHITECTURAL OVERVIEW

To test and demonstrate the Virtual Courtroom with multiple participants without needing public cloud hosting or expensive infrastructure, Lex Academy is architected to run across a **Local Area Network (LAN)**.

A single laptop acts as the **Local Simulation Server** running PHP/Laravel and serving all connected student and instructor client devices over standard Wi-Fi or a mobile phone hotspot.

```
                                  +------------------------------+
                                  |     LOCAL WI-FI ROUTER       |
                                  |   or MOBILE HOTSPOT (4G/5G)  |
                                  +---------------+--------------+
                                                  |
         +----------------------------------------+---------------------------------------+
         |                                        |                                       |
         v                                        v                                       v
+-------------------------------+  +-------------------------------+  +-------------------------------+
|    SERVER / TEACHER LAPTOP    |  |     STUDENT 1 (PROSECUTOR)    |  |      STUDENT 2 (DEFENSE)      |
|  IP: 192.168.1.100:8000       |  |  Client Laptop (Chrome)       |  |  Client Laptop / Tablet       |
|  - Laravel Herd / Artisan Serv|  |  Navigates to:                |  |  Navigates to:                |
|  - SQLite Database            |  |  http://192.168.1.100:8000    |  |  http://192.168.1.100:8000    |
+-------------------------------+  +-------------------------------+  +-------------------------------+
```

---

## 2. STEP-BY-STEP HOST CONFIGURATION

### Step 1: Identify Host Machine Local IP Address
Connect your host computer (e.g., MacBook or Windows PC) to the shared Wi-Fi network or mobile phone hotspot.

- **On macOS:**
  Open Terminal and run:
  ```bash
  ipconfig getifaddr en0   # For Wi-Fi
  # Output example: 192.168.1.105 (or 172.20.10.2 on mobile hotspot)
  ```
- **On Windows:**
  Open PowerShell / Command Prompt and run:
  ```powershell
  ipconfig
  # Look for "IPv4 Address" under Wireless LAN adapter Wi-Fi: 192.168.1.105
  ```

### Step 2: Configure Environment for LAN Hostname
In `.env` on the host machine:
```ini
APP_URL=http://192.168.1.105:8000
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=192.168.1.105:8000
```
*(Leave `SESSION_DOMAIN` empty to ensure session cookies are bound automatically to the client-facing IP).*

### Step 3: Launch Laravel HTTP Server on All Interfaces
Do NOT run `php artisan serve` with default parameters (which binds strictly to `127.0.0.1`, blocking other network devices). Bind explicitly to `0.0.0.0`:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```
*Laravel is now listening on all network interfaces.*

### Step 4: Ensure Assets are Pre-Compiled
Clients will fetch CSS, JS, and Babylon.js models directly from the server. Ensure the production build is compiled so clients do not require Vite dev server bridges:
```bash
npm run build
```

### Step 5: Adjust Local Firewall (If Prompted)
- **macOS:** If a dialog asks *"Do you want the application 'php' to accept incoming network connections?"*, click **Allow**.
- **Windows:** In Windows Defender Firewall, allow incoming TCP connections on port `8000` for Private Networks.

---

## 3. CLIENT DEVICE CONNECTION & LOGIN WORKFLOW

### Step 1: Connect Client Devices to the Same Wi-Fi / Hotspot
Ensure each student laptop, iPad, or smartphone is connected to the exact same Wi-Fi SSID or mobile hotspot.

### Step 2: Open Browser on Client Device
On each student device, open Google Chrome, Safari, or Microsoft Edge and navigate to:
```
http://192.168.1.105:8000
```
*(Replace `192.168.1.105` with your host machine’s actual IP from Step 1).*

### Step 3: Login Using Assigned Role Accounts
The platform includes pre-configured seed accounts in the local database:

| Role in Simulation | Login Email | Default Password | Initial Destination |
|---|---|---|---|
| **Facilitator / Teacher** | `teacher@huquq.ai` | `password` | `/admin/courtroom` |
| **Student 1 (Judge)** | `student@huquq.ai` | `password` | `/virtual-courtroom/groups` |
| **Student 2 (Prosecutor)** | `salom@gmail.com` | `password` | `/virtual-courtroom/groups` |
| **Student 3 (Defense)** | Register new via `/register` | (Set during registration) | `/virtual-courtroom/groups` |
| **Observers (4–10)** | Register new via `/register` | (Set during registration) | `/virtual-courtroom/groups` |

---

## 4. TROUBLESHOOTING & EDGE CASES

| Symptom | Cause | Solution |
|---|---|---|
| Client browser shows *"Connection timed out"* | Host bound to `127.0.0.1` instead of `0.0.0.0` | Stop server; restart with `php artisan serve --host=0.0.0.0 --port=8000`. |
| Host firewall blocking port 8000 | OS firewall filter | Temporarily allow port 8000 TCP in firewall settings. |
| Client logged out unexpectedly when opening pages | Cookie domain mismatch | Check `.env`: ensure `SESSION_DOMAIN` is empty (`null`). |
| 3D Canvas displays black screen on older phone | WebGL2 disabled or unsupported GPU | The system automatically provides the responsive 2D DOM interaction shell; simulation proceeds without graphical hitch. |
