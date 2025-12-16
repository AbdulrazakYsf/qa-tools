# QA Automation Dashboard System Architecture

## Overview
The **QA Automation Dashboard** ("QA Tools") is a centralized, web-based platform for executing automation scripts (Checkers, Scrapers, Validators) against the Jarir e-commerce environment. It enables QA testers to run batch validations, manage configurations, and generate reports.

## Core System Components

### 1. Application Layer (PHP)
*   **`dashboard.php`** (formerly `qa-dash9.php`): The core "Single File Application".
    *   **Frontend**: Renders the sophisticated UI (Tabs, Modals, Charts).
    *   **Backend**: Handles all API requests (`?api=...`) for Runs, Configs, Users, and Support.
    *   **Tool Engine**: Contains the embedded source code for all 13 automation tools.
*   **`index.php`**: The entry point. Handles **Login Authentication** and redirects to `dashboard.php`.
*   **`auth_session.php`**: The shared **Security Kernel**.
    *   Manages PHP Sessions (`session_start`).
    *   Provides `get_db_auth()` for centralized PDO Database connections.
    *   Enforces Access Control (`require_login()`, `require_role()`).
    *   **Auto-Migration**: Checks and creates missing DB tables/columns on connection.
*   **`qa_run_report.php`**: Generates standalone, printable HTML reports for test runs.

### 2. Database Schema (MySQL)
The system relies on a relational schema for persistence:
*   **`qa_users`**: Stores authentication data (`email`, `password_hash`, `role`, `avatar_url`).
*   **`qa_tool_configs`**: Stores JSON-encoded input configurations for tools.
*   **`qa_test_runs`**: Stores summary metrics of execution runs.
*   **`qa_run_results`**: Stores the raw JSON output from tools.
*   **`qa_support_messages`**: Stores support tickets.
    *   Columns: `user_id`, `subject`, `message`, `is_read`, `admin_reply`, `reply_at`.

### 3. Support & Reply System
A fully integrated ticketing system with **threaded messaging**:
*   **Users**: Can send tickets and reply to threads. Interface shows distinct bubbles (Left/White).
*   **Admins**: Manage tickets via "Support Center". Replies appear on the Right (Blue).
*   **Data Model**: Single-table storage (`qa_support_messages`). Replies are concatenated with separators and parsed at runtime.
*   **Notifications**: 3-state logic (`0`=AdminUnread, `1`=UserUnread, `2`=Read) ensures badges clear correctly for both parties.

### 4. Client-Side Architecture (JS)
*   **Tools**: Each tool runs in an isolated `iframe`.
*   **Execution**: The dashboard orchestrates a "Run Loop", injecting configs into iframes and polling for results.
*   **UI**: Vanilla JS handles all interactivity (Modals, AJAX, Chart.js rendering).

## Deployment & Security
*   **Host**: InfinityFree (LAMP Stack).
*   **Deploy**: GitHub Actions via FTP.
*   **Secrets**: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.
*   **Security**:
    *   Passwords hashed via `password_hash()`.
    *   Role-Based Access Control (Admin vs Tester vs Viewer).
    *   All DB queries prepared to prevent SQL Injection.
