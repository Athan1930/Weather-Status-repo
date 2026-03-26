const express    = require('express');
const { Pool }   = require('pg');
const cors       = require('cors');
const path       = require('path');
const nodemailer = require('nodemailer');

const app  = express();
const PORT = process.env.PORT || 3000;

// ── Middleware ──────────────────────────────
app.use(cors());
app.use(express.json());
app.use(express.static(__dirname));

// ── PostgreSQL connection ───────────────────
const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: { rejectUnauthorized: false }
});

// ── Auto-create table ───────────────────────
async function initDB() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS sensor_data (
      id          SERIAL PRIMARY KEY,
      node_id     VARCHAR(20)  DEFAULT 'NODE-01',
      location    VARCHAR(100) DEFAULT NULL,
      temperature FLOAT        DEFAULT 0,
      humidity    FLOAT        DEFAULT 0,
      wind_speed  FLOAT        DEFAULT 0,
      rain        FLOAT        DEFAULT 0,
      total_rain  FLOAT        DEFAULT 0,
      recorded_at TIMESTAMP    DEFAULT NOW()
    )
  `);
  console.log('[DB] ✓ Table ready');
}

// ── API KEY ─────────────────────────────────
const API_KEY = process.env.API_KEY || 'ucv-wsn-secret-2025';

// ── Nodemailer (Gmail) ───────────────────────
const mailer = nodemailer.createTransport({
  host: 'smtp.gmail.com',
  port: 465,
  secure: true,
  auth: {
    user: process.env.GMAIL_USER,
    pass: process.env.GMAIL_APP_PASSWORD,
  },
});

// ── POST /api/data — Arduino sends data ─────
app.post('/api/data', (req, res) => {
  const key = req.headers['x-api-key'];
  if (key !== API_KEY) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const {
    node_id     = 'NODE-01',
    location    = '',
    temperature = 0,
    humidity    = 0,
    wind_speed  = 0,
    rain        = 0,
    total_rain  = 0
  } = req.body;

  const temp  = (temperature < -40 || temperature > 85)  ? 0 : parseFloat(temperature);
  const hum   = (humidity    < 0   || humidity    > 100) ? 0 : parseFloat(humidity);
  const wind  = (wind_speed  < 0   || wind_speed  > 400) ? 0 : parseFloat(wind_speed);
  const rainV = (rain        < 0   || rain        > 500) ? 0 : parseFloat(rain);
  const total = total_rain < 0 ? 0 : parseFloat(total_rain);

  pool.query(
    `INSERT INTO sensor_data (node_id, location, temperature, humidity, wind_speed, rain, total_rain)
     VALUES ($1,$2,$3,$4,$5,$6,$7) RETURNING id`,
    [node_id, location, temp, hum, wind, rainV, total]
  ).then(result => {
    console.log(`[POST] ✓ Data saved — ID ${result.rows[0].id} | Temp:${temp} Hum:${hum} Wind:${wind} Rain:${rainV}`);
    res.json({ status: 'ok', id: result.rows[0].id });
  }).catch(err => {
    console.error('[POST] !! DB error:', err.message);
    res.status(500).json({ error: 'DB error' });
  });
});

// ── POST /api/alert — Arduino sends alert ───
app.post('/api/alert', async (req, res) => {
  const key = req.headers['x-api-key'];
  if (key !== API_KEY) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const {
    node_id    = 'NODE-01',
    location   = '',
    signal     = 0,
    temperature = 0,
    humidity    = 0,
    wind_speed  = 0,
    rain        = 0,
    total_rain  = 0
  } = req.body;

  const subject = `⚠️ UCV WSN ${node_id} — Signal ${signal} Alert`;

  const html = `
    <h2 style="color:#cc3300">⚠️ UCV WSN Weather Alert</h2>
    <table style="border-collapse:collapse;font-family:monospace;font-size:14px">
      <tr><td style="padding:4px 16px 4px 0;color:#888">Node</td>
          <td><b>${node_id}</b></td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Location</td>
          <td>${location}</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Alert Level</td>
          <td><b style="color:#cc3300">SIGNAL ${signal}</b></td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Temperature</td>
          <td>${temperature} °C</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Humidity</td>
          <td>${humidity} %</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Wind Speed</td>
          <td>${wind_speed} km/h</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Rain</td>
          <td>${rain} mm</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Total Rain</td>
          <td>${total_rain} mm</td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:#888">Time (PHT)</td>
          <td>${new Date().toLocaleString('en-PH', { timeZone: 'Asia/Manila' })}</td></tr>
    </table>
    <p style="margin-top:20px;font-size:12px;color:#aaa">
      UCV CEIT · WSN Research Group · Tuguegarao, Cagayan
    </p>
  `;

  try {
    await mailer.sendMail({
      from: `"UCV WSN Node-01" <${process.env.GMAIL_USER}>`,
      to: process.env.ALERT_EMAIL_TO,
      subject,
      html,
    });
    console.log(`[ALERT] ✓ Email sent — Signal ${signal}`);
    res.json({ status: 'ok' });
  } catch (err) {
    console.error('[ALERT] !! Email failed:', err.message);
    res.status(500).json({ error: err.message });
  }
});

// ── GET /api/data — Dashboard fetches data ──
app.get('/api/data', (req, res) => {
  const rows  = Math.min(1000, Math.max(1, parseInt(req.query.rows) || 60));
  const range = req.query.range || 'live';

  const rangeMap = {
    live:    `NOW() - INTERVAL '2 hours'`,
    today:   `DATE_TRUNC('day', NOW())`,
    '3days': `NOW() - INTERVAL '3 days'`,
    week:    `NOW() - INTERVAL '7 days'`,
    month:   `NOW() - INTERVAL '30 days'`,
  };
  const since = rangeMap[range] || rangeMap.live;

  const query = rows === 1
    ? `SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT 1`
    : `SELECT * FROM sensor_data WHERE recorded_at >= ${since} ORDER BY recorded_at DESC LIMIT $1`;

  const params = rows === 1 ? [] : [rows];

  pool.query(query, params).then(result => {
    const data = rows === 1 ? (result.rows[0] || {}) : result.rows.reverse();
    res.json(data);
  }).catch(err => {
    console.error('[GET] !! DB error:', err.message);
    res.status(500).json({ error: 'DB error' });
  });
});

// ── Serve index.html for root ───────────────
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'index.html'));
});

// ── Start server ────────────────────────────
initDB().then(() => {
  app.listen(PORT, () => {
    console.log(`[SERVER] ✓ Running on port ${PORT}`);
    console.log(`[SERVER] ✓ POST /api/data  — Arduino data endpoint`);
    console.log(`[SERVER] ✓ POST /api/alert — Arduino alert endpoint`);
    console.log(`[SERVER] ✓ GET  /api/data  — Dashboard endpoint`);
  });
}).catch(err => {
  console.error('[DB] !! Failed to connect:', err.message);
  process.exit(1);
});
