# QA Automation System Architecture

## Overview
The system is a centralized dashboard for running various QA automation tools (checkers, scrapers, validators) against the Jarir e-commerce platform. It is built as a hybrid Single Page Application (SPA) where the frontend and backend are largely contained within a single file (`qa-dash3.php`), supported by a MySQL database for persistence.

## Core Components

### 1. Dashboard (`qa-dash3.php`)
This is the heart of the application. It serves dual purposes:
*   **Frontend (HTML/JS)**:
    *   Displays the main UI with a grid of available tools.
    *   Manages "Run All" functionality to execute multiple tools in batch.
    *   Handles User and Configuration management via modal forms.
    *   Visualizes test run statistics (Charts.js).
    *   **Tool Execution**: Loads selected tools into an `iframe`. It injects configurations (inputs, logic) into the iframe and extracts results after execution.
*   **Backend (PHP)**:
    *   **HTML Hosting**: Serves the main dashboard user interface.
    *   **Tool Hosting**: Contains a large definition array `$TOOLS_HTML` which holds the complete HTML/JS source for all 13 supported tools.
    *   **API Endpoint**: Handles AJAX requests via `?api=ACTION` (e.g., `save-run`, `list-configs`).

### 2. Database (MySQL)
The system connects to a remote MySQL database (`sql309.infinityfree.com`) to store persistent data.
*   **`qa_tool_configs`**: Stores named configurations for tools (e.g., input URLs, SKUs) so they can be reused.
*   **`qa_test_runs`**: Records summary data of each test execution (total tests, passed, failed status).
*   **`qa_run_results`**: Stores detailed per-row results for every test run (JSON payload of the tool output).
*   **`qa_users`**: Manages authorized users/testers.

### 3. Reporting (`qa_run_report.php`)
A dedicated PHP script for generating detailed, printer-friendly reports.
*   Fetches run data from `qa_test_runs` and `qa_run_results` based on a `run_id`.
*   Renders a static HTML view of the results, grouped by tool.

### 4. Tools (Embedded)
There are 13 distinct tools embedded within `qa-dash3.php`. Each is a self-contained HTML/JS "mini-app" that performs a specific testing task.
*   **List of Tools**:
    1.  `add_to_cart`: Checks add-to-cart functionality (Guest/Logged-in).
    2.  `brand`: Validates brand pages.
    3.  `category`: Checks category pages.
    4.  `category_filter`: Checks filtered category pages.
    5.  `cms`: Validates CMS blocks.
    6.  `getcategories`: Fetches and checks category trees.
    7.  `images`: Validates image loading on pages.
    8.  `login`: Tests login flows across different store fronts.
    9.  `price_checker`: Validates product pricing.
    10. `products`: General product validation.
    11. `sku`: Lookup and validation of SKUs.
    12. `stock`: Checks stock availability status.
    13. `sub_category`: Validates sub-category navigation.

## Execution Flow

1.  **Configuration**: User creates/selects a "Configuration" for a tool (e.g., a list of Production URLs or SKUs).
2.  **Selection**: User selects one or more tools to run from the Dashboard.
3.  **Execution Loop**:
    *   Dashboard iterates through selected tools.
    *   Builds an `iframe` populated with the tool's HTML (from `$TOOLS_HTML`).
    *   Injects the selected configuration (inputs) into the tool's DOM.
    *   Triggers the tool's `run()` function.
4.  **Result Collection**:
    *   The tool performs its logic (AJAX calls to Jarir APIs, parsing response).
    *   The Dashboard polls or waits for the tool to finish.
    *   Results are scraped from the tool's internal state or DOM.
5.  **Persistence**:
    *   Dashboard aggregates results.
    *   Sends a `save-run` API request to the PHP backend.
    *   PHP saves the run summary and details to MySQL.

## File Structure
*   `qa-dash3.php`: Main application (Frontend + Backend + Tools).
*   `qa_run_report.php`: Reporting viewer.
*   `qa_bridge.js`: (Implied) Helper script likely used for communication between iframe and dashboard, though logic is mostly handled in `qa-dash3.php`.
*   `tools/*.html`: Standalone development versions of the tools (contents are copied into `qa-dash3.php`).
