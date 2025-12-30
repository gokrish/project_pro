# ProConsultancy ATS - Recruitment Management System

## 📋 Project Overview

ProConsultancy is a comprehensive Applicant Tracking System (ATS) designed specifically for recruitment consultancies and staffing agencies. It manages the complete recruitment lifecycle from lead generation through candidate placement.

**Version:** 5.0 (New Build)  
**Status:** Development (35-40% Complete)  
**Framework:** Vanilla PHP 8+ with MySQL  
**Architecture:** MVC-inspired custom framework

---

## 🎯 Core Features

### Implemented (Partial):
- ✅ User authentication & authorization
- ✅ Role-based permission system
- ⚠️ Candidate management (CRUD operational, needs polish)
- ⚠️ Job management (structure exists, needs completion)
- ⚠️ Submission tracking (basic workflow)
- ⚠️ Client management (foundation only)
- ⚠️ Dashboard & reporting (placeholders)

### Planned:
- ⏳ Resume parsing & CV inbox
- ⏳ Email notifications & automation
- ⏳ Document management
- ⏳ Advanced search & filtering
- ⏳ Public job board
- ⏳ Calendar integration
- ⏳ API endpoints

---

## 🏗️ System Architecture

### Directory Structure

```
proconsultancy/
│
├── includes/                  # Core framework
│   ├── Core/                 # Core classes (Auth, Database, Logger, etc.)
│   │   ├── Auth.php
│   │   ├── Database.php
│   │   ├── Permission.php
│   │   ├── Validator.php
│   │   └── ...
│   └── config/               # Configuration files
│       ├── app.php
│       ├── database.php
│       ├── config.php
│       └── constants.php
│
├── panel/                    # Admin panel (main application)
│   ├── modules/             # Feature modules
│   │   ├── candidates/      # Candidate management
│   │   ├── jobs/           # Job postings
│   │   ├── submissions/    # Candidate submissions
│   │   ├── clients/        # Client management
│   │   ├── contacts/       # Lead management
│   │   ├── users/          # User & permission management
│   │   ├── cv-inbox/       # Resume inbox
│   │   ├── applications/   # Application tracking
│   │   ├── settings/       # System settings
│   │   └── _common.php     # Shared bootstrap
│   │
│   ├── includes/           # Panel includes
│   │   ├── header.php
│   │   ├── footer.php
│   │   ├── sidebar.php
│   │   └── ...
│   │
│   ├── assets/            # Static assets
│   │   ├── css/
│   │   ├── js/
│   │   ├── images/
│   │   └── database/      # SQL schema files
│   │
│   ├── dashboards/        # Role-specific dashboards
│   ├── handlers/          # Global request handlers
│   ├── errors/            # Error pages
│   ├── login.php
│   └── dashboard.php
│
├── public/                # Public-facing pages
│   ├── index.php         # Homepage
│   ├── jobs.php          # Job listings
│   └── apply.php         # Application form
│
├── uploads/              # User uploaded files
│   ├── resumes/
│   ├── documents/
│   ├── photos/
│   └── cv_inbox/
│
├── logs/                 # Application logs
├── tests/               # Test files (minimal)
└── .env                 # Environment configuration (create this)
```

---

## 🚀 Installation Guide

### System Requirements

**Minimum:**
- PHP 8.0 or higher
- MySQL 5.7 or MariaDB 10.2+
- Apache/Nginx web server
- 512MB RAM
- 1GB disk space

**Recommended:**
- PHP 8.1+
- MySQL 8.0+
- 2GB RAM
- SSL certificate for production

**PHP Extensions Required:**
- mysqli
- PDO
- mbstring
- openssl
- json
- fileinfo
- gd (for image processing)

### Step 1: Clone/Download

```bash
# If using Git
git clone [repository-url] proconsultancy
cd proconsultancy

# Or download and extract ZIP
```

### Step 2: Environment Configuration

