import React, { useState, useEffect } from 'react';
import { Typography, Box, Grid, Switch, FormControlLabel, Alert, Divider, TextField, Stack, FormHelperText, Skeleton } from '@mui/material';
import { __, _x } from '@wordpress/i18n';
import { useAutoSaveForm } from '@flux-media-optimizer/hooks/useAutoSaveForm';
import { useOptions } from '@flux-media-optimizer/hooks/useOptions';
import { useSystemStatus } from '@flux-media-optimizer/hooks/useSystemStatus';
import { SubscribeForm, SettingsSkeleton } from '@flux-media-optimizer/components';

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
  const errorMessage = optionsError?.message || systemError?.message || __('Failed to load settings', 'flux-media-optimizer');
  
  // Check if data is still loading
  const isLoading = optionsLoading || systemLoading;

  return (
    <Box>

      {hasError && (
        <Alert severity="error" sx={{ mb: 3 }}>
          {errorMessage}
        </Alert>
      )}

      {isLoading ? (
        <SettingsSkeleton />
      ) : (
        <>
          {/* Format Support Alert */}
          {(!isWebPSupported() || !isAVIFSupported()) && (
            <Alert severity="warning" sx={{ mb: 3 }}>
              <Typography variant="body2">
                {!isWebPSupported() && !isAVIFSupported() 
                  ? __('Neither WebP nor AVIF conversion is supported by your server. Please install the required PHP extensions (GD or Imagick with WebP/AVIF support) to enable image optimization.', 'flux-media-optimizer')
                  : !isWebPSupported() 
                    ? __('WebP conversion is not supported by your server. Please install the required PHP extensions (GD or Imagick with WebP support) to enable WebP optimization.', 'flux-media-optimizer')
                    : __('AVIF conversion is not supported by your server. Please install Imagick with AVIF support to enable AVIF optimization.', 'flux-media-optimizer')
                }
              </Typography>
            </Alert>
          )}

          <Grid container spacing={3}>
        {/* General Settings - Full Width */}
        <Grid item xs={12}>
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('General Settings', 'flux-media-optimizer')}
            </Typography>
            <Stack spacing={2}>
              <FormControlLabel
                control={
                  <Switch
                    checked={settings?.bulk_conversion_enabled}
                    disabled={isLoading}
                    onChange={handleSettingChange('bulk_conversion_enabled')}
                  />
                }
                label={__('Enable bulk conversion', 'flux-media-optimizer')}
              /> 
              <FormHelperText>
                {__('Automatically convert existing media files in the background using WordPress cron.', 'flux-media-optimizer')}
              </FormHelperText>
            </Stack>
          </Box>
        </Grid>

        {/* Image Settings - Left Column */}
        <Grid item xs={12} md={6}>
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Image Settings', 'flux-media-optimizer')}
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
                label={__('Auto-convert images on upload', 'flux-media-optimizer')}
              />
              
              <FormControlLabel
                control={
                  <Switch
                    checked={settings?.image_hybrid_approach}
                    disabled={isLoading || (!isWebPSupported() && !isAVIFSupported())}
                    onChange={handleSettingChange('image_hybrid_approach')}
                  />
                }
                label={__('Image hybrid approach (experimental - use with caution)', 'flux-media-optimizer')}
              />
              <FormHelperText>
                {__('Creates both WebP and AVIF formats when supported by your server. Serves AVIF where supported (via <picture> tags or server detection), with WebP and the original image as fallback. This is the recommended approach for maximum performance and device compatibility. This is more dependent on theme and plugin compatibility than the native approach.', 'flux-media-optimizer')}
              </FormHelperText>

              {!settings?.image_hybrid_approach && (
                <>
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
                    label={__('Enable WebP conversion', 'flux-media-optimizer')}
                  />
                  
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
                    label={__('Enable AVIF conversion', 'flux-media-optimizer')}
                  />
                </>
              )}
            </Stack>
          </Box>
        </Grid>

        {/* Video Settings - Right Column */}
        <Grid item xs={12} md={6}>
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Video Settings', 'flux-media-optimizer')}
            </Typography>
            <Stack spacing={2}>
              <FormControlLabel
                control={
                  <Switch
                    checked={settings?.video_auto_convert}
                    disabled={isLoading}
                    onChange={handleSettingChange('video_auto_convert')}
                  />
                }
                label={__('Auto-convert videos on upload', 'flux-media-optimizer')}
              />
              
              <FormControlLabel
                control={
                  <Switch
                    checked={settings?.video_hybrid_approach}
                    disabled={isLoading}
                    onChange={handleSettingChange('video_hybrid_approach')}
                  />
                }
                label={__('Video hybrid approach (experimental - use with caution)', 'flux-media-optimizer')}
              />
              <FormHelperText>
                {__('Creates both AV1 and WebM formats when supported by your server. Serves AV1 where supported (via multiple <source> elements), with WebM and the original video as fallback. This is the recommended approach for maximum performance and device compatibility. This is more dependent on theme and plugin compatibility than the native approach.', 'flux-media-optimizer')}
              </FormHelperText>

              {!settings?.video_hybrid_approach && (
                <>
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings?.video_formats?.includes('av1')}
                        disabled={isLoading}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings?.video_formats || []).filter(f => f !== 'av1'), 'av1']
                            : (settings?.video_formats || []).filter(f => f !== 'av1');
                          
                          // Save only the video_formats field
                          debouncedSave({ video_formats: newFormats });
                        }}
                      />
                    }
                    label={__('Enable AV1 conversion', 'flux-media-optimizer')}
                  />
                  
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings?.video_formats?.includes('webm')}
                        disabled={isLoading}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings?.video_formats || []).filter(f => f !== 'webm'), 'webm']
                            : (settings?.video_formats || []).filter(f => f !== 'webm');
                          
                          // Save only the video_formats field
                          debouncedSave({ video_formats: newFormats });
                        }}
                      />
                    }
                    label={__('Enable WebM conversion', 'flux-media-optimizer')}
                  />
                </>
              )}
            </Stack>
          </Box>
        </Grid>

        {/* Image Quality Settings - Left Column */}
        <Grid item xs={12} md={6}>
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Image Quality Settings', 'flux-media-optimizer')}
            </Typography>
            <Stack spacing={3}>
              {/* WebP Quality */}
              <Box sx={{ opacity: isWebPSupported() ? 1 : 0.5 }}>
                <Typography variant="subtitle1" gutterBottom>
                  {__('WebP Quality', 'flux-media-optimizer')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media-optimizer')} {settings?.image_webp_quality}% ({__('Higher values produce larger files with better quality', 'flux-media-optimizer')})
                </Typography>
                <input
                  type="range"
                  min="1"
                  max="100"
                  value={settings?.image_webp_quality}
                  disabled={isLoading || !isWebPSupported()}
                  onChange={handleSettingChange('image_webp_quality')}
                  style={{ width: '100%' }}
                />
              </Box>

              {/* AVIF Quality */}
              <Box sx={{ opacity: isAVIFSupported() ? 1 : 0.5 }}>
                <Typography variant="subtitle1" gutterBottom>
                  {__('AVIF Quality', 'flux-media-optimizer')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media-optimizer')} {settings?.image_avif_quality}% ({__('AVIF typically needs lower quality for similar file size', 'flux-media-optimizer')})
                </Typography>
                <input
                  type="range"
                  min="0"
                  max="100"
                  value={settings?.image_avif_quality}
                  disabled={isLoading || !isAVIFSupported()}
                  onChange={handleSettingChange('image_avif_quality')}
                  style={{ width: '100%' }}
                />
              </Box>

              {/* AVIF Speed */}
              <Box sx={{ opacity: isAVIFSupported() ? 1 : 0.5 }}>
                <Typography variant="subtitle1" gutterBottom>
                  {__('AVIF Speed', 'flux-media-optimizer')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media-optimizer')} {settings?.image_avif_speed} ({__('Lower values = slower encoding but better compression', 'flux-media-optimizer')})
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
            </Stack>
          </Box>
        </Grid>

        {/* Video Quality Settings - Right Column */}
        <Grid item xs={12} md={6}>
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('Video Quality Settings', 'flux-media-optimizer')}
            </Typography>
            <Stack spacing={3}>
              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('AV1 CRF (Constant Rate Factor)', 'flux-media-optimizer')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media-optimizer')} {settings?.video_av1_crf} ({__('Lower values = higher quality, larger files', 'flux-media-optimizer')})
                </Typography>
                <input
                  type="range"
                  min="18"
                  max="50"
                  value={settings?.video_av1_crf}
                  disabled={isLoading}
                  onChange={handleSettingChange('video_av1_crf')}
                  style={{ width: '100%' }}
                />
              </Box>

              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('WebM CRF (Constant Rate Factor)', 'flux-media-optimizer')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media-optimizer')} {settings?.video_webm_crf} ({__('Lower values = higher quality, larger files', 'flux-media-optimizer')})
                </Typography>
                <input
                  type="range"
                  min="18"
                  max="50"
                  value={settings?.video_webm_crf}
                  disabled={isLoading}
                  onChange={handleSettingChange('video_webm_crf')}
                  style={{ width: '100%' }}
                />
              </Box>

              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('AV1 CPU Used', 'flux-media-optimizer')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media-optimizer')} {settings?.video_av1_cpu_used} ({__('Controls encoding speed vs file size. Lower values = slower encoding but smaller files at same quality (0-8)', 'flux-media-optimizer')})
                </Typography>
                <input
                  type="range"
                  min="0"
                  max="8"
                  value={settings?.video_av1_cpu_used}
                  disabled={isLoading}
                  onChange={handleSettingChange('video_av1_cpu_used')}
                  style={{ width: '100%' }}
                />
              </Box>

              <Box>
                <Typography variant="subtitle1" gutterBottom>
                  {__('WebM Speed', 'flux-media-optimizer')}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  {__('Current:', 'flux-media-optimizer')} {settings?.video_webm_speed} ({__('Controls encoding speed vs file size. Lower values = slower encoding but smaller files at same quality (0-9)', 'flux-media-optimizer')})
                </Typography>
                <input
                  type="range"
                  min="0"
                  max="9"
                  value={settings?.video_webm_speed}
                  disabled={isLoading}
                  onChange={handleSettingChange('video_webm_speed')}
                  style={{ width: '100%' }}
                />
              </Box>
            </Stack>
          </Box>
        </Grid>

        {/* License Settings */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('License Settings', 'flux-media-optimizer')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              {__('Enter your Flux Media Optimizer license key to unlock premium features and remove usage limits.', 'flux-media-optimizer')}
            </Typography>
            <TextField
              fullWidth
              label={__('License Key', 'flux-media-optimizer')}
              placeholder={__('Enter your license key', 'flux-media-optimizer')}
              value={settings?.license_key}
              disabled={isLoading}
              onChange={handleSettingChange('license_key')}
              variant="outlined"
              size="small"
              sx={{ maxWidth: 400 }}
              helperText={__('Your license key will be securely stored and used to validate premium features.', 'flux-media-optimizer')}
            />
          </Box>
        </Grid>

        {/* Newsletter Subscription */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <SubscribeForm />
        </Grid>

          </Grid>
        </>
      )}
    </Box>
  );
};

export default SettingsPage;
