# OT-CapEx System (Overtime & CapEx Labor Management)

**Lead Developer:** Zulfikar Hidayatullah (+62 857-1583-8733)  
**Tech Stack:** Laravel 12+ · Inertia.js v3 · Vue 3 · Wayfinder · Tailwind CSS v4 · Redis · PostgreSQL/MySQL  
**Operational Standards:** Timezone `Asia/Jakarta` (WIB) · Currency `Rp (IDR)` · Package Manager `pnpm`

---

## 1. Getting Started

### Prerequisites

- PHP 8.3+ with `pdo`, `mbstring`, `redis`, `bcmath` extensions
- Node.js 20+ & `pnpm`
- Composer 2+
- Redis Server (local or containerized)

### Local Development Setup

```bash
# 1. Clone repository and install dependencies
git clone <repo-url>
cd overtime-app
composer install
pnpm install

# 2. Environment configuration
cp .env.example .env
php artisan key:generate

# 3. Database migrations & seed demo plant data
php artisan migrate:fresh --seed

# 4. Compile frontend assets with Wayfinder route generation
pnpm dev
# Or production build:
pnpm build
```

---

## 2. Default Seeded Demo Accounts

All demo accounts use the standard password: `password`

| Role            | Email                           | NPK         | Assigned Area                 |
| --------------- | ------------------------------- | ----------- | ----------------------------- |
| **Admin**       | `admin@factory.com`             | `EMP-00001` | Plant-Wide Master Access      |
| **Manager**     | `manager.assembly@factory.com`  | `EMP-00101` | Assembly Department           |
| **Manager**     | `manager.stamping@factory.com`  | `EMP-00102` | Stamping Department           |
| **Team Leader** | `tl.stamping.press@factory.com` | `EMP-00201` | Stamping - Press Line         |
| **Team Leader** | `tl.assembly.trim@factory.com`  | `EMP-00204` | Assembly - Trim Line          |
| **Operator**    | `operator.01@factory.com`       | `EMP-01002` | Assembly - Trim Line Operator |

---

## 3. Background Workers & Redis Queues (E01-06)

The application utilizes Redis queues (`QUEUE_CONNECTION=redis`) to process asynchronous workloads (statistical ML anomaly detection, monthly financial burndown recalculations, and physical SPKL upload reminders).

### Running Workers Locally

```bash
# Start queue worker on default connection
php artisan queue:work redis --tries=3 --backoff=30,120,300
```

### Production Worker Configuration (Supervisor)

Create `/etc/supervisor/conf.d/ot-capex-worker.conf`:

```ini
[program:ot-capex-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/overtime-app/artisan queue:work redis --sleep=3 --tries=3 --backoff=30,120,300 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/overtime-app/storage/logs/worker.log
stopwaitsecs=3600
```

Load and start supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ot-capex-worker:*
```

---

## 4. Quality & Testing Checklist

Always execute the following verification steps before creating pull requests:

```bash
# 1. PHP style verification & auto-formatting
vendor/bin/pint --format agent

# 2. Frontend lint & type check
pnpm lint

# 3. Production asset compilation
pnpm build

# 4. Feature and Browser test suites
php artisan test --compact
```

---

## 5. Documentation Library

For detailed specifications, architectural decision records, and end-user manuals, consult the `docs/` folder:

- **[Master Documentation Index](docs/Readme.md)**
- **[System Architecture Blueprint](docs/architecture.md)**
- **[Developer Documentation](docs/dev-docs/README.md)**
- **[User Documentation](docs/user-docs/README.md)**
