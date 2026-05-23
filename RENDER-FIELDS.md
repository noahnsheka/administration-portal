# Render Deployment - Quick Field Reference

## Copy these values into your Render dashboard forms

---

## Web Service Configuration

### Basic Settings
```
Service Name:              administration-suite
Runtime:                   Docker
Region:                    Oregon (us-west) [or select your closest region]
Branch:                    main
Dockerfile Path:           ./Dockerfile
Docker Command:            [leave blank]
Plan:                      Standard or Pro
Auto-Deploy:               Yes
```

### Build Command
```
[Leave empty - Dockerfile handles everything]
```

### Start Command
```
[Leave empty - uses CMD from Dockerfile]
```

---

## Environment Variables for Web Service

Copy these exactly into the Environment section:

```
APP_ADMIN_BRAND=Administration Suite
APP_ALLOW_SETUP=1
APP_FOOTER_NAME=Administration Suite
APP_LOGIN_BADGE=School Administration
APP_LOGIN_COPY=Manage student records, internal communication, fees, academic reporting, and school operations from one secure system designed for institutional use.
APP_LOGIN_KICKER=Administration Suite
APP_LOGIN_TITLE=A professional control center for students, staff, and school leadership.
APP_NAME=Administration Suite
APP_ORGANIZATION=Your School
APP_SEED_DEMO_DATA=0
APP_SERVER_MODE=desktop
APP_STAFF_BRAND=Staff Portal
APP_STUDENT_BRAND=Student Portal
APP_SUPPORT_COPY=Need access or credential recovery? Contact the administration office for account setup or PIN assistance.
APP_TIMEZONE=UTC
DB_HOST=[Auto-filled from database connection]
DB_NAME=administration_suite
DB_PASS=[Auto-filled from database connection]
DB_PORT=3306
DB_USER=[Auto-filled from database connection]
```

---

## MySQL Database Configuration

### Database Creation Form

```
Database Name:             administration_suite
MySQL User:                admin_user
MySQL Password:            [Render auto-generates - save it]
Region:                    [Same as web service - Oregon or your region]
Plan:                      Starter (minimum)
Backup Enabled:            Yes
```

---

## After Database is Created - Get These Values

From the database details page, copy these to your web service environment:

```
DB_HOST:                   [Click to copy - looks like: mysql-xxx.onrender.com]
DB_PORT:                   3306
DB_USER:                   admin_user
DB_PASS:                   [Your auto-generated password]
DB_NAME:                   administration_suite
```

---

## Deployment Order

1. ✅ Create MySQL Database first (takes 2-3 minutes)
2. ✅ Get database connection details
3. ✅ Create Web Service
4. ✅ Add environment variables (including DB credentials)
5. ✅ Deploy
6. ✅ Visit `https://your-service-name.onrender.com/setup.php`

---

## Static Files Configuration

### Option 1: Served from Web Service (Recommended)
- Assets already served from `/var/www/html/assets/`
- No additional configuration needed
- Included in Docker container

### Option 2: Separate Static Site (Optional)
If you want CDN-served static files:

```
Service Type:              Static Site
Repository:                [Your GitHub repo]
Publish Directory:         assets
Build Command:             [leave empty]
```

Then update app to point to static site subdomain.

---

## After First Deployment

### Initial Setup
1. Navigate to: `https://your-service-name.onrender.com/setup.php`
2. Configure:
   - School name
   - Database settings (pre-filled)
   - Admin account details
   - Demo data (optional)
3. Complete setup

### Security - IMPORTANT
After setup completes:
1. Go to Web Service → Settings → Environment
2. Change: `APP_ALLOW_SETUP=0`
3. Redeploy (or Render auto-redeploys)

---

## Monitoring & Logs

In Render Dashboard:
- **Logs**: Web Service → Logs tab (see real-time output)
- **Metrics**: Web Service → Metrics tab (CPU, memory, requests)
- **Database**: MySQL → Logs for database errors

---

## Typical Service URLs After Deployment

```
Main Application:        https://administration-suite.onrender.com
Setup Page:              https://administration-suite.onrender.com/setup.php
Admin Portal:            https://administration-suite.onrender.com/admin/dashboard.php
Staff Portal:            https://administration-suite.onrender.com/staff/dashboard.php
Student Portal:          https://administration-suite.onrender.com/student/dashboard.php
```

(Replace `administration-suite` with your actual service name)

---

## Database Backup

In Render MySQL dashboard:
- Backups happen automatically (daily)
- Download backup: Database → Backups → Download
- Point-in-time recovery available (with plan upgrade)

---

## Cost Estimate

| Service | Plan | Cost/Month |
|---------|------|-----------|
| Web Service | Standard | $7 |
| MySQL Database | Starter | $15 |
| **Total** | - | **~$22** |

See https://render.com/pricing for latest pricing.

---

## Troubleshooting Commands

Not needed for basic setup, but useful info:

- **Check logs**: Render Dashboard → Logs
- **Database test**: Try connecting via MySQL client with provided credentials
- **Restart service**: Render Dashboard → Manual Deploy
- **Clear cache**: Render handles caching automatically

---

## Support Links

- Render Docs: https://render.com/docs
- Render Status: https://status.render.com
- Contact: https://render.com/support
