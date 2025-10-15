import React, { useState, useEffect } from 'react';
import { Typography, Box, Grid, Switch, FormControlLabel, Alert, Divider, TextField, Stack, Tooltip } from '@mui/material';
import { __, _x } from '@wordpress/i18n';
import { useAutoSaveForm } from '@flux-media/hooks/useAutoSaveForm';
import { useOptions } from '@flux-media/hooks/useOptions';
import { useSystemStatus } from '@flux-media/hooks/useSystemStatus';

/**
 * Settings page component with auto-save functionality
 */
const SettingsPage = () => {
  // Local state for immediate UI updates
  const [localSettings, setLocalSettings] = useState({});
  
  // React Query hooks for data fetching
  const { data: serverSettings, isLoading: optionsLoading, error: optionsError } = useOptions();
  const { data: systemStatus, isLoading: systemLoading, error: systemError } = useSystemStatus();

  // Auto-save hook - use local settings for immediate feedback
  const { debouncedSave, manualSave } = useAutoSaveForm('settings', localSettings);

  // Update local settings when server data changes
  useEffect(() => {
    if (serverSettings && typeof serverSettings === 'object') {
      setLocalSettings(prev => ({
        ...prev,
        ...serverSettings,
      }));
    }
  }, [serverSettings]);

  // Use local settings for display (immediate updates)
  const settings = localSettings;

  // Helper functions to check format support
  const isWebPSupported = () => {
    return systemStatus?.imageProcessor?.webp_support === true;
  };

  const isAVIFSupported = () => {
    return systemStatus?.imageProcessor?.avif_support === true;
  };

  const handleSettingChange = (key) => (event) => {
    let newValue;
    
    if (event.target.type === 'range') {
      // Handle range inputs - convert to number
      newValue = parseInt(event.target.value, 10);
    } else if (event.target.type === 'checkbox' || event.target.type === 'switch') {
      // Handle checkboxes/switches
      newValue = event.target.checked;
    } else {
      // Handle text inputs and other types
      newValue = event.target.value;
    }
    
    // Immediately update local state for instant UI feedback
    setLocalSettings(prev => ({
      ...prev,
      [key]: newValue
    }));
    
    // Trigger auto-save with only the single field that changed
    // This happens in the background while UI is already updated
    debouncedSave({ [key]: newValue });
  };

  // Determine if there are any errors
  const hasError = optionsError || systemError;
  const errorMessage = optionsError?.message || systemError?.message || __('Failed to load settings', 'flux-media');
  
  // Check if data is still loading
  const isLoading = optionsLoading || systemLoading;

  return (
    <Box>
      <Grid container justifyContent="space-between" alignItems="flex-start" sx={{ mb: 4 }}>
        <Grid item>
          <Typography variant="h3" component="h1" gutterBottom>
            {__('Flux Media Settings', 'flux-media')}
          </Typography>
          <Typography variant="subtitle1" color="text.secondary">
            {__('Configure your image optimization preferences', 'flux-media')}
          </Typography>
        </Grid>
      </Grid>

      {hasError && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {errorMessage}
        </Alert>
      )}

      {isLoading && (
        <Alert severity="info" sx={{ mb: 3 }}>
          {__('Loading settings...', 'flux-media')}
        </Alert>
      )}

      <Grid container spacing={3}>
        {/* General Settings */}
        <Grid item xs={12} md={6}>
          <Divider sx={{ mb: 3 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('General Settings', 'flux-media')}
            </Typography>
            <Stack spacing={2}>
              <FormControlLabel
                control={
                  <Switch
                    checked={settings?.image_auto_convert}
                    disabled={isLoading}
                    onChange={handleSettingChange('image_auto_convert')}
                  />
                }
                label={__('Auto-convert on upload', 'flux-media')}
              />
              
              <FormControlLabel
                control={
                  <Switch
                    checked={settings?.bulk_conversion_enabled}
                    disabled={isLoading}
                    onChange={handleSettingChange('bulk_conversion_enabled')}
                  />
                }
                label={__('Enable bulk conversion', 'flux-media')}
              />
              <Typography variant="body2" color="text.secondary" sx={{ ml: 4, mb: 1 }}>
                {__('Automatically convert existing media files in the background using WordPress cron.', 'flux-media')}
              </Typography>
              
              <Tooltip 
                title={!isWebPSupported() && !isAVIFSupported() ? __('Neither WebP nor AVIF conversion is supported by your server. Please install the required PHP extensions.', 'flux-media') : ''}
                disableHoverListener={isWebPSupported() || isAVIFSupported()}
              >
                <FormControlLabel
                  control={
                    <Switch
                      checked={settings?.hybrid_approach}
                      disabled={isLoading || (!isWebPSupported() && !isAVIFSupported())}
                      onChange={handleSettingChange('hybrid_approach')}
                    />
                  }
                  label={__('Hybrid approach (WebP + AVIF)', 'flux-media')}
                />
              </Tooltip>

              {!settings?.hybrid_approach && (
                <>
                  <Tooltip 
                    title={!isWebPSupported() ? __('WebP conversion is not supported by your server. Please install the required PHP extensions (GD or Imagick with WebP support).', 'flux-media') : ''}
                    disableHoverListener={isWebPSupported()}
                  >
                    <FormControlLabel
                      control={
                        <Switch
                          checked={settings?.image_formats?.includes('webp')}
                          disabled={isLoading || !isWebPSupported()}
                          onChange={(e) => {
                            const newFormats = e.target.checked 
                              ? [...(settings?.image_formats || []).filter(f => f !== 'webp'), 'webp']
                              : (settings?.image_formats || []).filter(f => f !== 'webp');
                            
                            // Save only the image_formats field
                            // React Query will handle updating the cache after successful save
                            debouncedSave({ image_formats: newFormats });
                          }}
                        />
                      }
                      label={__('Enable WebP conversion', 'flux-media')}
                    />
                  </Tooltip>
                  
                  <Tooltip 
                    title={!isAVIFSupported() ? __('AVIF conversion is not supported by your server. Please install Imagick with AVIF support.', 'flux-media') : ''}
                    disableHoverListener={isAVIFSupported()}
                  >
                    <FormControlLabel
                      control={
                        <Switch
                          checked={settings?.image_formats?.includes('avif')}
                          disabled={isLoading || !isAVIFSupported()}
                          onChange={(e) => {
                            const newFormats = e.target.checked 
                              ? [...(settings?.image_formats || []).filter(f => f !== 'avif'), 'avif']
                              : (settings?.image_formats || []).filter(f => f !== 'avif');
                            
                            // Save only the image_formats field
                            // React Query will handle updating the cache after successful save
                            debouncedSave({ image_formats: newFormats });
                          }}
                        />
                      }
                      label={__('Enable AVIF conversion', 'flux-media')}
                    />
                  </Tooltip>
                </>
              )}
            </Stack>
            
            {settings?.hybrid_approach && (
              <Box sx={{ mt: 2, p: 2, bgcolor: 'info.light', borderRadius: 1 }}>
                <Typography variant="body2" color="info.contrastText">
                  <strong>{__('Hybrid Approach:', 'flux-media')}</strong> {__('Creates both WebP and AVIF formats. Serves AVIF where supported (via <picture> tags or server detection), with WebP as fallback. This is the recommended approach for maximum performance and compatibility.', 'flux-media')}
                </Typography>
              </Box>
            )}
          </Box>
        </Grid>

        {/* Video Settings - Hidden for MVP */}
        {/* <Grid item xs={12} md={6}>
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
        </Grid> */}

        {/* Image Quality Settings */}
        <Grid item xs={12} md={6}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Image Quality Settings', 'flux-media')}
            </Typography>
            <Stack spacing={3}>
              {/* WebP Quality */}
              <Tooltip 
                title={!isWebPSupported() ? __('WebP quality settings are disabled because WebP conversion is not supported by your server.', 'flux-media') : ''}
                disableHoverListener={isWebPSupported()}
              >
                <Box sx={{ opacity: isWebPSupported() ? 1 : 0.5 }}>
                  <Typography variant="subtitle1" gutterBottom>
                    {__('WebP Quality', 'flux-media')}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                    {__('Current:', 'flux-media')} {settings?.image_webp_quality}% ({__('Higher values produce larger files with better quality', 'flux-media')})
                  </Typography>
                  <input
                    type="range"
                    min="60"
                    max="100"
                    value={settings?.image_webp_quality}
                    disabled={isLoading || !isWebPSupported()}
                    onChange={handleSettingChange('image_webp_quality')}
                    style={{ width: '100%' }}
                  />
                </Box>
              </Tooltip>

              {/* AVIF Quality */}
              <Tooltip 
                title={!isAVIFSupported() ? __('AVIF quality settings are disabled because AVIF conversion is not supported by your server.', 'flux-media') : ''}
                disableHoverListener={isAVIFSupported()}
              >
                <Box sx={{ opacity: isAVIFSupported() ? 1 : 0.5 }}>
                  <Typography variant="subtitle1" gutterBottom>
                    {__('AVIF Quality', 'flux-media')}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                    {__('Current:', 'flux-media')} {settings?.image_avif_quality}% ({__('AVIF typically needs lower quality for similar file size', 'flux-media')})
                  </Typography>
                  <input
                    type="range"
                    min="50"
                    max="90"
                    value={settings?.image_avif_quality}
                    disabled={isLoading || !isAVIFSupported()}
                    onChange={handleSettingChange('image_avif_quality')}
                    style={{ width: '100%' }}
                  />
                </Box>
              </Tooltip>

              {/* AVIF Speed */}
              <Tooltip 
                title={!isAVIFSupported() ? __('AVIF speed settings are disabled because AVIF conversion is not supported by your server.', 'flux-media') : ''}
                disableHoverListener={isAVIFSupported()}
              >
                <Box sx={{ opacity: isAVIFSupported() ? 1 : 0.5 }}>
                  <Typography variant="subtitle1" gutterBottom>
                    {__('AVIF Speed', 'flux-media')}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                    {__('Current:', 'flux-media')} {settings?.image_avif_speed} ({__('Lower values = slower encoding but better compression', 'flux-media')})
                  </Typography>
                  <input
                    type="range"
                    min="0"
                    max="10"
                    value={settings?.image_avif_speed}
                    disabled={isLoading || !isAVIFSupported()}
                    onChange={handleSettingChange('image_avif_speed')}
                    style={{ width: '100%' }}
                  />
                </Box>
              </Tooltip>
            </Stack>
          </Box>
        </Grid>

        {/* Video Quality Settings - Hidden for MVP */}
        {/*
        <Grid item xs={12} md={6}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Video Quality Settings', 'flux-media')}
            </Typography>
            <Stack spacing={3}>
              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('AV1 CRF (Constant Rate Factor)', 'flux-media')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media')} {settings.video_av1_crf} ({__('Lower values = higher quality, larger files', 'flux-media')})
                </Typography>
                <input
                  type="range"
                  min="18"
                  max="50"
                  value={settings.video_av1_crf}
                  onChange={handleSettingChange('video_av1_crf')}
                  style={{ width: '100%' }}
                />
              </Box>

              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('WebM CRF (Constant Rate Factor)', 'flux-media')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media')} {settings.video_webm_crf} ({__('Lower values = higher quality, larger files', 'flux-media')})
                </Typography>
                <input
                  type="range"
                  min="18"
                  max="50"
                  value={settings.video_webm_crf}
                  onChange={handleSettingChange('video_webm_crf')}
                  style={{ width: '100%' }}
                />
              </Box>

              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('AV1 Preset', 'flux-media')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media')} {settings.video_av1_preset} ({__('Faster presets = larger files, slower presets = smaller files', 'flux-media')})
                </Typography>
                <select
                  value={settings.video_av1_preset}
                  onChange={handleSettingChange('video_av1_preset')}
                  style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ccc' }}
                >
                  <option value="ultrafast">{__('Ultrafast', 'flux-media')}</option>
                  <option value="superfast">{__('Superfast', 'flux-media')}</option>
                  <option value="veryfast">{__('Veryfast', 'flux-media')}</option>
                  <option value="faster">{__('Faster', 'flux-media')}</option>
                  <option value="fast">{__('Fast', 'flux-media')}</option>
                  <option value="medium">{__('Medium', 'flux-media')}</option>
                  <option value="slow">{__('Slow', 'flux-media')}</option>
                  <option value="slower">{__('Slower', 'flux-media')}</option>
                  <option value="veryslow">{__('Veryslow', 'flux-media')}</option>
                </select>
              </Box>

              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('WebM Preset', 'flux-media')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media')} {settings.video_webm_preset} ({__('Faster presets = larger files, slower presets = smaller files', 'flux-media')})
                </Typography>
                <select
                  value={settings.video_webm_preset}
                  onChange={handleSettingChange('video_webm_preset')}
                  style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ccc' }}
                >
                  <option value="ultrafast">{__('Ultrafast', 'flux-media')}</option>
                  <option value="superfast">{__('Superfast', 'flux-media')}</option>
                  <option value="veryfast">{__('Veryfast', 'flux-media')}</option>
                  <option value="faster">{__('Faster', 'flux-media')}</option>
                  <option value="fast">{__('Fast', 'flux-media')}</option>
                  <option value="medium">{__('Medium', 'flux-media')}</option>
                  <option value="slow">{__('Slow', 'flux-media')}</option>
                  <option value="slower">{__('Slower', 'flux-media')}</option>
                  <option value="veryslow">{__('Veryslow', 'flux-media')}</option>
                </select>
              </Box>
            </Stack>
          </Box>
        </Grid>
        */}

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
              value={settings?.license_key}
              disabled={isLoading}
              onChange={handleSettingChange('license_key')}
              variant="outlined"
              size="small"
              sx={{ maxWidth: 400 }}
              helperText={__('Your license key will be securely stored and used to validate premium features.', 'flux-media')}
            />
          </Box>
        </Grid>


      </Grid>
    </Box>
  );
};

export default SettingsPage;
