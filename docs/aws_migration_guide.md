# AWS Migration Guide: QA Tools

To resolve the issue with API access being blocked by the "aes.js" browser check, you need to migrate your application to a standard hosting environment. **AWS Lightsail** is recommended as the easiest and most cost-effective starting point ($3.50/month).

## Phase 1: Create a Server
1.  **Log in** to the [AWS Console](https://console.aws.amazon.com/).
2.  Search for **Lightsail**.
3.  Click **Create instance**.
4.  **Platform**: Linux/Unix.
5.  **Blueprint**: Select **LAMP (PHP 8)**. This comes pre-installed with Apache, MySQL, and PHP.
6.  **Instance Plan**: Choose the **$3.50** plan (Nano).
7.  **Identify**: Name it `qa-tools-server`.
8.  Click **Create instance**.

## Phase 2: Connect and Prepare
1.  Wait for the instance to show "Running".
2.  Click the orange **>_ (Terminal)** icon to connect via SSH.
3.  **Get Database Credentials**:
    Run this command to see your MySQL password:
    ```bash
    cat bitnami_application_password
    ```
    *(Save this password!)*

## Phase 3: Migrate Database
You need to move your data from `sql309.infinityfree.com` to your new AWS server.
1.  **Export current data**:
    - Go to your current hosting's **phpMyAdmin**.
    - Select your database (`if0_40372489_init_db`).
    - Click **Export** -> **Quick** -> **Go**.
    - Save the `.sql` file.
2.  **Import to AWS**:
    - Upload the `.sql` file to your AWS server (you can use SFTP or drag-and-drop if using the Lightsail browser console).
    - In the AWS terminal, import it:
      ```bash
      mysql -u root -p < your_backup_file.sql
      ```
      *(Enter the password you retrieved in Phase 2)*.

## Phase 4: Deploy Code
1.  **Generate a Zip** of your local project folder (`QA-TOOLS`).
2.  **Upload** it to the server (using SFTP like FileZilla or the Lightsail upload feature) to `/home/bitnami/`.
3.  **Move files to Web Root**:
    In the AWS terminal:
    ```bash
    # Unzip
    unzip QA-TOOLS.zip
    
    # Remove default files
    sudo rm -rf /opt/bitnami/apache/htdocs/*
    
    # Move your files
    sudo mv QA-TOOLS/* /opt/bitnami/apache/htdocs/
    ```

## Phase 5: Update Configuration
1.  **Edit [auth_session.php](file:///c:/Users/ASUS/Downloads/QA-TOOLS/auth_session.php)** on the server:
    ```bash
    sudo nano /opt/bitnami/apache/htdocs/auth_session.php
    ```
2.  Update the DB constants:
    - Host: `localhost`
    - User: `root`
    - Password: *(Your AWS Password)*
    - DB Name: `if0_40372489_init_db` (or whatever you named it during import).
3.  **Save**: `Ctrl+O`, `Enter`, `Ctrl+X`.

## Phase 6: Test
- Open your Instance's **Public IP** in the browser.
- Try the API using Postman (http://YOUR_IP/api.php...).
- It should work immediately without any "aes.js" checks!

---
> [!TIP]
> **Static IP**: In Lightsail > Networking, create a Static IP and attach it to your instance so the IP doesn't change on reboot.
