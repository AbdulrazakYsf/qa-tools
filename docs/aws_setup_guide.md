# AWS API Gateway Setup Guide

Since we cannot programmatically create resources without AWS Admin credentials, this guide will walk you through setting up an **HTTP API** in AWS API Gateway to proxy requests to your existing backend at `https://qa-tools.42web.io/api.php`.

## Prerequisites
- Access to the [AWS Management Console](https://console.aws.amazon.com/apigateway).
- Your Backend URL: `https://qa-tools.42web.io/api.php`
- Your API Key (for testing): `SS79XKCjcG73OrTnqfEIE1i1Wr8oGWGy4MTvcsUZ`

## Step 1: Create the API
1.  Navigate to **API Gateway** in the AWS Console.
2.  Click **Create API**.
3.  Under **HTTP API**, click **Build**.
4.  **Step 1: Create an API**:
    *   **Integration**: Add Integration > **HTTP**.
    *   **Method**: `ANY` (or select specific methods like GET/POST).
    *   **URL**: `https://qa-tools.42web.io/api.php`
    *   **API Name**: `QA Tools Proxy` (or your preferred name).
    *   Click **Next**.

## Step 2: Configure Routes
1.  **Step 2: Configure routes**:
    *   Resource path: `/` (Default).
    *   Target: Your HTTP Integration (`https://qa-tools.42web.io/api.php`).
    *   *Note: If you want to match the path structure, you can use `/{proxy+}`, but since your backend is a single file (`api.php`), mapping root `/` is sufficient.*
    *   Click **Next**.

## Step 3: Define Stages
1.  **Step 3: Define stages**:
    *   **Stage name**: `$default` (Keep auto-deploy enabled).
    *   Click **Next**.

## Step 4: Review and Create
1.  Review your settings.
2.  Click **Create**.

## Step 5: Test Your AWS Endpoint
Once created, AWS will provide an **Invoke URL** (e.g., `https://xyz123.execute-api.us-east-1.amazonaws.com`).

You can now call your tools using this AWS URL. The API Gateway will forward the requests to your PHP backend.

**Example using Curl:**
```bash
curl "https://xyz123.execute-api.us-east-1.amazonaws.com?api_key=SS79XKCjcG73OrTnqfEIE1i1Wr8oGWGy4MTvcsUZ&tool=headers_check&urls[]=https://google.com"
```

## Optional: Configuring AWS API Key (Native Rate Limiting)
If you want to use AWS-managed API Keys (for rate limiting/metering) instead of just passing the `api_key` param to PHP:
1.  You would need to use **REST API** instead of **HTTP API** (HTTP APIs do not support Usage Plans/Api Keys in the same way).
2.  Create a **Usage Plan**.
3.  Create an **API Key**.
4.  Associate the Key with the Plan.
5.  Enable "API Key Required" on the Method Request.

*For now, the HTTP API Setup (Steps 1-4) is the simplest way to get a public AWS endpoint up and running.*
