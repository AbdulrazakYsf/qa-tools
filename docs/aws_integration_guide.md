# AWS Integration & Automated Deployment Guide

This guide documents the complete process used to integrate the QA Tools application with AWS Lightsail (Ubuntu), including server setup, database migration, and setting up a CI/CD pipeline using GitHub Actions.

## 1. Server Provisioning & Setup

We used **AWS Lightsail** with a standard **Ubuntu** blueprint (not Bitnami) to have full control over the stack.

### 1.1 Instance Creation
- **Provider**: AWS Lightsail
- **OS**: Linux/Unix, OS Only -> **Ubuntu 24.04 LTS**
- **Plan**: Nano ($3.50/month)
- **Static IP**: Attached a static IP to ensure consistent access.

### 1.2 LAMP Stack Installation
Since we chose a clean OS, we manually installed the stack. 
**[See detailed Manual LAMP Setup Guide](manual_lamp_setup_guide.md)** for exact commands.
- **Web Server**: Apache2
- **Database**: MySQL Server
- **Language**: PHP 8.3 (with extensions: `php-curl`, `php-json`, `php-mbstring`, `php-xml`, `php-zip`, `php-mysql`).

## 2. Database Migration

1.  **Export**: Exported `.sql` backup from the previous hosting (InfinityFree).
2.  **Create User**: Created a dedicated SQL user `qa_user` on the AWS instance (avoiding root for app connection).
    ```sql
    CREATE USER 'qa_user'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';
    GRANT ALL PRIVILEGES ON if0_40372489_init_db.* TO 'qa_user'@'localhost';
    ```
3.  **Import**: Imported the backup schema and data.
    ```bash
    mysql -u qa_user -p if0_40372489_init_db < backup.sql
    ```

## 3. Automated Deployment (CI/CD)

We migrated from FTP to **SSH/Rsync** deployment for better speed and security.

### 3.1 GitHub Actions Workflow
File: `.github/workflows/deploy_aws.yml`

This workflow runs on every push to `main`:
1.  **Checkout**: Grabs the latest code.
2.  **Transfer (SCP)**: Uses `appleboy/scp-action` to copy files to the server.
3.  **Post-Process (SSH)**: Uses `appleboy/ssh-action` to:
    - Fix permissions (`chown www-data`, `chmod 755`).
    - Run any necessary migration scripts (optional).

### 3.2 Required Secrets
Configure these in GitHub Repo > Settings > Secrets and variables > Actions:
- `AWS_HOST`: The Static IP of the Lightsail instance.
- `AWS_USERNAME`: `ubuntu`
- `AWS_PORT`: `22`
- `AWS_KEY`: The private SSH key contents (`-----BEGIN OPENSSH PRIVATE KEY-----...`).

## 4. API Enhancements & Configuration

To support Postman and programmatic access, we made specific configuration changes.

### 4.1 Authorization Header Fix
Apache often strips the `Authorization` header. We fixed this by:
1.  **Adding `.htaccess`**:
    ```apache
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{HTTP:Authorization} ^(.*)
        RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]
    </IfModule>
    ```
2.  **Updating `api.php`**: Added a fallback to check `apache_request_headers()` if `$_SERVER['HTTP_AUTHORIZATION']` is missing.

### 4.2 Flexible API Key Support
The API now accepts the API Key in three ways (in order of priority):
1.  **Header**: `Authorization: Bearer <key>`
2.  **Body (JSON)**: `{"api_key": "<key>", "tool": "..."}`
3.  **URL Query**: `?api_key=<key>`

This ensures maximum compatibility with different clients.

## 5. Troubleshooting Common Issues

### "500 Internal Server Error"
- Check Apache logs: `sudo tail -f /var/log/apache2/error.log`
- Often due to missing PHP extensions. We installed:
  ```bash
  sudo apt install php-curl php-xml php-mbstring
  ```

### "401 Unauthorized" despite sending key
- Ensure `.htaccess` is present in `/var/www/html/`.
- Ensure `AllowOverride All` is enabled in your Apache config (`/etc/apache2/apache2.conf`) for the web root directory.
- OR simply use the **Body** method for the API Key.
