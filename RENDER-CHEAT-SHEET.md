# Render Deployment Cheat Sheet

## 3-Step Quick Deploy

### 1️⃣ Push Code to GitHub
```bash
git add .
git commit -m "Add Render deployment configuration"
git push origin main
```

### 2️⃣ Create Services on Render

**Create MySQL First:**
- Render Dashboard → New + → MySQL
- Database: `administration_suite`
- User: `admin_user`
- Region: Oregon (or your choice)
- Plan: Starter
- ✅ Create

**Get Connection Details:**
- Copy Host (DB_HOST)
- Copy Password (DB_PASS)
- Note Port: 3306
- Note User: admin_user

**Create Web Service:**
- Render Dashboard → New + → Web Service
- Select GitHub repo
- Service Name: `administration-suite`
- Region: **SAME as database**
- Runtime: Docker
- Dockerfile Path: `./Dockerfile`
- Plan: Standard
- ✅ Create Web Service

**Add Environment Variables:**
Copy-paste from below:
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
DB_HOST=[FROM DATABASE CONNECTION]
DB_NAME=administration_suite
DB_PASS=[FROM DATABASE CONNECTION]
DB_PORT=3306
DB_USER=admin_user
PORT=80
```

### 3️⃣ Initialize & Test

**Wait for Deployment:**
- Web Service → Logs (watch for "Live" status)
- Takes 2-5 minutes first time

**Run Setup:**
- Go to: `https://administration-suite.onrender.com/setup.php`
- Fill in:
  - School/Organization name
  - Admin PIN (e.g., 1234)
  - Admin name
  - Database settings (auto-filled)
  - Select "No" for demo data
- ✅ Submit

**After Setup:**
1. Update Environment: `APP_ALLOW_SETUP=0`
2. Web Service redeploys automatically
3. Log in with admin credentials
4. Test all portals

---

## Field Reference

### Key Render Settings
```
Web Service Name:   administration-suite
Database Name:      administration_suite
Database User:      admin_user
Regions:            Oregon (us-west)
Runtime:            Docker
Dockerfile:         ./Dockerfile
```

### Static Files
✅ Already included in Docker container
- Location: `/assets/`
- Served by Apache
- No separate static site needed

### Custom Domain (Optional)
```
1. Register domain
2. Render → Web Service → Custom Domains
3. Add domain
4. Follow DNS instructions
5. SSL auto-enabled
```

---

## URLs After Deployment

```
Login Page:      https://administration-suite.onrender.com
Setup Page:      https://administration-suite.onrender.com/setup.php
Admin Portal:    https://administration-suite.onrender.com/admin/dashboard.php
Staff Portal:    https://administration-suite.onrender.com/staff/dashboard.php
Student Portal:  https://administration-suite.onrender.com/student/dashboard.php
```

---

## Troubleshooting Quick Fixes

| Problem | Solution |
|---------|----------|
| "Database connection failed" | Verify DB_HOST, DB_USER, DB_PASS in Environment |
| "Port already in use" | Render handles this - restart service |
| "Setup page won't load" | Set APP_ALLOW_SETUP=1 and redeploy |
| "Assets not loading" | Clear browser cache (Ctrl+Shift+Delete) |
| "Can't log in" | Use credentials from setup.php success page |

---

## Cost Reference

```
Web Service:    $7/month (Standard)
Database:       $15/month (Starter)
Domain:         $12+/year (external registrar)
SSL:            FREE (included)
──────────────────────────
Total:          ~$22/month
```

---

## Environment Variables Explained

| Variable | What It Does |
|----------|-------------|
| `DB_HOST` | Where database is located |
| `DB_USER` / `DB_PASS` | Database login |
| `APP_NAME` | App title in UI |
| `APP_ORGANIZATION` | School/organization name |
| `APP_ALLOW_SETUP` | Allow setup.php (set to 0 after setup!) |
| `APP_SEED_DEMO_DATA` | Add example data (0=no, 1=yes) |
| `APP_TIMEZONE` | Server timezone |
| `PORT` | Listen port (Render sets this) |

---

## Monitoring Commands

### View Live Logs
Render Dashboard → Web Service → Logs (refresh auto)

### Check Service Status
Render Dashboard → Web Service (green = live, red = error)

### Database Status
Render Dashboard → MySQL → Overview

---

## Post-Deployment Checklist

- [ ] Service shows "Live" (green)
- [ ] Setup.php loads and works
- [ ] Admin login works
- [ ] All portals accessible
- [ ] APP_ALLOW_SETUP changed to 0
- [ ] Service redeployed after setup
- [ ] Test accounts created
- [ ] Client credentials shared

---

## Emergency: Reset Everything

If something goes wrong and you need to start over:

1. Delete web service from Render
2. Delete database from Render
3. Change APP_ALLOW_SETUP=1 locally (if needed)
4. Re-create database
5. Re-create web service
6. Add environment variables
7. Run setup.php again

---

## File Checklist in GitHub

Make sure these are in your repo:

- [x] Dockerfile (root)
- [x] render.yaml (root)
- [x] .dockerignore (root)
- [x] setup.php (root)
- [x] index.php (root)
- [x] All PHP files
- [x] database/schema.sql
- [x] assets/ folder
- [ ] ❌ .env (should NOT be in repo)
- [ ] ❌ .env.runtime (should NOT be in repo)

---

## Notes

- Services auto-update when you push to GitHub
- Database credentials auto-filled from database service
- Render provides free SSL/HTTPS automatically
- Can upgrade plan anytime from dashboard
- Database backups automatic (daily)
- Static files served from same container (~better)

---

**Questions? See:**
- Complete guide: `RENDER-DEPLOYMENT.md`
- Step-by-step checklist: `RENDER-CHECKLIST.md`
- Detailed field reference: `RENDER-FIELDS.md`

🚀 **You're ready to deploy!**