```bash
# Create environment file
cp .env.example .env

# Edit with your details
nano .env
```

**.env Contents:**
```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=proconsultancy
DB_USER=root
DB_PASS=your_password_here

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/proconsultancy
APP_TIMEZONE=Europe/Brussels

# Email (configure later)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
```

### Step 3: Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE proconsultancy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p proconsultancy < panel/assets/database/schema.sql

# Import default data (roles, permissions, sample data)
mysql -u root -p proconsultancy < panel/assets/database/database_default_data.sql
```

### Step 4: Create Admin User

```sql
mysql -u root -p proconsultancy

INSERT INTO users (user_code, name, full_name, email, password, level, is_active)
VALUES (
    'ADMIN001',
    'Admin',
    'System Administrator',
    'admin@yourdomain.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: "password"
    'super_admin',
    1
);
```

**⚠️ IMPORTANT:** Change this password immediately after first login!

### Step 5: File Permissions

```bash
# Make upload and log directories writable
chmod 755 uploads uploads/* logs
chmod 644 includes/config/*.php

# Security: Prevent direct access
touch uploads/.htaccess
echo "Deny from all" > uploads/.htaccess
```

### Step 6: Web Server Configuration

**Apache (.htaccess already included):**
```apache
# Enable mod_rewrite
sudo a2enmod rewrite
sudo service apache2 restart

# Virtual host example:
<VirtualHost *:80>
    ServerName proconsultancy.local
    DocumentRoot /var/www/proconsultancy
    
    <Directory /var/www/proconsultancy>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name proconsultancy.local;
    root /var/www/proconsultancy;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /uploads {
        deny all;
        return 403;
    }
}
```

### Step 7: Verify Installation

**Test database connection:**
```bash
# Access from browser:
http://localhost/proconsultancy/panel/test-db.php
```

**Access login page:**
```
URL: http://localhost/proconsultancy/panel/login.php
Username: admin@yourdomain.com
Password: password
```

---

## 🔧 Configuration

### Application Settings

Edit `includes/config/app.php`:

```php
return [
    'app_name' => 'ProConsultancy',
    'app_env' => 'development',  // production, staging, development
    'app_debug' => true,          // Set false in production
    'app_timezone' => 'Europe/Brussels',
    'items_per_page' => 25,
];
```

### Database Settings

Edit `includes/config/database.php` or use .env file.

### Security Settings

```php
// includes/config/app.php
'session_lifetime' => 1800,     // 30 minutes
'password_min_length' => 8,
'password_require_special' => true,
'max_login_attempts' => 5,
```

---

## 👥 User Roles & Permissions

### Default Roles:

1. **Super Admin** - Full system access
2. **Admin** - Can manage everything except system settings
3. **Manager** - Can manage jobs, candidates, and submissions
4. **Senior Recruiter** - Extended access to all recruitment functions
5. **Recruiter** - Standard recruiter access
6. **Coordinator** - Limited access, support functions

### Permission System:

Permissions follow the format: `module.action`

Examples:
- `candidates.view_all` - View all candidates
- `candidates.view_own` - View only assigned candidates
- `candidates.create` - Create new candidates
- `jobs.publish` - Publish jobs
- `users.manage` - Manage user accounts

---

## 🔐 Security Features

### Implemented:
- ✅ Password hashing (bcrypt)
- ✅ CSRF protection
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (output escaping)
- ✅ Session security (httponly, secure cookies)
- ✅ Login attempt limiting
- ✅ Account lockout mechanism
- ✅ Role-based access control

### Best Practices:
- Change default credentials immediately
- Use HTTPS in production
- Regular security audits
- Keep dependencies updated
- Monitor error logs
- Regular database backups

---

## 📊 Database Schema

### Core Tables:

**users** - System users and their roles  
**roles** - User role definitions  
**permissions** - Granular permission definitions  
**role_permissions** - Role-permission mappings  

**candidates** - Candidate profiles and information  
**jobs** - Job postings and requirements  
**clients** - Client companies  
**submissions** - Candidate-to-job submissions  
**applications** - Formal job applications  

**contacts** - Lead management (potential candidates/clients)  
**cv_inbox** - Resume inbox for processing  
**documents** - File management and tracking  
**activity_log** - System audit trail  

### Relationships:
- Users → Roles (many-to-one)
- Roles → Permissions (many-to-many)
- Candidates → Users (assigned_to)
- Jobs → Clients (many-to-one)
- Submissions → Jobs + Candidates (many-to-one each)

---

## 🐛 Troubleshooting

### Common Issues:

**"Page won't load" / Fatal Errors**
1. Check PHP error log: `logs/php-errors.log`
2. Verify all helper functions are defined in `_common.php`
3. Check file permissions
4. Verify database connection

**"Can't login"**
1. Verify database has users
2. Check email/password are correct
3. Ensure `is_active = 1`
4. Clear browser cookies
5. Check session configuration

**"Permission denied errors"**
1. Verify role_permissions table has data
2. Check user has valid role_id
3. Review Permission.php logic

**"Database errors"**
1. Run schema.sql again
2. Check foreign key constraints
3. Verify table exists: `SHOW TABLES;`

**"File upload fails"**
1. Check folder permissions (755)
2. Verify PHP upload_max_filesize
3. Check disk space

---

## 🚧 Known Issues & Limitations

### Critical (Must Fix):
- ❌ Missing `input()` helper function (see EMERGENCY_FIXES.md)
- ❌ Incomplete Applications module (over-engineered)
- ❌ Resume parsing not implemented
- ❌ Email notifications incomplete
- ❌ Public job board non-functional

### High Priority:
- ⚠️ Bulk operations need testing
- ⚠️ Search functionality basic only
- ⚠️ Dashboard statistics incomplete
- ⚠️ Activity logging not fully integrated
- ⚠️ File versioning missing

### Medium Priority:
- ⚠️ No mobile optimization
- ⚠️ Limited reporting capabilities
- ⚠️ No data export options
- ⚠️ Calendar integration missing

---

## 📚 Usage Guide

### Basic Workflow:

1. **Add Candidates:**
   - Panel → Candidates → Create New
   - Fill in details or upload resume (when available)
   - Assign to recruiter

2. **Create Jobs:**
   - Panel → Jobs → Create New
   - Link to client
   - Set requirements and status

3. **Submit Candidates:**
   - Open candidate profile
   - Click "Submit to Job"
   - Select job and add notes
   - Track submission status

4. **Manage Pipeline:**
   - Dashboard shows overview
   - Submissions module tracks all active submissions
   - Update status as process progresses

5. **Client Management:**
   - Add clients with contact info
   - Link jobs to clients
   - Track all submissions per client

---

## 🔄 Update & Maintenance

### Backup Strategy:

**Database:**
```bash
# Daily backup
mysqldump -u root -p proconsultancy > backup_$(date +%Y%m%d).sql

# Automated (cron)
0 2 * * * mysqldump -u root -p[password] proconsultancy > /backups/db_$(date +\%Y\%m\%d).sql
```

**Files:**
```bash
# Weekly backup
tar -czf backup_files_$(date +%Y%m%d).tar.gz uploads/ logs/
```

### Log Rotation:

```bash
# Add to /etc/logrotate.d/proconsultancy
/path/to/proconsultancy/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    missingok
}
```

---

## 📈 Performance Optimization

### Database:
- Add indexes on frequently queried columns
- Regular OPTIMIZE TABLE commands
- Monitor slow query log

### PHP:
- Enable OPcache in production
- Set appropriate memory limits
- Use connection pooling

### Caching:
- Browser cache for static assets
- Consider Redis for session storage (future)

---

## 🧪 Development Guide

### Coding Standards:

**PHP:**
- PSR-12 coding style (mostly)
- Namespaces: ProConsultancy\Core\*
- Type hints where possible
- Document all functions

**Database:**
- Always use prepared statements
- No raw queries with user input
- Wrap in transactions when appropriate

**Security:**
- Always escape output: `escape($data)`
- Verify CSRF tokens: `CSRFToken::verifyRequest()`
- Check permissions: `Permission::require('module', 'action')`

### Adding New Module:

```bash
# Create module directory
mkdir panel/modules/newmodule
mkdir panel/modules/newmodule/handlers

# Required files:
touch panel/modules/newmodule/index.php   # Entry point
touch panel/modules/newmodule/list.php    # List view
touch panel/modules/newmodule/create.php  # Create form
touch panel/modules/newmodule/edit.php    # Edit form
touch panel/modules/newmodule/view.php    # Detail view
touch panel/modules/newmodule/handlers/create.php
touch panel/modules/newmodule/handlers/update.php
touch panel/modules/newmodule/handlers/delete.php
```

### Testing Checklist:

```
□ CRUD operations work
□ Permissions enforced
□ Validation working
□ Error handling graceful
□ No SQL injection risk
□ XSS protection in place
□ CSRF tokens present
□ Mobile responsive
□ No console errors
□ No PHP errors/warnings
```

---

## 📖 Documentation

- **Analysis Report:** `ProConsultancy_Analysis_Report.md` - Comprehensive project review
- **Development Plan:** `ProConsultancy_Development_Plan.md` - Week-by-week roadmap
- **Emergency Fixes:** `ProConsultancy_EMERGENCY_FIXES.md` - Critical issues to fix first
- **This README:** Project overview and setup

---

## 🤝 Contributing

### Development Process:

1. Create feature branch: `git checkout -b feature/my-feature`
2. Make changes
3. Test thoroughly
4. Commit: `git commit -m "Add: my feature description"`
5. Push: `git push origin feature/my-feature`
6. Submit pull request

### Git Commit Conventions:

- `Add:` - New feature
- `Fix:` - Bug fix
- `Update:` - Modification to existing feature
- `Remove:` - Deletion of code/feature
- `Refactor:` - Code restructuring
- `Docs:` - Documentation changes

---

## 📞 Support

### Getting Help:

1. Check documentation first
2. Review error logs
3. Search for similar issues
4. Check troubleshooting section

### Reporting Bugs:

Include:
- Steps to reproduce
- Expected vs actual behavior
- Error messages (from logs)
- Environment details (PHP version, etc.)

---

## 📜 License

**Proprietary Software**  
All rights reserved.

This software is proprietary and confidential. Unauthorized copying, distribution, or modification is strictly prohibited.

---

## 🔮 Roadmap

### Version 1.0 (MVP) - Target: 6-8 weeks
- Complete core CRUD for all modules
- Basic workflow: Candidate → Job → Submission
- Essential permissions and security
- Email notifications (basic)

### Version 1.1 - Target: +2 months
- Resume parsing automation
- Advanced search and filtering
- Calendar integration
- Client portal (basic)

### Version 1.2 - Target: +4 months
- Mobile app
- API for integrations
- Advanced analytics
- Automated workflows

### Version 2.0 - Target: +6 months
- AI-powered candidate matching
- Video interview integration
- Multi-language support
- White-labeling options

---

## ⚖️ Final Notes

**Current Status:** This is a work in progress. Approximately 35-40% complete.

**Critical Issues:** See `EMERGENCY_FIXES.md` for problems that must be resolved before use.

**Not Production Ready:** Do not deploy to production without completing all critical fixes and thorough testing.

**Development Approach:** Follow the detailed plans in the accompanying documentation. Don't skip steps or add features prematurely.

---

**Built with dedication for the recruitment industry. 🚀**

Last Updated: December 28, 2024  
Version: 5.0 (Development Build)

