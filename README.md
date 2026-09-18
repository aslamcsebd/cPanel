# cPanel Clone Project

A PHP-based cPanel-like control panel application with web hosting management features.

## Features

### Dashboard
- System overview and resource monitoring
- Quick access to main features

### Domains
- Domain management (index)
- DNS management
- DNSSEC configuration
- SSL certificate management
- Subdomain management
- Website vhosts
- URL redirects

### Email
- Email account management
- Mailbox management
- Autoresponders
- Email forwarding
- Email filters
- DKIM configuration
- DMARC configuration
- MX records management
- Webmail SSO integration

### Databases
- MySQL database management
- PostgreSQL database management

### Files
- File manager
- Webdisk configuration

### Security
- Security overview
- SSH host keys management
- Access tokens

### Services
- Cron job management
- FTP management
- Git integration

### System
- Backups management
- Bandwidth monitoring
- Calendar integration
- System features
- Resource monitoring
- WordPress integration

### Developer Tools
- API Explorer
- Logs viewer

### Settings
- User settings and preferences

## API Layer
- `CpanelClient.php` - cPanel API client
- `ImapClient.php` - IMAP client for email operations

## Authentication
- Login/logout system
- Session management

## Getting Started

1. Configure `config.json` with your settings
2. Run `setup.php` for initial setup
3. Access the panel via `index.php`

## File Structure

```
/var/www/html/cpanel/
├── api/                    # API clients and helpers
├── dashboard.php           # Main dashboard
├── domains/                # Domain management features
├── email/                  # Email management features
├── databases/              # Database management features
├── files/                  # File management features
├── security/               # Security features
├── services/               # Service management (cron, FTP, git)
├── system/                 # System management features
├── developer/              # Developer tools
├── includes/               # Core includes (auth, layout)
├── settings.php            # User settings
├── config.json             # Configuration file
├── setup.php               # Setup script
└── index.php               # Entry point
```

## License
Proprietary


#CI/CD: common SSH concept:
- Host → Username → Key → Key's Passphrase