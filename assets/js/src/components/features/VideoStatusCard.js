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
 * Dumb component for displaying video processing status
 *
 * @since TBD
 */
const VideoStatusCard = ({ status, loading, error }) => {
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
          {/* Video Processing Skeleton */}
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
        {__('Error loading video status:', 'flux-media')} {error?.message || __('Unknown error occurred', 'flux-media')}
      </Alert>
    );
  }

  // Handle no data state
  if (!status) {
    return (
      <Alert severity="warning" sx={{ mb: 3 }}>
        {__('No video status data available', 'flux-media')}
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
  const videoProcessor = status.videoProcessor || {};

  const hasVideoSupport = videoProcessor.available && 
    (videoProcessor.av1_support || videoProcessor.webm_support);

  return (
    <Box>
      <Typography variant="h5" gutterBottom>
        {__('Video Processing Status', 'flux-media')}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
        {__('Current video processing capabilities and requirements', 'flux-media')}
      </Typography>
      <Divider sx={{ mb: 3 }} />
      
      <Grid container spacing={3}>
        {/* Video Processing Status */}
        <Grid item xs={12} md={6}>
          <Box>
            <Typography variant="h6" gutterBottom>
              {__('Video Processing', 'flux-media')}
            </Typography>
            
            {/* Overall Video Support Status */}
            <Box sx={{ mb: 2 }}>
              {getStatusChip(videoProcessor.available, __('Video Processing', 'flux-media'))}
            </Box>

            {/* Individual Processors */}
            {videoProcessor.processors && Object.keys(videoProcessor.processors).length > 0 && (
              <Box sx={{ ml: 2 }}>
                {Object.entries(videoProcessor.processors).map(([processorType, processor]) => (
                  <Box key={processorType} sx={{ mb: 2 }}>
                    <Typography variant="subtitle2" gutterBottom>
                      {processor.type?.toUpperCase() || processorType.toUpperCase()}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                      {__('Version:', 'flux-media')} {processor.version || __('Unknown', 'flux-media')}
                    </Typography>
                    <Grid container spacing={1}>
                      <Grid item>
                        <Chip
                          label="AV1"
                          color={processor.av1_support ? 'success' : 'error'}
                          size="small"
                        />
                      </Grid>
                      <Grid item>
                        <Chip
                          label="WebM"
                          color={processor.webm_support ? 'success' : 'error'}
                          size="small"
                        />
                      </Grid>
                    </Grid>
                  </Box>
                ))}
              </Box>
            )}
          </Box>
        </Grid>

        {/* Warnings and Recommendations */}
        {!hasVideoSupport && (
          <Grid item xs={12}>
            <Alert severity="warning">
              <AlertTitle>{__('Video Processing Not Available', 'flux-media')}</AlertTitle>
              <Typography variant="body2">
                {__('Video processing is not available. Please install FFmpeg with AV1/WebM support.', 'flux-media')}
              </Typography>
            </Alert>
          </Grid>
        )}

        {hasVideoSupport && (
          <Grid item xs={12}>
            <Alert severity="success">
              <AlertTitle>{__('Video Processing Ready', 'flux-media')}</AlertTitle>
              <Typography variant="body2">
                {__('Video processing components are available. Flux Media can optimize videos.', 'flux-media')}
              </Typography>
            </Alert>
          </Grid>
        )}
      </Grid>
    </Box>
  );
};

export default VideoStatusCard;
