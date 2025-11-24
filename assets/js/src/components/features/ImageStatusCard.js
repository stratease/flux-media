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
  Tooltip,
} from '@mui/material';
import {
  CheckCircle,
  Error,
} from '@mui/icons-material';
import { __, _x } from '@wordpress/i18n';

/**
 * Dumb component for displaying image processing status
 *
 * @since TBD
 */
const ImageStatusCard = ({ status, loading, error }) => {
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
              
              {/* Processor skeletons */}
              <Box sx={{ ml: 2 }}>
                <Skeleton variant="text" width="30%" height={24} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="50%" height={20} sx={{ mb: 1 }} />
                <Grid container spacing={1} sx={{ mb: 2 }}>
                  <Grid item>
                    <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                  </Grid>
                  <Grid item>
                    <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                  </Grid>
                </Grid>
                
                <Skeleton variant="text" width="35%" height={24} sx={{ mb: 1 }} />
                <Grid container spacing={1}>
                  <Grid item>
                    <Skeleton variant="rectangular" width={80} height={24} sx={{ borderRadius: 1 }} />
                  </Grid>
                  <Grid item>
                    <Skeleton variant="rectangular" width={80} height={24} sx={{ borderRadius: 1 }} />
                  </Grid>
                </Grid>
              </Box>
            </Box>
          </Grid>
        </Grid>
      </Box>
    );
  }

  // Handle error state
  if (error) {
    return (
      <Alert severity="error" sx={{ mb: 3 }}>
        {__('Error loading image status:', 'flux-media-optimizer')} {error?.message || __('Unknown error occurred', 'flux-media-optimizer')}
      </Alert>
    );
  }

  // Handle no data state
  if (!status) {
    return (
      <Alert severity="warning" sx={{ mb: 3 }}>
        {__('No image status data available', 'flux-media-optimizer')}
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
        label={available ? `${type} ${__('Available', 'flux-media-optimizer')}` : `${type} ${__('Not Available', 'flux-media-optimizer')}`}
        color={available ? 'success' : 'error'}
        size="small"
      />
    );
  };

  // Safely access nested properties with fallbacks
  const imageProcessor = status.imageProcessor || {};

  const hasImageSupport = imageProcessor.available && 
    (imageProcessor.webp_support || imageProcessor.avif_support);

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h5" gutterBottom>
          {__('Image Processing Status', 'flux-media-optimizer')}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {__('Current image processing capabilities and requirements', 'flux-media-optimizer')}
        </Typography>
      </Box>
      <Divider sx={{ mb: 3 }} />
      
      <Grid container spacing={3}>
        {/* Image Processing Status */}
        <Grid item xs={12} md={6}>
          <Box>
            <Typography variant="h6" gutterBottom>
              {__('Image Processing', 'flux-media-optimizer')}
            </Typography>
            
            {/* Overall Image Support Status */}
            <Box sx={{ mb: 2 }}>
              {getStatusChip(imageProcessor.available, __('Image Processing', 'flux-media-optimizer'))}
            </Box>

            {/* Individual Processors */}
            {imageProcessor.processors && Object.keys(imageProcessor.processors).length > 0 && (
              <Box sx={{ ml: 2 }}>
                {Object.entries(imageProcessor.processors).map(([processorType, processor]) => (
                  <Box key={processorType} sx={{ mb: 2 }}>
                    <Typography variant="subtitle2" gutterBottom>
                      {processor.type?.toUpperCase() || processorType.toUpperCase()}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                      {__('Version:', 'flux-media-optimizer')} {processor.version || __('Unknown', 'flux-media-optimizer')}
                    </Typography>
                    <Grid container spacing={1}>
                      <Grid item>
                        <Chip
                          label="WebP"
                          color={processor.webp_support ? 'success' : 'error'}
                          size="small"
                        />
                      </Grid>
                      <Grid item>
                        <Chip
                          label="AVIF"
                          color={processor.avif_support ? 'success' : 'error'}
                          size="small"
                        />
                      </Grid>
                      <Grid item>
                        <Tooltip
                          title={
                            processor.animated_gif_support
                              ? __(
                                  'Imagick can preserve animation when converting animated GIFs to WebP/AVIF.',
                                  'flux-media-optimizer'
                                )
                              : __(
                                  'GD cannot preserve animation. Animated GIFs will lose animation when converted. Imagick is required for animated GIF support.',
                                  'flux-media-optimizer'
                                )
                          }
                          arrow
                        >
                          <Chip
                            label="Animated GIF"
                            color={processor.animated_gif_support ? 'success' : 'error'}
                            size="small"
                          />
                        </Tooltip>
                      </Grid>
                    </Grid>
                  </Box>
                ))}
              </Box>
            )}
          
          </Box>
        </Grid>

        {/* Warnings and Recommendations */}
        {!hasImageSupport && (
          <Grid item xs={12}>
            <Alert severity="warning">
              <AlertTitle>{__('Image Processing Not Available', 'flux-media-optimizer')}</AlertTitle>
              <Typography variant="body2">
                {__('Image processing is not available. Please install Imagick or GD with WebP/AVIF support.', 'flux-media-optimizer')}
              </Typography>
            </Alert>
          </Grid>
        )}

        {hasImageSupport && (
          <Grid item xs={12}>
            <Alert severity="success">
              <AlertTitle>{__('Image Processing Ready', 'flux-media-optimizer')}</AlertTitle>
              <Typography variant="body2">
                {__('Image processing components are available. Flux Media Optimizer can optimize images.', 'flux-media-optimizer')}
              </Typography>
            </Alert>
          </Grid>
        )}
      </Grid>
    </Box>
  );
};

export default ImageStatusCard;
