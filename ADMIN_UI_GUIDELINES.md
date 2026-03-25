# Admin UI Design Guidelines

## Overview

All admin settings tabs in **Directorist - AI Agents** now use a unified CSS design system. This ensures consistency across all current and future settings pages while allowing for custom styling when needed.

## Common CSS Classes

### Card Container
```jsx
<div className="daia-admin-card">
  {/* Your content */}
</div>
```

### Sections
```jsx
<div className="daia-admin-section">
  <h2>Section Title</h2>
  <p>Optional description text</p>
  
  {/* Fields go here */}
</div>
```

### Fields
```jsx
<div className="daia-admin-field">
  <TextControl label="Field Label" value={value} onChange={onChange} />
</div>
```

### Field Groups (Side-by-Side)
```jsx
<div className="daia-admin-field-group">
  <div className="daia-admin-field">
    <TextControl label="Left Field" />
  </div>
  <div className="daia-admin-field">
    <TextControl label="Right Field" />
  </div>
</div>
```

### Actions (Buttons)
```jsx
<div className="daia-admin-actions">
  <Button isSecondary>Reset</Button>
  <Button isPrimary>Save Settings</Button>
</div>
```

## Special Field Variants

### Highlighted API Key Field
```jsx
<div className="daia-admin-field daia-admin-field--api-key">
  <TextControl 
    label="API Key" 
    type="password"
    value={apiKey}
    onChange={setApiKey}
  />
</div>
```

### Highlighted URL Field
```jsx
<div className="daia-admin-field daia-admin-field--api-url">
  <TextControl 
    label="API URL" 
    value={apiUrl}
    onChange={setApiUrl}
  />
</div>
```

### Highlighted Website ID Field
```jsx
<div className="daia-admin-field daia-admin-field--website-id">
  <TextControl 
    label="Website ID" 
    value={websiteId}
    onChange={setWebsiteId}
  />
</div>
```

## Complete Example

```jsx
import { TextControl, SelectControl, ToggleControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const MyNewSettingsTab = ({ settings, onChange }) => {
  return (
    <div className="daia-admin-card">
      <div className="daia-admin-section">
        <h2>{__('General Settings', 'directorist-ai-agents')}</h2>
        <p>{__('Configure the main settings for this feature.', 'directorist-ai-agents')}</p>
        
        <div className="daia-admin-field">
          <TextControl
            label={__('Feature Name', 'directorist-ai-agents')}
            value={settings.featureName || ''}
            onChange={(value) => onChange({ featureName: value })}
          />
        </div>
        
        <div className="daia-admin-field">
          <ToggleControl
            label={__('Enable Feature', 'directorist-ai-agents')}
            checked={settings.enableFeature || false}
            onChange={(value) => onChange({ enableFeature: value })}
          />
        </div>
      </div>
      
      <div className="daia-admin-section">
        <h2>{__('Advanced Options', 'directorist-ai-agents')}</h2>
        
        <div className="daia-admin-field-group">
          <div className="daia-admin-field">
            <SelectControl
              label={__('Mode', 'directorist-ai-agents')}
              value={settings.mode || 'auto'}
              options={[
                { label: 'Auto', value: 'auto' },
                { label: 'Manual', value: 'manual' }
              ]}
              onChange={(value) => onChange({ mode: value })}
            />
          </div>
          
          <div className="daia-admin-field">
            <TextControl
              label={__('Timeout (seconds)', 'directorist-ai-agents')}
              type="number"
              value={settings.timeout || '30'}
              onChange={(value) => onChange({ timeout: value })}
            />
          </div>
        </div>
      </div>
      
      <div className="daia-admin-actions">
        <Button isPrimary>{__('Save Settings', 'directorist-ai-agents')}</Button>
      </div>
    </div>
  );
};

export default MyNewSettingsTab;
```

## Alternative: Use Your Own Naming Pattern

If you prefer, you can use your own class naming pattern (e.g., `.my-feature-setup__section`, `.my-feature-setup__field`), and the common styles will automatically apply. This is what the existing components do:

- `VectorStorageSetup` uses `.vector-storage-setup__section`, `.vector-storage-setup__field`
- `ChatAgentSetup` uses `.chat-agent-setup__section`, `.chat-agent-setup__field`
- `ChatModuleSettings` uses `.chat-module-settings__section`, `.chat-module-settings__field`

All of these inherit from the common styles defined in `assets/src/admin/style.css`.

## Design Specifications

- **Card Background**: White with subtle border and shadow
- **Section Padding**: 32px (24px on mobile)
- **Section Border**: 1.5px solid #e5e7eb (between sections)
- **H2 Title**: 20px, bold, with gradient vertical bar accent
- **Field Margin**: 28px bottom spacing
- **Input Border**: 1.5px solid #e5e7eb
- **Focus State**: #667eea border with subtle shadow
- **Primary Button**: Purple gradient (135deg, #667eea to #764ba2)
- **Button Height**: 42px minimum
- **Button Padding**: 10px 24px
- **Hover Effect**: Subtle lift (translateY) with enhanced shadow
- **Mobile Breakpoint**: 782px

## Notes

- All components are fully responsive
- The design matches the professional, clean aesthetic of the Vector Storage tab
- Future tabs will automatically inherit this design
- Custom modifications can be added without breaking the base styles
