# Installation Guide

## Prerequisites

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Directorist plugin (must be active)
- Node.js and npm (for building assets)
- Composer (for PHP dependencies)

## Step 1: Install Dependencies

### PHP Dependencies

```bash
cd wp-content/plugins/directorist-smart-assistant
composer install
```

### Node Dependencies

```bash
npm install
```

## Step 2: Build Assets

Build the React components and assets:

```bash
npm run build
```

For development with watch mode:

```bash
npm run start
```

## Step 3: Activate Plugin

1. Go to WordPress Admin > Plugins
2. Find "Directorist Smart Assistant"
3. Click "Activate"

## Step 4: Configure Settings

1. Navigate to **Directorist > Smart Assistant** in WordPress admin
2. Enter your OpenAI API key (get one at https://platform.openai.com/api-keys)
3. Configure other settings as needed
4. Click "Save Settings"

## Step 5: Test

Visit your website's frontend. You should see a chat button in the bottom-right corner. Click it to test the chat functionality.

## Troubleshooting

### Assets Not Loading

If the admin page or chat widget doesn't appear, make sure you've run:

```bash
npm run build
```

### Plugin Not Appearing

- Ensure Directorist plugin is installed and activated
- Check that all PHP files are in place
- Verify Composer autoloader is generated (`vendor/autoload.php` exists)

### Chat Not Working

- Verify OpenAI API key is correctly configured
- Check browser console for JavaScript errors
- Ensure REST API is accessible (check `/wp-json/directorist-smart-assistant/v1/settings`)

## Development

### File Structure

- `inc/` - PHP classes (PSR-4 autoloaded)
- `assets/src/` - React source files
- `assets/build/` - Compiled assets (generated)
- `languages/` - Translation files

### Building for Production

```bash
npm run build
```

This will:
- Compile React components
- Minify JavaScript and CSS
- Generate asset files with dependency information

### Development Mode

```bash
npm run start
```

This runs webpack in watch mode, automatically rebuilding on file changes.

