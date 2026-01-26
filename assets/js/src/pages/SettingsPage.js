import React, { useState, useEffect } from 'react';
import { Typography, Box, Grid, Switch, FormControlLabel, Alert, Divider, TextField, Stack, FormHelperText, Skeleton, Button, CircularProgress, InputAdornment, Tooltip, IconButton, Link, Collapse } from '@mui/material';
import { CheckCircle, Error as ErrorIcon, Refresh } from '@mui/icons-material';
import { __, _x } from '@wordpress/i18n';
import { useAutoSaveForm } from '@flux-media-optimizer/hooks/useAutoSaveForm';
import { useOptions, useUpdateOptions } from '@flux-media-optimizer/hooks/useOptions';
import { useSystemStatus } from '@flux-media-optimizer/hooks/useSystemStatus';
import { SubscribeForm, SettingsSkeleton } from '@flux-media-optimizer/components';

/**
 * Settings page component with auto-save functionality
 */
const SettingsPage = () => {
  // Local state for immediate UI updates - completely decoupled from server state
  const [localSettings, setLocalSettings] = useState({});
  const [isInitialized, setIsInitialized] = useState(false);
  
  // React Query hooks for data fetching
  const { data: serverSettings, isLoading: optionsLoading, error: optionsError } = useOptions();
  const { data: systemStatus, isLoading: systemLoading, error: systemError } = useSystemStatus();
  const updateOptionsMutation = useUpdateOptions();

  // Auto-save hook - use local settings for immediate feedback
  const { debouncedSave, manualSave } = useAutoSaveForm('settings', localSettings);

  // Initialize local settings ONCE from server data on first load only
  // After initialization, local state is completely independent
  useEffect(() => {
    if (!isInitialized && serverSettings && typeof serverSettings === 'object' && Object.keys(serverSettings).length > 0) {
      setLocalSettings(serverSettings);
      setIsInitialized(true);
    }
  }, [serverSettings, isInitialized]);

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
    
    // Update local state immediately for instant UI feedback
    // This state is completely independent and won't be overwritten by server responses
    setLocalSettings(prev => ({
      ...prev,
      [key]: newValue
    }));
    
    // Trigger auto-save in the background
    // The UI already reflects the change, so no waiting for server response
    debouncedSave({ [key]: newValue });
  };


  const adminUrl = window.fluxMediaAdmin?.adminUrl || '/wp-admin/';
  const licenseUrl = `${adminUrl}admin.php?page=flux-suite-license`;

  // Determine if there are any errors
  const hasError = optionsError || systemError;
  const errorMessage = optionsError?.message || systemError?.message || __('Failed to load settings', 'flux-media-optimizer');
  
  // Check if data is still loading (only for initial load)
  const isLoading = !isInitialized && (optionsLoading || systemLoading);
  // Note: License check removed - license is now handled in the standalone License page
  // External service should check license via common library API
  const shouldEnableQualitySettings = !settings?.external_service_enabled;

  return (
    <>

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
                    checked={!!settings?.bulk_conversion_enabled}
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
                    checked={!!settings?.image_auto_convert}
                    disabled={isLoading}
                    onChange={handleSettingChange('image_auto_convert')}
                  />
                }
                label={__('Auto-convert images on upload', 'flux-media-optimizer')}
              />
              
              <FormControlLabel
                control={
                  <Switch
                    checked={!!settings?.image_hybrid_approach}
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
                        checked={settings?.image_formats?.includes('webp') || false}
                        disabled={isLoading || !isWebPSupported()}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings?.image_formats || []).filter(f => f !== 'webp'), 'webp']
                            : (settings?.image_formats || []).filter(f => f !== 'webp');
                          
                          // Update local state immediately
                          setLocalSettings(prev => ({
                            ...prev,
                            image_formats: newFormats
                          }));
                          
                          // Save in background
                          debouncedSave({ image_formats: newFormats });
                        }}
                      />
                    }
                    label={__('Enable WebP conversion', 'flux-media-optimizer')}
                  />
                  
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings?.image_formats?.includes('avif') || false}
                        disabled={isLoading || !isAVIFSupported()}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings?.image_formats || []).filter(f => f !== 'avif'), 'avif']
                            : (settings?.image_formats || []).filter(f => f !== 'avif');
                          
                          // Update local state immediately
                          setLocalSettings(prev => ({
                            ...prev,
                            image_formats: newFormats
                          }));
                          
                          // Save in background
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
                    checked={!!settings?.video_auto_convert}
                    disabled={isLoading}
                    onChange={handleSettingChange('video_auto_convert')}
                  />
                }
                label={__('Auto-convert videos on upload', 'flux-media-optimizer')}
              />
              
              <FormControlLabel
                control={
                  <Switch
                    checked={!!settings?.video_hybrid_approach}
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
                        checked={settings?.video_formats?.includes('av1') || false}
                        disabled={isLoading}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings?.video_formats || []).filter(f => f !== 'av1'), 'av1']
                            : (settings?.video_formats || []).filter(f => f !== 'av1');
                          
                          // Update local state immediately
                          setLocalSettings(prev => ({
                            ...prev,
                            video_formats: newFormats
                          }));
                          
                          // Save in background
                          debouncedSave({ video_formats: newFormats });
                        }}
                      />
                    }
                    label={__('Enable AV1 conversion', 'flux-media-optimizer')}
                  />
                  
                  <FormControlLabel
                    control={
                      <Switch
                        checked={settings?.video_formats?.includes('webm') || false}
                        disabled={isLoading}
                        onChange={(e) => {
                          const newFormats = e.target.checked 
                            ? [...(settings?.video_formats || []).filter(f => f !== 'webm'), 'webm']
                            : (settings?.video_formats || []).filter(f => f !== 'webm');
                          
                          // Update local state immediately
                          setLocalSettings(prev => ({
                            ...prev,
                            video_formats: newFormats
                          }));
                          
                          // Save in background
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
        {/* Hide when license is valid and CDN is enabled (external service handles quality) */}
        <Grid item xs={12} md={6}>
          <Collapse in={shouldEnableQualitySettings} timeout="auto" unmountOnExit>
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
                    value={settings?.image_webp_quality || 80}
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
                    value={settings?.image_avif_quality || 70}
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
                    value={settings?.image_avif_speed || 6}
                    disabled={isLoading || !isAVIFSupported()}
                    onChange={handleSettingChange('image_avif_speed')}
                    style={{ width: '100%' }}
                  />
                </Box>
              </Stack>
            </Box>
          </Collapse>
        </Grid>

        {/* Video Quality Settings - Right Column */}
        {/* Hide when license is valid and CDN is enabled (external service handles quality) */}
        <Grid item xs={12} md={6}>
          <Collapse in={shouldEnableQualitySettings} timeout="auto" unmountOnExit>
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
                    value={settings?.video_av1_crf || 28}
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
                    value={settings?.video_webm_crf || 30}
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
                    value={settings?.video_av1_cpu_used || 4}
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
                    value={settings?.video_webm_speed || 4}
                    disabled={isLoading}
                    onChange={handleSettingChange('video_webm_speed')}
                    style={{ width: '100%' }}
                  />
                </Box>
              </Stack>
            </Box>
          </Collapse>
        </Grid>

        {/* External Service Settings */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Typography variant="h5" gutterBottom>
              {__('External Service Settings', 'flux-media-optimizer')}
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              {__('Configure external service settings. A valid license key is required.', 'flux-media-optimizer')}{' '}
              {__('Manage your license in the', 'flux-media-optimizer')}{' '}
              <Link href={licenseUrl}>{__('License page', 'flux-media-optimizer')}</Link>.
            </Typography>
            <Stack spacing={2}>
              <FormControlLabel
                control={
                  <Switch
                    checked={settings?.external_service_enabled || false}
                    onChange={handleSettingChange('external_service_enabled')}
                    disabled={isLoading}
                  />
                }
                label={__('Enable CDN and External Processing', 'flux-media-optimizer')}
              />
              <FormHelperText sx={{ ml: 0, mt: 1 }}>
                {__('When enabled, all media files will be stored on the CDN. Images and videos will be processed and optimized by the external service, while other file types (PDFs, documents, etc.) will be stored directly for CDN delivery. Local processing will be disabled.', 'flux-media-optimizer')}
              </FormHelperText>
            </Stack>
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
    </>
  );
};

export default SettingsPage;
