# Render Deployment with PostgreSQL - Updated Guide

## ✅ What's Changed

The app has been updated to use **PostgreSQL** instead of MySQL:

- ✅ Dockerfile now includes PostgreSQL PDO support
- ✅ Database connection configured for PostgreSQL (`pgsql://`)
- ✅ Port changed from 3306 → 5432
- ✅ Database user: `postgres` (Render default)
- ✅ All PHP code compatible with PostgreSQL

---

## 🚀 Step-by-Step PostgreSQL Deployment

### STEP 1: Push Updated Code

Push the updated Dockerfile and configs to GitHub:

```bash
cd "c:\Users\noahs\OneDrive\Desktop\Noahs projects\administration"
git add Dockerfile RENDER-FIELDS.md .env.example
git commit -m "Update for PostgreSQL support - Render deployment"
git push origin main
```

---

### STEP 2: Create PostgreSQL Database on Render

1. **Go to Render Dashboard**: https://render.com/dashboard
2. Click **"New +"** → **"PostgreSQL"**
3. Fill in the form:

```
Name:                    administration-db
Database Name:           administration_suite
User:                    postgres
Password:                [Render auto-generates - SAVE IT!]
Region:                  Oregon or your region (must match web service!)
Plan:                    Starter ($15/month)
Backup Enabled:          Yes
```

4. Click **Create Database**
5. ⏳ Wait 2-3 minutes for status to show "Available"

---

### STEP 3: Get Database Connection Details

When database is ready (shows "Available"):

1. Click into the database
2. Look for "Connection" section
3. Copy these values:

```
Host:     dpg-xxx.render.com  ← DB_HOST
Port:     5432                ← DB_PORT (PostgreSQL standard)
Database: administration_suite ← DB_NAME
User:     postgres            ← DB_USER
Password: [your password]     ← DB_PASS
```

**Save the password!** You'll need it next.

---

### STEP 4: Create Web Service

1. Click **"New +"** → **"Web Service"**
2. Connect your GitHub repo
3. Fill in:

```
Service Name:    administration-suite
Region:          [SAME AS DATABASE - e.g., Oregon]
Branch:          main
Dockerfile Path: ./Dockerfile
Plan:            Standard ($7/month)
Auto-Deploy:     Yes
```

4. Click **Create Web Service**

---

### STEP 5: Add Environment Variables

1. Go to Web Service → **Settings** → **Environment**
2. Add these variables:

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
DB_HOST=dpg-xxx.render.com
DB_NAME=administration_suite
DB_PASS=[PASTE_PASSWORD_FROM_DATABASE]
DB_PORT=5432
DB_USER=postgres
```

**Replace:**
- `DB_HOST`: Your database host (from Step 3)
- `DB_PASS`: Your database password (from Step 3)

3. Click **Save**
4. Service auto-deploys

---

### STEP 6: Wait for Deployment

1. Go to **Logs** tab
2. Watch for "Live" status (green dot)
3. Should take 3-5 minutes first time

---

### STEP 7: Run Setup

1. Visit: `https://administration-suite.onrender.com/setup.php`
2. Fill in form:
   - School name
   - Admin name
   - Admin PIN (e.g., 1234)
   - Leave database fields as-is (pre-filled!)
   - Leave "Seed Demo Data" unchecked
3. Click **Submit**
4. ⏳ Wait for "Setup Complete" message

---

### STEP 8: Disable Setup Page

For security:

1. Go to Web Service → **Settings** → **Environment**
2. Find `APP_ALLOW_SETUP`
3. Change from `1` to `0`
4. Click **Save**
5. Service auto-redeploys

---

### STEP 9: Test Your App

1. Visit: `https://administration-suite.onrender.com`
2. Log in with admin credentials from setup
3. Test all portals:
   - Admin Dashboard
   - Staff Portal  
   - Student Portal

---

## ✅ PostgreSQL Specific Details

### Port
- **MySQL:** 3306
- **PostgreSQL:** 5432 ← Changed

### Default User
- **MySQL:** root
- **PostgreSQL:** postgres ← Changed

### Connection String
- **MySQL:** `mysql://host:3306`
- **PostgreSQL:** `pgsql://host:5432` ← Changed

### Database Initialization
PostgreSQL automatically creates the database schema when the app first runs. No manual SQL imports needed!

---

## 🎯 Quick Summary

| Item | MySQL | PostgreSQL |
|------|-------|-----------|
| Port | 3306 | 5432 |
| User | root | postgres |
| DSN | mysql:// | pgsql:// |
| Host Format | host:port | host:port |
| Cost | ~$15/mo | ~$15/mo |

---

## 📊 Cost (Unchanged)

```
Web Service:        $7/month
PostgreSQL DB:      $15/month
Total:              $22/month
```

---

## 🆘 Troubleshooting PostgreSQL

### "Connection refused"
- Verify `DB_HOST`, `DB_PORT` (5432!), `DB_USER`, `DB_PASS` in Environment
- Check database is "Available" (not still provisioning)
- Both services in same region?

### "Database does not exist"
- Check `DB_NAME` = `administration_suite`
- Let setup.php run - it creates tables automatically

### "Setup page won't load"
- Set `APP_ALLOW_SETUP=1` (if you changed it to 0 too early)
- Check logs for database connection errors

### Port/Host Issues
- PostgreSQL uses port **5432** (not 3306!)
- Host looks like: `dpg-xxx.render.com` (not `mysql-xxx`)

---

## 🎉 Success!

Your app is now running on PostgreSQL via Render!

**Next Steps:**
- Create staff/student accounts
- Customize app branding
- Share URL with clients

---

## 📞 Need Help?

- **PostgreSQL Docs**: https://www.postgresql.org/docs/
- **Render Docs**: https://render.com/docs
- **Check Logs**: Web Service → Logs tab

---

**You're all set!** 🚀
