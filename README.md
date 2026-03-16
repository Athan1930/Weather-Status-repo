# UCV WSN Weather Dashboard
**University of Cagayan Valley — CEIT WSN Research Group**
Node-01 · 915 MHz LoRa · Tuguegarao, Cagayan, Region 2 PH

---

## 📁 Project Structure
```
ucv-wsn/
├── Dockerfile          ← Tells Railway to use PHP+Apache
├── .htaccess           ← Security + HTTPS redirect
├── index.html          ← Weather dashboard (frontend)
├── api/
│   └── data.php        ← API endpoint (POST from Arduino, GET from dashboard)
└── README.md           ← This file
```

---

## 🚀 Railway Deployment — Step by Step

### STEP 1 — Push to GitHub
1. Create a new repository on github.com (e.g. `ucv-wsn-weather`)
2. Upload all files keeping the same folder structure above
3. Make sure `api/data.php` is inside an `api/` folder

### STEP 2 — Create Railway Project
1. Go to https://railway.app and log in with GitHub
2. Click **"New Project"**
3. Choose **"Deploy from GitHub repo"**
4. Select your `ucv-wsn-weather` repository
5. Railway will auto-detect the Dockerfile and start building

### STEP 3 — Add MySQL Database
1. Inside your Railway project, click **"+ New"**
2. Choose **"Database" → "MySQL"**
3. Wait for it to provision (takes ~30 seconds)

### STEP 4 — Set Environment Variables
1. Click on your **PHP service** (not the database)
2. Go to **"Variables"** tab
3. Click **"Add Variable Reference"** and add these from your MySQL service:
   - `MYSQLHOST`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`
   - `MYSQLDATABASE`
   - `MYSQLPORT`

   > Tip: Railway lets you copy these directly from the MySQL service's "Connect" tab

### STEP 5 — Get Your Public URL
1. Click on your PHP service
2. Go to **"Settings" → "Networking"**
3. Click **"Generate Domain"**
4. Copy your URL — it looks like: `https://ucv-wsn-weather-production.up.railway.app`

### STEP 6 — Update Your Files
Replace `your-app.up.railway.app` in TWO places:

**In `index.html`** (line ~302):
```javascript
const API_URL = 'https://YOUR-ACTUAL-URL.up.railway.app/api/data.php';
```

**In `node01.ino`** (line ~43):
```cpp
const char* SERVER_URL = "https://YOUR-ACTUAL-URL.up.railway.app/api/data.php";
```

Then push the updated `index.html` to GitHub — Railway will auto-redeploy.

---

## 🔐 API Key
The API key is set in `api/data.php`:
```php
define('API_KEY', 'ucv-wsn-secret-2025');
```
And must match `node01.ino`:
```cpp
const char* API_KEY = "ucv-wsn-secret-2025";
```
Change both to the same custom string before deploying!

---

## 🧪 Testing Your API

**Test POST (simulate Arduino):**
```bash
curl -X POST https://your-app.up.railway.app/api/data.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: ucv-wsn-secret-2025" \
  -d '{"node_id":"NODE-01","location":"UCV","temperature":28.5,"humidity":75,"wind_speed":12.3,"rain":0,"total_rain":5.2}'
```

**Test GET (check data):**
```bash
curl "https://your-app.up.railway.app/api/data.php?rows=5&range=live"
```

---

## ⚠️ Things to Remember
- Change the API key before going live
- Railway free tier has usage limits — 5s polling interval is fine
- `total_rain` resets when Arduino restarts — this is expected behavior
- The database table is auto-created on first POST — no manual SQL needed
