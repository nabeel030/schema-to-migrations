# Schema to Migrations

Convert an existing MySQL `.sql` dump or schema into **Laravel migrations** automatically.

This package is designed for developers migrating legacy projects (Core PHP, CodeIgniter, WordPress, etc.) into Laravel and want **clean, accurate migrations** without rewriting everything manually.

---

## ✨ Features

- Import a MySQL `.sql` file into a temporary database
- Introspect schema using `information_schema`
- Generate **Laravel-native migrations**
- Supports:
  - Auto-increment IDs
  - ENUM columns (with defaults)
  - Nullable columns
  - Default values
  - Unsigned integers
  - Foreign keys (separate migration)
- Laravel **12 compatible**
- Windows (Laragon / XAMPP) friendly

---

## 📦 Installation

```bash
composer require nabeel030/schema-to-migrations
```

Laravel will auto-discover the service provider.

---

## 🚀 Basic Usage

```bash
php artisan migrate:import-schema database/schema.sql
```

This will:

1. Create a temporary database
2. Import the `.sql` file
3. Read schema from `information_schema`
4. Generate migration files
5. (Optionally) drop the temporary database

---

## 🧾 Command Signature

```bash
php artisan migrate:import-schema <sql-file>
```

### Example

```bash
php artisan migrate:import-schema database/legacy.sql
```

---

## ⚙️ Available Options

| Option | Description |
|------|------------|
| `--connection` | Database connection name (default: `mysql`) |
| `--database` | Temporary database name (default: `legacy_tmp`) |
| `--output` | Output directory for migrations |
| `--fk` | Foreign key mode: `separate` or `inline` |
| `--drop-temp` | Drop temp database after generation |
| `--mysql-bin` | Full path to `mysql` executable |

---

## 📁 Example with All Options

```bash
php artisan migrate:import-schema database/legacy.sql \
  --connection=mysql \
  --database=legacy_tmp \
  --output=database/migrations/imported \
  --fk=separate \
  --drop-temp
```

---

## 🪟 Windows (Laragon / XAMPP) Setup

On Windows, the `mysql` CLI is often **not available in PATH** even though MySQL works with Laravel migrations.

This package uses the **MySQL CLI** to safely import large `.sql` dumps.

### Option 1 (Recommended): Set environment variable

Add this to your `.env` file:

```env
STM_MYSQL_BIN=D:/laragon/bin/mysql/mysql-8.0.xx-winx64/bin/mysql.exe
```

Adjust the path according to your Laragon/XAMPP installation.

### Option 2: Pass it directly to the command

```bash
php artisan migrate:import-schema database/legacy.sql \
  --mysql-bin="D:/laragon/bin/mysql/mysql-8.0.xx-winx64/bin/mysql.exe"
```

---

## 🧪 Running the Generated Migrations

To run **only the generated migrations** on a fresh database:

```bash
php artisan migrate:fresh --path=database/migrations/imported
```

This is the **recommended** approach to avoid conflicts with Laravel’s default migrations.

---

## ⚠️ Important Notes

- The `migrations` table is **NOT generated** by this package  
  (Laravel creates it automatically — this is intentional)
- Foreign keys are generated in a **separate migration** when using `--fk=separate`
- Circular foreign keys may require manual adjustment (rare, legacy schemas)

---

## 🛠 Supported Column Types

- `int`, `bigint`, `smallint`
- `varchar`, `text`, `longtext`
- `enum`
- `boolean`
- `timestamp`, `datetime`
- auto-increment primary keys

---

## ❌ Not Supported (yet)

- Stored procedures
- Triggers
- Views
- Check constraints
- Indexes / unique keys (planned)

---

## 🗺 Roadmap

Planned improvements:

- Index & unique key generation
- Live database introspection (no SQL file)
- PHP Enum generation
- Composite primary keys
- Test suite & CI

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome.

1. Fork the repository
2. Create a feature branch
3. Submit a pull request

---

## 📄 License

MIT License © 2025 Nabeel

---

## ⭐ Support

If this package saved you time, consider starring the repository on GitHub.
