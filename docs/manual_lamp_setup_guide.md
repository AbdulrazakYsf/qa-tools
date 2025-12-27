# Manual LAMP Setup & Migration Guide (Ubuntu)

Since you are using a standard Ubuntu instance (not the Bitnami LAMP blueprint), you must install the web server and database software yourself.

## Phase 1: Install Software
Run these commands in your Git Bash terminal connected to the AWS instance:

1.  **Update package lists**:
    ```bash
    sudo apt update
    ```
2.  **Install Apache, MySQL, PHP, and utilities**:
    ```bash
    sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql unzip -y
    ```

## Phase 2: Setup Database
1.  **Log in to MySQL** as root (sudo requires no password initially on Ubuntu):
    ```bash
    sudo mysql
    ```
2.  **Create Database and User**:
    *Replace `YOUR_SECRET_PASSWORD` with a strong password.*
    ```sql
    -- Create the database
    CREATE DATABASE if0_40372489_init_db;

    -- Create a new user
    CREATE USER 'qa_user'@'localhost' IDENTIFIED BY 'YOUR_SECRET_PASSWORD';

    -- Grant full access to the user on that database
    GRANT ALL PRIVILEGES ON if0_40372489_init_db.* TO 'qa_user'@'localhost';

    -- Apply changes and exit
    FLUSH PRIVILEGES;
    EXIT;
    ```

## Phase 3: Migrate Data
1.  **Upload your SQL file**:
    Use SFTP (FileZilla) or `scp` to upload your database backup file (e.g., `backup.sql`) to the server.
2.  **Import the SQL file**:
    Run this command in the terminal (enter the password you created in Phase 2 when prompted):
    ```bash
    mysql -u qa_user -p if0_40372489_init_db < backup.sql
    ```

## Phase 4: Deploy Code
1.  **Upload Code**:
    Upload your `QA-TOOLS.zip` file to the server (e.g., into `/home/ubuntu/`).
2.  **Deploy**:
    ```bash
    # Unzip the file
    unzip QA-TOOLS.zip

    # Remove default Apache greeting page
    sudo rm /var/www/html/index.html

    # Move your files to the web root
    sudo mv QA-TOOLS/* /var/www/html/

    # Fix permissions so Apache can read them
    sudo chown -R www-data:www-data /var/www/html/
    sudo chmod -R 755 /var/www/html/
    ```

## Phase 5: Configure Application
1.  **Edit `auth_session.php`**:
    ```bash
    sudo nano /var/www/html/auth_session.php
    ```
2.  **Update the values**:
    ```php
    <?php
    // ...
    $db_host = "localhost";
    $db_user = "qa_user";      // The user you created in Phase 2
    $db_pass = "YOUR_SECRET_PASSWORD"; // The password you created in Phase 2
    $db_name = "if0_40372489_init_db";
    // ...
    ?>
    ```
3.  **Save**: Press `Ctrl+O`, `Enter`, then `Ctrl+X`.

## Phase 6: Test
- Open your AWS Instance's **Public IP** in a browser.
- You should see your application.
