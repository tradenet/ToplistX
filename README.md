# ToplistX

http://www.unofficialjmbsupport.com/

## PHP Version Requirements

**Minimum Required PHP Version: 8.2**

This codebase has been migrated to PHP 8.2 with full support for modern PHP features and best practices. All deprecated functions and MySQL extensions have been replaced with MySQLi prepared statements for enhanced security and compatibility.

### Migration Details

- ✅ Migrated from PHP 5.6 to PHP 8.2
- ✅ Replaced all deprecated `mysql_*` functions with MySQLi
- ✅ Implemented prepared statements for SQL injection protection
- ✅ Added strict parameter and return type hints
- ✅ Updated class constructors to modern PHP syntax
- ✅ Added visibility modifiers to all class properties
- ✅ Removed deprecated PHP functions and constants
- ✅ Fixed undefined array key notices in critical entrypoints
  - Added safe `$_REQUEST` key defaults in installer, public rating/comment handlers, admin interfaces, and utility scripts
  - Implemented `Request()` helper function for guarded access to `$_REQUEST` globals

### Recent Updates (PHP 8.2 Hardening)

This repository now includes comprehensive fixes for PHP 8.2's stricter undefined array key handling:

- **Public Entrypoints**: rate.php, out.php
- **Admin Handlers**: admin/ajax.php, admin/index.php
- **Utilities**: mysql-change.php, arp-convert.php, arphp-convert.php
- **Core Library**: Request() helper added to includes/common.php for safe $_REQUEST access

All changes use null-coalescing operator (`??`) to provide sensible defaults, ensuring zero undefined index notices in PHP 8.2 strict mode.
