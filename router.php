<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri === '/api/data.php') {
    require __DIR__ . '/api/data.php';
    exit;
}

if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.html';
```

---

## On Railway:

**1. Delete MySQL service**
**2. Add PostgreSQL service**
**3. Go to `Weather-Status-repo` → Variables → Add Variable Reference → select `DATABASE_URL` from PostgreSQL**

---

## Your final repo structure:
```
Weather-Status-repo/
├── railway.json
├── nixpacks.toml
├── router.php
├── index.html
└── api/
    └── data.php
