import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Typography, Box, Grid, Switch, FormControlLabel, Alert, Divider, TextField, Stack, FormHelperText, Skeleton, Button, CircularProgress, InputAdornment, Tooltip, IconButton, Link } from '@mui/material';
import { CheckCircle, Error as ErrorIcon, Refresh } from '@mui/icons-material';
import { __, _x } from '@wordpress/i18n';
import { useAutoSaveForm } from '@flux-media-optimizer/hooks/useAutoSaveForm';
import { useOptions, useUpdateOptions } from '@flux-media-optimizer/hooks/useOptions';
import { useSystemStatus } from '@flux-media-optimizer/hooks/useSystemStatus';
import { useLicense, useActivateLicense, useValidateLicense } from '@flux-media-optimizer/hooks/useLicense';
import { SubscribeForm, SettingsSkeleton } from '@flux-media-optimizer/components';

/**
 * Settings page component with auto-save functionality
 */
const SettingsPage = () => {
  // Local state for immediate UI updates
  const [localSettings, setLocalSettings] = useState({});
  const [licenseKey, setLicenseKey] = useState('');
  const [licenseActivationError, setLicenseActivationError] = useState(null);
  
  // React Query hooks for data fetching
  const { data: serverSettings, isLoading: optionsLoading, error: optionsError } = useOptions();
  const { data: systemStatus, isLoading: systemLoading, error: systemError } = useSystemStatus();
  const { data: licenseData, isLoading: licenseLoading, error: licenseError } = useLicense();
  const updateOptionsMutation = useUpdateOptions();
  const activateLicenseMutation = useActivateLicense();
  const validateLicenseMutation = useValidateLicense();

  // Auto-save hook - use local settings for immediate feedback
  const { debouncedSave, manualSave } = useAutoSaveForm('settings', localSettings);
  
  // Debounce timer for license activation
  const debounceTimerRef = useRef(null);

  // Update local settings when server data changes
  useEffect(() => {
    if (serverSettings && typeof serverSettings === 'object') {
      setLocalSettings(prev => ({
        ...prev,
        ...serverSettings,
      }));
    }
  }, [serverSettings]);

  // Update license key when license data changes (initial load only)
  useEffect(() => {
    if (licenseData && typeof licenseData === 'object') {
      if (licenseData.license_key !== undefined && licenseKey === '') {
        // Only set on initial load when field is empty
        setLicenseKey(licenseData.license_key || '');
      }
    }
  }, [licenseData, licenseKey]);

  // Debounced license activation function
  const debouncedActivateLicense = useCallback((key) => {
    // Clear any existing timer
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }
    
    // Set new timer
    debounceTimerRef.current = setTimeout(() => {
      const trimmedKey = key.trim();
      // Activate if key is not empty
      if (trimmedKey) {
        activateLicenseMutation.mutate(trimmedKey);
      }
    }, 1000); // 1 second debounce
  }, [activateLicenseMutation]);

  // Cleanup debounce timer on unmount
  useEffect(() => {
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, []);
  
  // Monitor license activation mutation for errors
  useEffect(() => {
    if (activateLicenseMutation.isError) {
      const error = activateLicenseMutation.error;
      const errorData = error?.data || {};
      const errorCode = errorData.error_code || error?.code || 'unknown_error';
      const errorMessage = errorData.message || error?.message || __('License activation failed', 'flux-media-optimizer');
      
      setLicenseActivationError({
        success: false,
        error: errorCode,
        message: errorMessage,
      });
    } else if (activateLicenseMutation.isSuccess) {
      // Clear error on success
      setLicenseActivationError(null);
    }
  }, [activateLicenseMutation.isError, activateLicenseMutation.isSuccess, activateLicenseMutation.error]);

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

  const handleLicenseKeyChange = (event) => {
    const newLicenseKey = event.target.value;
    setLicenseKey(newLicenseKey);
    
    // Trigger debounced activation on change
    if (newLicenseKey.trim()) {
      debouncedActivateLicense(newLicenseKey);
    } else {
      // Clear debounce timer if field is empty
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    }
  };

  const handleRevalidateLicense = async () => {
    if (!licenseKey) {
      return;
    }
    
    // First activate the license to ensure it's activated
    try {
      await activateLicenseMutation.mutateAsync(licenseKey);
      // After activation succeeds, validate the license
      await validateLicenseMutation.mutateAsync();
    } catch (error) {
      // Error handling is done by the mutation hooks
      // If activation fails, we still try to validate
      await validateLicenseMutation.mutateAsync();
    }
  };

  // Format date for display using WordPress date formatting
  const formatLicenseDate = (dateString) => {
    if (!dateString) {
      return null;
    }
    
    try {
      // Use WordPress date formatting if available
      if (window.wp?.date?.dateI18n) {
        // WordPress date format from settings
        const dateFormat = window.wp?.date?.settings?.formats?.date || 'F j, Y';
        const timeFormat = window.wp?.date?.settings?.formats?.time || 'g:i a';
        const format = `${dateFormat} ${timeFormat}`;
        
        // Parse the GMT date and format it
        const date = new Date(dateString + ' UTC');
        return window.wp.date.dateI18n(format, date);
      } else {
        // Fallback to JavaScript date formatting
        const date = new Date(dateString + ' UTC');
        return date.toLocaleString();
      }
    } catch (e) {
      // Fallback to simple date string
      return dateString;
    }
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

      {licenseActivationError && (
        <Alert 
          severity="error" 
          sx={{ mb: 3 }}
          onClose={() => setLicenseActivationError(null)}
        >
          <Typography variant="body2" component="div">
            <strong>{__('License Activation Failed', 'flux-media-optimizer')}</strong>
            <Typography variant="body2" component="div" sx={{ mt: 0.5 }}>
              {licenseActivationError.formatted_message || licenseActivationError.message || __('An error occurred while activating your license. Please check your license key and try again.', 'flux-media-optimizer')}
            </Typography>
          </Typography>
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
              {__('Enter your Flux Plugins license key.', 'flux-media-optimizer')}{' '}
              <Link
                href="https://fluxplugins.com"
                target="_blank"
                rel="noopener noreferrer"
                sx={{ textDecoration: 'none' }}
              >
                {__('Purchase a license.', 'flux-media-optimizer')}
              </Link>
            </Typography>
            <Grid container spacing={2} alignItems="flex-start">
              <Grid item xs={12} md={6}>
                <Stack spacing={2}>
                  <Box sx={{ display: 'flex', gap: 1, alignItems: 'flex-start' }}>
                    <TextField
                      fullWidth
                      label={__('License Key', 'flux-media-optimizer')}
                      placeholder={__('Enter your license key', 'flux-media-optimizer')}
                      value={licenseKey}
                      disabled={isLoading || activateLicenseMutation.isPending}
                      onChange={handleLicenseKeyChange}
                      variant="outlined"
                      size="small"
                      InputProps={{
                        endAdornment: (
                          <InputAdornment position="end">
                            <Stack direction="row" spacing={0.5} alignItems="center">
                              {activateLicenseMutation.isPending || validateLicenseMutation.isPending ? (
                                <CircularProgress size={20} />
                              ) : licenseKey && licenseData?.license_is_valid ? (
                                <CheckCircle color="success" sx={{ fontSize: 20 }} />
                              ) : licenseKey && licenseData?.license_is_valid === false ? (
                                <ErrorIcon color="error" sx={{ fontSize: 20 }} />
                              ) : null}
                              {licenseKey && (
                                <Tooltip title={__('Revalidate license', 'flux-media-optimizer')}>
                                  <IconButton
                                    size="small"
                                    onClick={handleRevalidateLicense}
                                    disabled={isLoading || licenseLoading || activateLicenseMutation.isPending || validateLicenseMutation.isPending || !licenseKey}
                                    sx={{ ml: 0.5 }}
                                  >
                                    <Refresh fontSize="small" />
                                  </IconButton>
                                </Tooltip>
                              )}
                            </Stack>
                          </InputAdornment>
                        ),
                      }}
                    />
                  </Box>
                  {licenseKey && licenseData?.license_is_valid && licenseData?.license_last_valid_date && (
                    <Typography variant="body2" color="text.secondary">
                      {__('Last validated:', 'flux-media-optimizer')} {formatLicenseDate(licenseData.license_last_valid_date)}
                    </Typography>
                  )}
                  <Box>
                    <Tooltip
                      title={!licenseData?.license_is_valid ? __('A valid license key is required to enable CDN and external processing', 'flux-media-optimizer') : ''}
                      arrow
                    >
                      <span>
                        <FormControlLabel
                          control={
                            <Switch
                              checked={settings?.external_service_enabled || false}
                              onChange={handleSettingChange('external_service_enabled')}
                              disabled={isLoading || !licenseData?.license_is_valid}
                            />
                          }
                          label={__('Enable CDN and External Processing', 'flux-media-optimizer')}
                        />
                      </span>
                    </Tooltip>
                    <FormHelperText sx={{ ml: 0, mt: 1 }}>
                      {__('When enabled, all media files will be stored on the CDN. Images and videos will be processed and optimized by the external service, while other file types (PDFs, documents, etc.) will be stored directly for CDN delivery. Local processing will be disabled.', 'flux-media-optimizer')}
                    </FormHelperText>
                  </Box>
                </Stack>
              </Grid>
              <Grid item xs={12} md={6}>
                {licenseActivationError && (
                  <Alert 
                    severity="error" 
                    onClose={() => setLicenseActivationError(null)}
                  >
                    <Typography variant="body2" component="div">
                      <strong>{__('License Activation Failed', 'flux-media-optimizer')}</strong>
                      <Typography variant="body2" component="div" sx={{ mt: 0.5 }}>
                        {licenseActivationError.message || __('An error occurred while activating your license. Please check your license key and try again.', 'flux-media-optimizer')}
                      </Typography>
                    </Typography>
                  </Alert>
                )}
              </Grid>
            </Grid>
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
