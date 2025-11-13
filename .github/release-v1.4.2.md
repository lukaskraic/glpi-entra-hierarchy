# 🔧 Critical Bug Fix Release v1.4.2

## ⚠️ What was fixed?

This release fixes a **critical bug** that affected users who performed **fresh installations** of v1.4.0 or v1.4.1 (not upgrades).

### The Problem

Users encountered this MySQL error when trying to save plugin configuration:
```
MySQL error 1054: Unknown column 'oauth_enabled' in 'field list'
```

### Root Cause

The `hook.php` file had inconsistent database schema creation:
- **Upgrade path** (ALTER TABLE migrations): ✅ Correctly added OAuth columns
- **Fresh install path** (CREATE TABLE): ❌ Missing 6 OAuth columns

---

## ✅ What's Fixed

### Code Changes

1. **hook.php** - Added missing OAuth columns to CREATE TABLE statement
2. **setup.php** - Version bump to 1.4.2
3. **CHANGELOG.md** - Comprehensive documentation
4. **sql/hotfix-1.4.2.sql** - NEW migration script for affected users

### OAuth Columns Added

```sql
oauth_enabled          tinyint      NOT NULL DEFAULT '0'
oauth_client_id        varchar(255) DEFAULT NULL
oauth_client_secret    varchar(255) DEFAULT NULL
oauth_tenant_id        varchar(255) DEFAULT NULL
oauth_redirect_uri     varchar(500) DEFAULT NULL
oauth_auto_redirect    varchar(20)  NOT NULL DEFAULT 'never'
```

---

## 🧪 Testing

Validated with Docker E2E test:
- ✅ Fresh GLPI 11.0.1 installation
- ✅ Plugin v1.4.2 fresh installation
- ✅ Database schema verified: all 6 OAuth columns present
- ✅ Configuration save tested: no MySQL error

---

## 📦 Installation

### For Affected Users (v1.4.0 or v1.4.1 Fresh Install)

**Option 1: Reinstall Plugin** (Recommended)
```bash
# Uninstall old version via GLPI UI
# Then install v1.4.2
```

**Option 2: Apply Hotfix SQL Script**
```bash
mysql -u glpi_user -p glpi_database < sql/hotfix-1.4.2.sql
```

---

**Full Changelog**: https://github.com/lukaskraic/glpi-entra-hierarchy/compare/v1.4.1...v1.4.2
