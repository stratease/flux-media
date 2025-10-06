import React, { useState, useEffect } from 'react';
import { Typography, Box, Grid, Switch, FormControlLabel, Alert, Divider, TextField, Stack } from '@mui/material';
import { __, _x } from '@wordpress/i18n';
import { useAutoSaveForm } from '@flux-media/hooks/useAutoSaveForm';
import { AutoSaveStatus } from '@flux-media/contexts/AutoSaveContext';
import { apiService } from '@flux-media/services/api';

/**
 * Settings page component with auto-save functionality
 */
const SettingsPage = () => {
  const [settings, setSettings] = useState({
    image_auto_convert: true,
    image_webp_quality: 85,
    image_formats: ['webp', 'avif'],
    hybrid_approach: true,
    video_formats: ['av1', 'webm'],
    license_key: '',
    enable_logging: true,
  });

  const [error, setError] = useState(null);

  // Auto-save hook
  const { debouncedSave, manualSave } = useAutoSaveForm('settings', settings);

  // Load initial settings
  useEffect(() => {
    const loadSettings = async () => {
      try {
        const response = await apiService.getOptions();
        // Use backend field names directly
        if (response && typeof response === 'object') {
          setSettings(prev => ({
            ...prev,
            ...response,
          }));
        }
      } catch (err) {
        console.error('Failed to load settings:', err);
        setError(__('Failed to load settings', 'flux-media'));
      }
    };

    loadSettings();
  }, []);

  const handleSettingChange = (key) => (event) => {
    const newValue = event.target.checked !== undefined ? event.target.checked : event.target.value;
    const newSettings = {
      ...settings,
      [key]: newValue
    };
    
    setSettings(newSettings);
    setError(null);
    
    // Trigger auto-save
    debouncedSave(newSettings);
  };

  return (
    <Box>
      <Grid container justifyContent="space-between" alignItems="flex-start" sx={{ mb: 4 }}>
        <Grid item>
          <Typography variant="h3" component="h1" gutterBottom>
            {__('Flux Media Settings', 'flux-media')}
          </Typography>
          <Typography variant="subtitle1" color="text.secondary">
            {__('Configure your image and video optimization preferences', 'flux-media')}
          </Typography>
        </Grid>
        <Grid item>
          <AutoSaveStatus saveKey="settings" />
        </Grid>
      </Grid>

      {error && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {error}
        </Alert>
      )}

      <Grid container spacing={3}>
        {/* General Settings */}
        <Grid item xs={12} md={6}>
                  <Box>
                    <Typography variant="h5" gutterBottom>
                      {__('General Settings', 'flux-media')}
                    </Typography>
                    <Stack spacing={2}>
                      <FormControlLabel
                        control={
                          <Switch
                            checked={settings.image_auto_convert}
                            onChange={handleSettingChange('image_auto_convert')}
                          />
                        }
                        label={__('Auto-convert on upload', 'flux-media')}
                      />
                      
                      <FormControlLabel
                        control={
                          <Switch
                            checked={settings.hybrid_approach}
                            onChange={handleSettingChange('hybrid_approach')}
                          />
                        }
                        label={__('Hybrid approach (WebP + AVIF)', 'flux-media')}
                      />
              
              {!settings.hybrid_approach && (
                <>
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings.image_formats?.includes('webp')}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings.image_formats || []).filter(f => f !== 'webp'), 'webp']
                            : (settings.image_formats || []).filter(f => f !== 'webp');
                          handleSettingChange('image_formats')({ target: { value: newFormats } });
                        }}
                      />
                    }
                    label={__('Enable WebP conversion', 'flux-media')}
                  />
                  
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings.image_formats?.includes('avif')}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings.image_formats || []).filter(f => f !== 'avif'), 'avif']
                            : (settings.image_formats || []).filter(f => f !== 'avif');
                          handleSettingChange('image_formats')({ target: { value: newFormats } });
                        }}
                      />
                    }
                    label={__('Enable AVIF conversion', 'flux-media')}
                  />
                </>
              )}
                    </Stack>
            
            {settings.hybrid_approach && (
              <Box sx={{ mt: 2, p: 2, bgcolor: 'info.light', borderRadius: 1 }}>
                <Typography variant="body2" color="info.contrastText">
                  <strong>{__('Hybrid Approach:', 'flux-media')}</strong> {__('Creates both WebP and AVIF formats. Serves AVIF where supported (via <picture> tags or server detection), with WebP as fallback. This is the recommended approach for maximum performance and compatibility.', 'flux-media')}
                </Typography>
              </Box>
            )}
          </Box>
        </Grid>

        {/* Video Settings */}
        <Grid item xs={12} md={6}>
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Video Settings', 'flux-media')}
            </Typography>
            <Stack spacing={2}>
              <FormControlLabel
                control={
                  <Switch
                    checked={settings.video_formats?.includes('av1')}
                    onChange={(e) => {
                      const newFormats = e.target.checked 
                        ? [...(settings.video_formats || []).filter(f => f !== 'av1'), 'av1']
                        : (settings.video_formats || []).filter(f => f !== 'av1');
                      handleSettingChange('video_formats')({ target: { value: newFormats } });
                    }}
                  />
                }
                label={__('Enable AV1 conversion', 'flux-media')}
              />
              
              <FormControlLabel
                control={
                  <Switch
                    checked={settings.video_formats?.includes('webm')}
                    onChange={(e) => {
                      const newFormats = e.target.checked 
                        ? [...(settings.video_formats || []).filter(f => f !== 'webm'), 'webm']
                        : (settings.video_formats || []).filter(f => f !== 'webm');
                      handleSettingChange('video_formats')({ target: { value: newFormats } });
                    }}
                  />
                }
                label={__('Enable WebM conversion', 'flux-media')}
              />
            </Stack>
          </Box>
        </Grid>

        {/* Quality Settings */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Quality Settings', 'flux-media')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              {__('Quality:', 'flux-media')} {settings.image_webp_quality}% ({__('Higher values produce larger files with better quality', 'flux-media')})
            </Typography>
            <input
              type="range"
              min="60"
              max="100"
              value={settings.image_webp_quality}
              onChange={handleSettingChange('image_webp_quality')}
              style={{ width: '100%' }}
            />
          </Box>
        </Grid>

        {/* License Settings */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('License Settings', 'flux-media')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              {__('Enter your Flux Media license key to unlock premium features and remove usage limits.', 'flux-media')}
            </Typography>
            <TextField
              fullWidth
              label={__('License Key', 'flux-media')}
              placeholder={__('Enter your license key', 'flux-media')}
              value={settings.license_key}
              onChange={handleSettingChange('license_key')}
              variant="outlined"
              size="small"
              sx={{ maxWidth: 400 }}
              helperText={__('Your license key will be securely stored and used to validate premium features.', 'flux-media')}
            />
          </Box>
        </Grid>

        {/* System Settings */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('System Settings', 'flux-media')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              {__('Configure system-level settings for Flux Media.', 'flux-media')}
            </Typography>
            <Stack spacing={2}>
              <FormControlLabel
                control={
                  <Switch
                    checked={settings.enable_logging}
                    onChange={handleSettingChange('enable_logging')}
                  />
                }
                label={__('Enable logging', 'flux-media')}
              />
              <Typography variant="caption" color="text.secondary">
                {__('When enabled, Flux Media will log errors, warnings, and system information to help with troubleshooting. Disable this in production environments if you prefer not to store logs.', 'flux-media')}
              </Typography>
            </Stack>
          </Box>
        </Grid>

      </Grid>
    </Box>
  );
};

export default SettingsPage;
