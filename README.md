# Directorist Smart Assistant

AI-powered chat assistant for Directorist listings using OpenAI.

## Description

Directorist Smart Assistant is a WordPress plugin extension for Directorist that adds an AI-powered chat assistant to your directory website. The assistant can answer questions about your listings using OpenAI's GPT models.

## Features

- **AI-Powered Chat**: Interactive chat widget powered by OpenAI GPT models
- **Listing Context**: Automatically includes all Directorist listings in the AI context
- **Admin Settings**: Easy-to-use admin interface for configuring OpenAI API settings
- **Modern UI**: Beautiful, responsive chat widget with WordPress admin styling
- **Secure**: Encrypted API key storage and proper permission checks

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Directorist plugin (active)
- OpenAI API key

## Installation

1. Upload the plugin files to `/wp-content/plugins/directorist-smart-assistant/`
2. Install dependencies:
   ```bash
   composer install
   npm install
   ```
3. Build assets:
   ```bash
   npm run build
   ```
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Navigate to Directorist > Smart Assistant to configure your OpenAI API key

## Development

### Setup

1. Install dependencies:
   ```bash
   composer install
   npm install
   ```

2. Start development build (with watch):
   ```bash
   npm run start
   ```

3. Build for production:
   ```bash
   npm run build
   ```

### Project Structure

```
directorist-smart-assistant/
├── assets/
│   ├── src/
│   │   ├── admin/          # Admin React components
│   │   └── chat-widget/    # Frontend chat widget
│   └── build/              # Compiled assets
├── inc/
│   ├── Admin/              # Admin PHP classes
│   ├── Frontend/           # Frontend PHP classes
│   ├── REST_API/           # REST API endpoints
│   └── Settings/           # Settings management
├── languages/              # Translation files
├── composer.json           # PHP dependencies
├── package.json            # Node dependencies
└── directorist-smart-assistant.php  # Main plugin file
```

## Configuration

1. Go to **Directorist > Smart Assistant** in WordPress admin
2. Enter your OpenAI API key (get one at https://platform.openai.com/api-keys)
3. Select your preferred GPT model
4. Customize the system prompt
5. Adjust temperature and max tokens as needed
6. Click "Save Settings"

## Usage

Once configured, a chat button will appear in the bottom-right corner of your website. Visitors can click it to start chatting with the AI assistant about your listings.

## Security

- API keys are encrypted before storage
- All REST API endpoints have proper permission checks
- User inputs are sanitized and validated
- Nonces are used for all AJAX requests

## Support

For support, visit https://wpxplore.com

## Changelog

### 1.0.0
- Initial release
- OpenAI integration
- Chat widget
- Admin settings page
- Listing context integration

## License

GPL v2 or later

## Author

wpXplore - https://wpxplore.com

