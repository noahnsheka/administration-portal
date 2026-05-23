# Render Deployment Guide

## Overview
This Administration Suite has been configured for deployment on Render.com using Docker.

## Pre-Deployment Checklist

✅ **Completed Setup:**
- [x] Dockerfile created (PHP 8.2 with Apache)
- [x] render.yaml configuration file
- [x] .dockerignore for clean builds
- [x] PHP PDO and MySQL extensions enabled
- [x] Apache mod_rewrite enabled
- [x] Static asset serving configured

## Deployment Steps

### 1. Create Render Account
- Sign up at https://render.com
- Connect your GitHub repository

### 2. Create Web Service on Render

**Option A: Using render.yaml (Recommended)**
1. Push the code to GitHub (including render.yaml)
2. In Render dashboard, click "New +" → "Web Service"
3. Connect your GitHub repo
4. Render will auto-detect render.yaml configuration
5. Review settings and deploy

**Option B: Manual Configuration**
1. Click "New +" → "Web Service"
2. Connect your GitHub repository
3. Fill in the following fields:

### Field Values for Manual Setup

| Field | Value |
|-------|-------|
| **Service Name** | `administration-suite` |
| **Runtime** | `Docker` |
| **Region** | `Oregon (us-west)` or closest to your location |
| **Branch** | `main` or `master` |
| **Dockerfile Path** | `./Dockerfile` |
| **Docker Command** | Leave empty (uses CMD from Dockerfile) |

### 3. Configure Environment Variables

Add these environment variables in the Render dashboard (Settings → Environment):

```
DB_HOST = [Auto-populated from database]
DB_PORT = 3306
DB_NAME = administration_suite
DB_USER = [Auto-populated from database]
DB_PASS = [Auto-populated from database]
APP_NAME = Administration Suite
APP_ORGANIZATION = Your School Name
APP_LOGIN_BADGE = School Administration
APP_LOGIN_KICKER = Administration Suite
APP_LOGIN_TITLE = A professional control center for students, staff, and school leadership.
APP_LOGIN_COPY = Manage student records, internal communication, fees, academic reporting, and school operations from one secure system designed for institutional use.
APP_SUPPORT_COPY = Need access or credential recovery? Contact the administration office.
APP_ADMIN_BRAND = Administration Suite
APP_STAFF_BRAND = Staff Portal
APP_STUDENT_BRAND = Student Portal
APP_FOOTER_NAME = Administration Suite
APP_TIMEZONE = UTC
APP_SEED_DEMO_DATA = 0
APP_ALLOW_SETUP = 1
APP_SERVER_MODE = desktop
```

### 4. Create MySQL Database

1. In Render dashboard: "New +" → "MySQL"
2. Fill in:
   - **Database Name**: `administration_suite`
   - **MySQL User**: `admin_user`
   - **Region**: Same as web service
   - **Plan**: `Starter` or higher

3. After creation, get the connection details:
   - **Host** (Internal): Copy to DB_HOST
   - **Port**: Usually 3306
   - **Database**: administration_suite
   - **User**: Copy to DB_USER
   - **Password**: Copy to DB_PASS

### 5. Connect Database to Web Service

1. In Web Service settings → Environment
2. Add the database credentials from step 4

### 6. Configure Static Files (Optional)

If using Render's Static Site for assets:

**For Assets Subdomain (Optional):**
1. Create a new Static Site service
2. Point to GitHub repo
3. Set Publish Directory: `assets`
4. Update asset URLs in code if needed

OR keep assets served from the same web service (recommended for simplicity).

### 7. Deploy & Initialize

1. Deploy the web service (Render auto-deploys from GitHub)
2. Once deployed, visit: `https://your-service-name.onrender.com/setup.php`
3. Complete the setup form:
   - Set your school name
   - Confirm database settings (auto-filled)
   - Create initial administrator account
   - Choose to seed demo data (optional)
4. Setup will create `.env.runtime` and lock itself

### 8. Post-Setup Configuration

After setup completes:

1. **Disable Setup Page** (important for security):
   - In Render Environment: Set `APP_ALLOW_SETUP = 0`
   - Deploy again

2. **Create Administrator Accounts**: 
   - Log in with the account created during setup
   - Use Admin panel to create staff and student accounts

3. **Customize Branding**:
   - You can re-enable setup temporarily to change branding
   - Or update environment variables directly

## File Structure on Render

```
/var/www/html/
├── index.php           (Login page)
├── setup.php           (Initial setup)
├── admin/              (Admin portal)
├── staff/              (Staff portal)
├── student/            (Student portal)
├── auth/               (Authentication)
├── config/             (Configuration)
├── assets/             (CSS, JS, vendors)
├── includes/           (Includes)
└── database/           (SQL schemas)
```

## Environment Variables Explained

| Variable | Purpose | Example |
|----------|---------|---------|
| `DB_HOST` | MySQL server hostname | `mysql.xxx.onrender.com` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_NAME` | Database name | `administration_suite` |
| `DB_USER` | MySQL user | `admin_user` |
| `DB_PASS` | MySQL password | `[auto-generated]` |
| `APP_NAME` | Application name | `Administration Suite` |
| `APP_ORGANIZATION` | School/organization name | `Your School` |
| `APP_ALLOW_SETUP` | Enable setup page | `0` (disable after setup) |
| `APP_SEED_DEMO_DATA` | Seed demo data | `0` (no demo) or `1` |
| `APP_TIMEZONE` | Timezone | `America/New_York` |

## Static Files & Assets

Static assets (CSS, JS, images) are served from the same web service:
- CSS: `/assets/css/`
- JavaScript: `/assets/js/`
- Vendor libraries: `/assets/vendor/`

All served through Apache in the same container.

## Troubleshooting

### Database Connection Failed
- Verify `DB_HOST`, `DB_USER`, `DB_PASS` in Environment
- Check database status in Render dashboard
- Ensure web service has access to database (same region)

### Setup Page Not Loading
- Ensure `APP_ALLOW_SETUP=1`
- Check `/setup.php` is accessible
- Clear browser cache

### File Permissions Error
- Dockerfile sets correct permissions (www-data:www-data)
- If issues persist, check logs in Render dashboard

### Session Not Persisting
- Sessions use database by default (should work)
- If not working, ensure database is connected

## Accessing Your App

After deployment:
- **Main URL**: `https://your-service-name.onrender.com`
- **Setup URL**: `https://your-service-name.onrender.com/setup.php`
- **Login**: Use credentials created during setup

## Render Cost Estimates

- **Web Service (Starter)**: ~$7/month
- **MySQL Database (Starter)**: ~$15/month
- **Total**: ~$22/month for starter deployment

See https://render.com/pricing for current pricing.

## Support

For Render-specific issues: https://render.com/docs
For app-specific issues: Check app logs in Render dashboard

## Customization After Deployment

To update environment variables:
1. Go to Web Service → Settings → Environment
2. Update variables
3. Render auto-redeploys

To update application code:
1. Push changes to GitHub
2. Render auto-deploys (if autoDeploy enabled)

## Security Considerations

1. **Setup Page**: Disable (`APP_ALLOW_SETUP=0`) after initial setup
2. **Database**: Use strong passwords (Render generates automatically)
3. **HTTPS**: Render provides free SSL automatically
4. **Backups**: Enable automatic backups for MySQL database in Render
5. **Secrets**: Never commit `.env` files to GitHub
