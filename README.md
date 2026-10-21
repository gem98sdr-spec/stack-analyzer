# PHP Project Stack Analyzer 🚀

A highly intelligent, ultra-lightweight, single-file PHP script that instantly analyzes any web directory to detect the active framework, required PHP version, server PHP version, and all installed dependencies.

## Features
- **Smart Framework Detection:** Automatically identifies frameworks like Laravel, Symfony, CodeIgniter, Yii, and CMS platforms like WordPress or Magento.
- **Dependency Parser:** Reads `composer.json` (if available) to list all production and development libraries with their specific versions.
- **PHP Version Matcher:** Displays the server's current PHP version next to the project's required PHP version.
- **Beautiful Dashboard UI:** A clean, zero-dependency, dark-mode interface that makes reading project specs a breeze.
- **JSON API Support:** Need the data programmatically? Just add `?api=true` to the URL.

## How to Use

Drop `analyzer.php` into the root directory of any PHP project (or any folder you want to inspect).

### 1. Visual Dashboard
Navigate to the file in your browser to see the cleanly formatted analysis:
`http://your-domain.com/analyzer.php`

### 2. JSON API
Fetch the exact same intelligence in JSON format by adding the `api=true` parameter.

**Request:**
`GET /analyzer.php?api=true`

**Response:**
```json
{
  "path": "/var/www/html/my-app",
  "analysis": {
    "server_php": "8.2.10",
    "project_php": "^8.1",
    "framework": "Laravel (^10.10)",
    "dependencies": {
      "guzzlehttp/guzzle": "^7.2",
      "laravel/framework": "^10.10"
    },
    "dev_dependencies": {
      "phpunit/phpunit": "^10.1"
    }
  }
}
