# Pre-Render Deployment Checklist

## Application Readiness

### Code & Files
- [x] PHP code is production-ready
- [x] No hardcoded credentials in code
- [x] Dockerfile created (PHP 8.2 + Apache + MySQL PDO)
- [x] .dockerignore configured to exclude development files
- [x] render.yaml configuration file created
- [x] Asset paths work correctly (`/assets/css/`, `/assets/js/`)

### Configuration
- [x] Environment variables properly externalized
- [x] Database connection uses environment variables
- [x] `.env` and `.env.local` will not be committed to GitHub
- [x] `.env.runtime` created by setup.php (not in repo)
- [x] `setup.php` exists and can initialize database
- [x] Auto-redirect from `/` to `/setup.php` before initialization

### Dependencies & Extensions
- [x] PHP PDO extension available in Dockerfile
- [x] PHP pdo_mysql extension available
- [x] Apache mod_rewrite enabled for URL routing
- [x] Apache mod_headers enabled
- [x] File permissions set correctly (www-data:www-data)

### Security
- [x] No sensitive data in committed files
- [x] Session handling configured
- [x] Authentication system functional
- [x] User roles properly defined (admin, staff, student)
- [x] Database includes hashed passwords (bcrypt or similar)

### Database Readiness
- [x] SQL schema exists (`database/schema.sql`)
- [x] Schema supports dynamic initialization
- [x] PDO prepared statements used (SQL injection protected)
- [x] No hardcoded database names
- [x] No hardcoded database credentials

### Static Assets
- [x] Bootstrap CSS included (`/assets/vendor/bootstrap/`)
- [x] Bootstrap JS included
- [x] QR code library included (`/assets/vendor/qrcode/`)
- [x] Custom CSS exists (`/assets/css/style.css`)
- [x] Custom JS exists (`/assets/js/app.js`)
- [x] All asset paths use relative URLs or helper functions

### Testing Before Push
- [ ] Tested with environment variables (simulate Render env)
- [ ] Setup page works cleanly
- [ ] Can create admin account during setup
- [ ] Login works after setup
- [ ] Student dashboard accessible
- [ ] Staff dashboard accessible
- [ ] Admin dashboard accessible

---

## GitHub Repository Setup

### Repository Configuration
- [x] Code pushed to GitHub
- [x] Main/master branch is clean
- [x] `.gitignore` includes:
  - `.env`
  - `.env.local`
  - `.env.runtime`
  - `node_modules/`
  - `.vscode/`
  - `dist/`
- [x] No sensitive files committed
- [x] README.md present

### Files to Verify in GitHub
- [x] `Dockerfile` at root
- [x] `.dockerignore` at root
- [x] `render.yaml` at root
- [x] `RENDER-DEPLOYMENT.md` at root
- [x] `RENDER-FIELDS.md` at root
- [x] All application files
- [x] `setup.php` at root
- [x] `database/schema.sql`

---

## Render Account & Services

### Before Creating Services
- [ ] Render.com account created
- [ ] GitHub account connected to Render
- [ ] Credit card added (for paid plans)

### Service Planning
- [ ] Decided on region (Oregon, Virginia, Singapore, etc.)
- [ ] Planned web service name: `administration-suite`
- [ ] Planned database name: `administration_suite`
- [ ] Planned database user: `admin_user`

---

## Data to Have Ready When Deploying

### Application Configuration
- [ ] School/Organization Name
- [ ] Application name
- [ ] Login badge text
- [ ] Support copy/email
- [ ] Admin account PIN (prefer random 4-6 digits)
- [ ] Admin full name
- [ ] Admin account number (e.g., ADM-3001)

### Timezone (Optional)
- [ ] Timezone string (e.g., `America/New_York`, `UTC`, `Asia/Tokyo`)
- [ ] Or leave as `UTC` default

### Demo Data Decision
- [ ] Decide: seed demo data? (0 = no, 1 = yes)
- [ ] Recommended: 0 (no) for production

---

## Render Deployment Checklist

### Step 1: Create MySQL Database
- [ ] Log into Render.com
- [ ] Click "New +" → "MySQL"
- [ ] Database Name: `administration_suite`
- [ ] MySQL User: `admin_user`
- [ ] Region: select your region
- [ ] Plan: Starter (minimum)
- [ ] Click Create Database
- [ ] Wait for database to provision (2-3 minutes)
- [ ] Copy connection details:
  - [ ] DB_HOST: `________________________`
  - [ ] DB_PORT: `3306`
  - [ ] DB_USER: `admin_user`
  - [ ] DB_PASS: `________________________`

