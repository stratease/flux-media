import React from 'react';
import {
  Typography,
  Box,
  Chip,
  Grid,
  Alert,
  AlertTitle,
  Divider,
  Skeleton,
} from '@mui/material';
import {
  CheckCircle,
  Error,
} from '@mui/icons-material';
import { __, _x } from '@wordpress/i18n';

/**
 * Dumb component for displaying system status
 */
const SystemStatusCard = ({ status, loading, error }) => {
  // Handle loading state
  if (loading) {
    return (
      <Box>
        <Box sx={{ mb: 3 }}>
          <Skeleton variant="text" width="30%" height={40} sx={{ mb: 1 }} />
          <Skeleton variant="text" width="50%" height={24} />
        </Box>
        <Divider sx={{ mb: 3 }} />
        
        <Grid container spacing={3}>
          {/* Image Processing Skeleton */}
          <Grid item xs={12} md={6}>
            <Box>
              <Skeleton variant="text" width="40%" height={32} sx={{ mb: 2 }} />
              <Skeleton variant="rectangular" width={120} height={32} sx={{ borderRadius: 1, mb: 2 }} />
              <Skeleton variant="text" width="60%" height={20} sx={{ mb: 1 }} />
              <Grid container spacing={1}>
                <Grid item>
                  <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                </Grid>
                <Grid item>
                  <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                </Grid>
              </Grid>
            </Box>
          </Grid>

          {/* Video Processing Skeleton */}
          <Grid item xs={12} md={6}>
            <Box>
              <Skeleton variant="text" width="40%" height={32} sx={{ mb: 2 }} />
              <Skeleton variant="rectangular" width={120} height={32} sx={{ borderRadius: 1, mb: 2 }} />
              <Skeleton variant="text" width="60%" height={20} sx={{ mb: 1 }} />
              <Grid container spacing={1}>
                <Grid item>
                  <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                </Grid>
                <Grid item>
                  <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                </Grid>
              </Grid>
            </Box>
          </Grid>

          {/* PHP Configuration Skeleton */}
          <Grid item xs={12}>
            <Skeleton variant="text" width="30%" height={32} sx={{ mb: 2 }} />
            <Grid container spacing={2}>
              <Grid item xs={6} sm={3}>
                <Skeleton variant="text" width="60%" height={20} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="80%" height={24} />
              </Grid>
              <Grid item xs={6} sm={3}>
                <Skeleton variant="text" width="60%" height={20} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="80%" height={24} />
              </Grid>
              <Grid item xs={6} sm={3}>
                <Skeleton variant="text" width="60%" height={20} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="80%" height={24} />
              </Grid>
              <Grid item xs={6} sm={3}>
                <Skeleton variant="text" width="60%" height={20} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="80%" height={24} />
              </Grid>
            </Grid>
          </Grid>
        </Grid>
      </Box>
    );
  }

  // Handle error state
  if (error) {
    return (
      <Alert severity="error" sx={{ mb: 3 }}>
        {__('Error loading system status:', 'flux-media')} {error?.message || __('Unknown error occurred', 'flux-media')}
      </Alert>
    );
  }

  // Handle no data state
  if (!status) {
    return (
      <Alert severity="warning" sx={{ mb: 3 }}>
        {__('No system status data available', 'flux-media')}
      </Alert>
    );
  }

  const getStatusIcon = (available) => {
    return available ? (
      <CheckCircle color="success" />
    ) : (
      <Error color="error" />
    );
  };

  const getStatusChip = (available, type) => {
    return (
      <Chip
        icon={getStatusIcon(available)}
        label={available ? `${type} ${__('Available', 'flux-media')}` : `${type} ${__('Not Available', 'flux-media')}`}
        color={available ? 'success' : 'error'}
        size="small"
      />
    );
  };

  // Safely access nested properties with fallbacks
  const imageProcessor = status.imageProcessor || {};
  const videoProcessor = status.videoProcessor || {};

  const hasImageSupport = imageProcessor.available && 
    (imageProcessor.webp_support || imageProcessor.avif_support);
  
  const hasVideoSupport = videoProcessor.available && 
    (videoProcessor.av1_support || videoProcessor.webm_support);

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h5" gutterBottom>
          {__('System Status', 'flux-media')}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {__('Current system capabilities and requirements', 'flux-media')}
        </Typography>
      </Box>
      <Divider sx={{ mb: 3 }} />
        <Grid container spacing={3}>
          {/* Image Processing Status */}
          <Grid item xs={12} md={6}>
            <Box>
              <Typography variant="h6" gutterBottom>
                {__('Image Processing', 'flux-media')}
              </Typography>
              <Box sx={{ mb: 2 }}>
                {getStatusChip(imageProcessor.available, (imageProcessor.type || 'Unknown').toUpperCase())}
              </Box>
          
              <Box sx={{ ml: 2 }}>
                <Typography variant="body2" color="text.secondary">
                  {__('Version:', 'flux-media')} {imageProcessor?.version || __('Unknown', 'flux-media')}
                </Typography>
                <Grid container spacing={1} sx={{ mt: 1 }}>
                  <Grid item>
                    <Chip
                      label="WebP"
                      color={imageProcessor?.webp_support ? 'success' : 'error'}
                      size="small"
                    />
                  </Grid>
                  <Grid item>
                    <Chip
                      label="AVIF"
                      color={imageProcessor?.avif_support ? 'success' : 'error'}
                      size="small"
                    />
                  </Grid>
                </Grid>
              </Box>
            
            </Box>
          </Grid>

          {/* Video Processing Status */}
          <Grid item xs={12} md={6}>
            <Box>
              <Typography variant="h6" gutterBottom>
                {__('Video Processing', 'flux-media')}
              </Typography>
              <Box sx={{ mb: 2 }}>
                {getStatusChip(videoProcessor.available, (videoProcessor.type || 'Unknown').toUpperCase())}
              </Box>
              
              <Box sx={{ ml: 2 }}>
                <Typography variant="body2" color="text.secondary">
                  {__('Version:', 'flux-media')} {videoProcessor?.version || __('Unknown', 'flux-media')}
                </Typography> 
                <Grid container spacing={1} sx={{ mt: 1 }}>
                  <Grid item>
                    <Chip
                      label="AV1"
                      color={videoProcessor?.av1_support ? 'success' : 'error'}
                      size="small"
                    />
                  </Grid>
                  <Grid item>
                    <Chip
                      label="WebM"
                      color={videoProcessor?.webm_support ? 'success' : 'error'}
                      size="small"
                    />
                  </Grid>
                </Grid>
              </Box>
            </Box>
          </Grid>

          {/* PHP Configuration */}
          <Grid item xs={12}>
            <Typography variant="h6" gutterBottom>
              {__('PHP Configuration', 'flux-media')}
            </Typography>
            <Grid container spacing={2}>
              <Grid item xs={6} sm={3}>
                <Typography variant="body2" color="text.secondary">
                  {__('PHP Version', 'flux-media')}
                </Typography>
                <Typography variant="body1">
                  {status.phpVersion || __('Unknown', 'flux-media')}
                </Typography>
              </Grid>
              <Grid item xs={6} sm={3}>
                <Typography variant="body2" color="text.secondary">
                  {__('Memory Limit', 'flux-media')}
                </Typography>
                <Typography variant="body1">
                  {status.memoryLimit || __('Unknown', 'flux-media')}
                </Typography>
              </Grid>
              <Grid item xs={6} sm={3}>
                <Typography variant="body2" color="text.secondary">
                  {__('Max Execution Time', 'flux-media')}
                </Typography>
                <Typography variant="body1">
                  {status.maxExecutionTime || __('Unknown', 'flux-media')}s
                </Typography>
              </Grid>
              <Grid item xs={6} sm={3}>
                <Typography variant="body2" color="text.secondary">
                  {__('Upload Max Filesize', 'flux-media')}
                </Typography>
                <Typography variant="body1">
                  {status.uploadMaxFilesize || __('Unknown', 'flux-media')}
                </Typography>
              </Grid>
            </Grid>
          </Grid>

          {/* Warnings and Recommendations */}
          {(!hasImageSupport || !hasVideoSupport) && (
            <Grid item xs={12}>
              <Alert severity="warning">
                <AlertTitle>{__('System Requirements Not Met', 'flux-media')}</AlertTitle>
                <Typography variant="body2">
                  {!hasImageSupport && !hasVideoSupport && 
                    __('Neither image nor video processing is available. Please install Imagick/GD with WebP/AVIF support and FFmpeg with AV1/WebM support.', 'flux-media')}
                  {!hasImageSupport && hasVideoSupport && 
                    __('Image processing is not available. Please install Imagick or GD with WebP/AVIF support.', 'flux-media')}
                  {hasImageSupport && !hasVideoSupport && 
                    __('Video processing is not available. Please install FFmpeg with AV1/WebM support.', 'flux-media')}
                </Typography>
              </Alert>
            </Grid>
          )}

          {hasImageSupport && hasVideoSupport && (
            <Grid item xs={12}>
              <Alert severity="success">
                <AlertTitle>{__('System Ready', 'flux-media')}</AlertTitle>
                <Typography variant="body2">
                  {__('All required components are available. Flux Media can optimize both images and videos.', 'flux-media')}
                </Typography>
              </Alert>
            </Grid>
          )}
        </Grid>
    </Box>
  );
};

export default SystemStatusCard;
