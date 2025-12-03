# Directorist Smart Assistant - Features

## Core Features

### 1. AI-Powered Chat Widget
- Floating chat button in bottom-right corner
- Expandable chat window with modern UI
- Real-time conversation with OpenAI GPT models
- Message history maintained during session
- Loading indicators and error handling
- Responsive design for mobile devices

### 2. Admin Settings Page
- Integrated under Directorist menu
- React-based interface using WordPress components
- TabPanel for organized settings
- Form fields for:
  - OpenAI API Key (with show/hide toggle)
  - Model selection (GPT-3.5 Turbo, GPT-4, GPT-4 Turbo)
  - System prompt customization
  - Temperature control (0-1 range)
  - Max tokens configuration
- Success/error notifications
- Settings persisted to WordPress options table

### 3. Listing Context Integration
- Automatically queries all `at_biz_dir` posts
- Extracts title and content from listings
- Includes listing data in AI context
- Cached for performance (1 hour TTL)
- Enables AI to answer questions about available listings

### 4. Security Features
- API keys encrypted before storage
- Permission checks on all REST endpoints
- Nonce verification for AJAX requests
- Input sanitization and validation
- Output escaping
- Masked API key display in admin

### 5. REST API Endpoints
- `GET /wp-json/directorist-smart-assistant/v1/settings` - Retrieve settings
- `POST /wp-json/directorist-smart-assistant/v1/settings` - Save settings
- `POST /wp-json/directorist-smart-assistant/v1/chat` - Handle chat messages
- `GET /wp-json/directorist-smart-assistant/v1/listings` - Get listings data

## Technical Architecture

### PHP Structure
- PSR-4 autoloading with Composer
- Namespace: `DirectoristSmartAssistant`
- Singleton pattern for main classes
- Dependency injection where appropriate
- Modern PHP 7.4+ features (typed properties, return types)

### React Architecture
- Built with @wordpress/scripts
- Uses @wordpress/element (React)
- WordPress components for UI consistency
- Separate entry points for admin and frontend
- Proper asset dependency management

### Build System
- Webpack configuration for multiple entry points
- Production and development builds
- Asset file generation for dependency tracking
- Minification and optimization

## File Structure

```
directorist-smart-assistant/
├── assets/
│   ├── build/              # Compiled assets (generated)
│   └── src/
│       ├── admin/         # Admin React app
│       └── chat-widget/   # Frontend chat widget
├── languages/              # Translation files
├── inc/
│   ├── Admin/             # Admin PHP classes
│   ├── Frontend/          # Frontend PHP classes
│   ├── REST_API/          # REST API endpoints
│   └── Settings/          # Settings management
├── composer.json          # PHP dependencies
├── package.json           # Node dependencies
├── webpack.config.js      # Build configuration
└── directorist-smart-assistant.php  # Main plugin file
```

## Usage Flow

1. **Admin Configuration**
   - Admin navigates to Directorist > Smart Assistant
   - Enters OpenAI API key and configures settings
   - Settings saved to database (API key encrypted)

2. **Frontend Chat**
   - Visitor clicks chat button
   - Types message in chat interface
   - Message sent to WordPress REST API
   - API retrieves listings context
   - API calls OpenAI with message + context
   - Response displayed in chat window

3. **Listing Context**
   - On chat request, system queries all listings
   - Listing data formatted as context string
   - Included in system prompt to OpenAI
   - AI can answer questions about listings

## Future Extensibility

The plugin is designed to be extensible:
- Easy to add new REST endpoints
- React components can be extended
- Settings structure allows new options
- OpenAI integration can be enhanced
- Ready for Pinecone integration (future)
- Ready for action triggers (future)