### Step 2: Create Web Service
- [ ] Click "New +" → "Web Service"
- [ ] Select your GitHub repository
- [ ] Service Name: `administration-suite`
- [ ] Region: **SAME as database region**
- [ ] Branch: `main` (or your branch)
- [ ] Root Directory: `/` (default)
- [ ] Runtime: `Docker`
- [ ] Dockerfile Path: `./Dockerfile`
- [ ] Docker Command: (leave empty)
- [ ] Plan: Standard or Pro
- [ ] Auto-deploy: **Yes**
- [ ] Click Create Web Service

### Step 3: Add Environment Variables
- [ ] Service → Settings → Environment
- [ ] Add all variables from RENDER-FIELDS.md
- [ ] For DB_HOST, DB_USER, DB_PASS: use values from Step 1
- [ ] Click Save Changes
- [ ] Service auto-redeploys

### Step 4: Wait for Deployment
- [ ] Logs show no errors
- [ ] Service shows "Live" status (green)
- [ ] Typically takes 2-5 minutes first time

### Step 5: Initial Setup
- [ ] Visit: `https://administration-suite.onrender.com`
- [ ] Redirects to `/setup.php` automatically
- [ ] Fill in configuration form:
  - [ ] School name
  - [ ] Organization name
  - [ ] Admin account details
  - [ ] Confirm database connection info
  - [ ] Choose demo data (recommended: No)
- [ ] Click Setup
- [ ] Success page should appear
- [ ] Note the created admin credentials

### Step 6: Verify Deployment
- [ ] Visit main URL: `https://administration-suite.onrender.com`
- [ ] Should redirect to login page
- [ ] Can log in with admin credentials created during setup
- [ ] Admin dashboard loads
- [ ] Can navigate to staff/student sections

### Step 7: Secure the Setup Page
- [ ] Go back to Web Service Settings → Environment
- [ ] Change: `APP_ALLOW_SETUP` = `0`
- [ ] Click Save
- [ ] Service auto-redeploys
- [ ] Verify setup.php no longer accessible (404 or redirect)

---

## Post-Deployment Verification

### Functionality Tests
- [ ] Login works with admin credentials
- [ ] Can create staff accounts (admin panel)
- [ ] Can create student accounts (admin panel)
- [ ] Staff can log in and see staff portal
- [ ] Students can log in and see student portal
- [ ] Admin can see all dashboards
- [ ] Navigation works between sections
- [ ] CSS styling loads correctly
- [ ] No JavaScript errors in browser console

### Database Tests
- [ ] Data persists after page refresh
- [ ] Can create new records
- [ ] Can update existing records
- [ ] Sessions work (stay logged in)

### Performance & Logs
- [ ] No error logs in Render Logs
- [ ] Page load times are reasonable
- [ ] No 500 server errors

---

## Going Live - Additional Considerations

### Custom Domain (Optional)
- [ ] Register domain (GoDaddy, Namecheap, etc.)
- [ ] In Render: Web Service → Settings → Custom Domains
- [ ] Add your domain
- [ ] Update DNS records (Render provides instructions)
- [ ] Render provides free SSL certificate

### Monitoring & Alerts
- [ ] Set up uptime monitoring
- [ ] Enable error notifications
- [ ] Monitor database usage
- [ ] Plan upgrade timeline if needed

### Backup Strategy
- [ ] Enable MySQL automatic backups (Render default)
- [ ] Test backup restoration process
- [ ] Document backup procedures

### Documentation for Clients
- [ ] Share login credentials securely
- [ ] Provide URL to live application
- [ ] Document admin procedures
- [ ] Provide support contact info

---

## Troubleshooting Reference

If deployment fails:

1. **Check Render Logs**: Web Service → Logs
   - Look for database connection errors
   - Check for port binding issues
   - Verify environment variables

2. **Database Connection Issues**:
   - Verify DB_HOST, DB_USER, DB_PASS in Environment
   - Ensure database is in "Live" status
   - Both services in same region

3. **Setup Page Issues**:
   - Ensure APP_ALLOW_SETUP=1
   - Check file permissions in logs
   - Verify database connection works first

4. **Asset Loading Issues**:
   - Clear browser cache (Ctrl+Shift+Del)
   - Check console for 404 errors
   - Verify asset paths in HTML

---

## Final Checklist Before Client Demo

- [ ] Application fully deployed and live
- [ ] All portals (admin, staff, student) working
- [ ] Test user accounts created
- [ ] Setup page disabled (APP_ALLOW_SETUP=0)
- [ ] Domain configured (if using custom domain)
- [ ] Backup enabled on database
- [ ] Monitoring configured
- [ ] Client credentials shared securely
- [ ] Client given:
  - [ ] Application URL
  - [ ] Login instructions
  - [ ] Contact for support
  - [ ] Basic user manual
