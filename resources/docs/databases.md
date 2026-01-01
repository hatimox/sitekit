# Databases

## Creating Databases

Create databases on your provisioned servers for your applications.

**Supported Databases:**
- **MariaDB** (default, MySQL-compatible)
- **PostgreSQL** (optional during provisioning)

**Steps:**
1. Navigate to "Databases" → "Create Database"
2. Select the server
3. Enter a database name (lowercase, underscores allowed)
4. The database is created automatically

**Naming Conventions:**
- Use lowercase letters, numbers, and underscores
- Avoid special characters and spaces
- Example: `myapp_production`, `blog_db`

---

## Database Users

Each database can have dedicated users with specific permissions.

**Creating Users:**
1. Go to Database → Users tab
2. Click "Add User"
3. Enter username and password
4. Select permissions (read, write, all)

**Permission Levels:**
- **Read Only**: SELECT queries only
- **Read/Write**: SELECT, INSERT, UPDATE, DELETE
- **All Privileges**: Full access including schema changes

**Connection Details:**
```
Host: 127.0.0.1
Port: 3306 (MariaDB) or 5432 (PostgreSQL)
Database: your_database_name
Username: your_user
Password: your_password
```

**Remote Access:**
By default, databases only accept local connections. To enable remote access:
1. Add a firewall rule for port 3306 or 5432
2. Update the database bind address (use with caution)

---

## Backups

Protect your data with regular database backups.

**Manual Backups:**
1. Go to Database → Backups tab
2. Click "Create Backup"
3. Download the backup file when ready

**Backup Location:**
Backups are stored in `/opt/sitekit/backups/databases/`

**Backup Format:**
- MariaDB: `.sql.gz` (gzipped SQL dump)
- PostgreSQL: `.dump` (pg_dump format)

**Restoring from Backup:**
```bash
# MariaDB
gunzip < backup.sql.gz | mysql -u root database_name

# PostgreSQL
pg_restore -d database_name backup.dump
```

**Best Practices:**
- Schedule regular automated backups
- Test restore procedures periodically
- Store backups off-server for disaster recovery
- Encrypt sensitive backup files
