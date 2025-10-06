import React from 'react';
import {
  Typography,
  Box,
  LinearProgress,
  Grid,
  Chip,
  Button,
  Alert,
  AlertTitle,
  Divider,
  Skeleton,
} from '@mui/material';
import {
  TrendingUp,
  VideoLibrary,
  Image,
  Upgrade,
} from '@mui/icons-material';
import { __ } from '@wordpress/i18n';

/**
 * Dumb component for displaying quota progress
 */
const QuotaProgressCard = ({ quota, loading, error, onUpgrade }) => {
  // Handle loading state
  if (loading) {
    return (
      <Box>
        <Grid container justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
          <Grid item>
            <Skeleton variant="text" width="35%" height={40} sx={{ mb: 1 }} />
            <Skeleton variant="text" width="25%" height={24} />
          </Grid>
          <Grid item>
            <Skeleton variant="rectangular" width={100} height={36} sx={{ borderRadius: 1 }} />
          </Grid>
        </Grid>
        <Divider sx={{ mb: 3 }} />
        
        <Grid container spacing={3}>
          {/* Image Quota Skeleton */}
          <Grid item xs={12} md={6}>
            <Box>
              <Skeleton variant="text" width="40%" height={32} sx={{ mb: 2 }} />
              <Skeleton variant="text" width="60%" height={48} sx={{ mb: 1 }} />
              <Skeleton variant="text" width="30%" height={20} sx={{ mb: 2 }} />
              <Skeleton variant="rectangular" height={8} sx={{ borderRadius: 4, mb: 1 }} />
              <Skeleton variant="text" width="25%" height={16} />
            </Box>
          </Grid>

          {/* Video Quota Skeleton */}
          <Grid item xs={12} md={6}>
            <Box>
              <Skeleton variant="text" width="40%" height={32} sx={{ mb: 2 }} />
              <Skeleton variant="text" width="60%" height={48} sx={{ mb: 1 }} />
              <Skeleton variant="text" width="30%" height={20} sx={{ mb: 2 }} />
              <Skeleton variant="rectangular" height={8} sx={{ borderRadius: 4, mb: 1 }} />
              <Skeleton variant="text" width="25%" height={16} />
            </Box>
          </Grid>

          {/* Quota Status Skeleton */}
          <Grid item xs={12}>
            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
              <Skeleton variant="text" width="30%" height={20} />
              <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
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
        Error loading quota progress: {error?.message || 'Unknown error occurred'}
      </Alert>
    );
  }

  // Handle no data state
  if (!quota) {
    return (
      <Alert severity="warning" sx={{ mb: 3 }}>
        No quota data available
      </Alert>
    );
  }

  // Safely access nested properties with fallbacks
  const images = quota.images || { used: 0, limit: 0 };
  const videos = quota.videos || { used: 0, limit: 0 };
  const plan = quota.plan || 'free';

  const isImageQuotaExceeded = images.used >= images.limit;
  const isVideoQuotaExceeded = videos.used >= videos.limit;
  const isAnyQuotaExceeded = isImageQuotaExceeded || isVideoQuotaExceeded;

  const getProgressColor = (progress, isExceeded) => {
    if (isExceeded) return 'error';
    if (progress >= 80) return 'warning';
    return 'primary';
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
  };

  return (
    <Box>
      <Grid container justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
        <Grid item>
          <Typography variant="h5" gutterBottom>
            {__('Monthly Quota Usage', 'flux-media')}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {__('Current plan:', 'flux-media')} {plan.charAt(0).toUpperCase() + plan.slice(1)}
          </Typography>
        </Grid>
        {plan === 'free' && onUpgrade && (
          <Grid item>
            <Button
              variant="contained"
              color="primary"
              startIcon={<Upgrade />}
              onClick={onUpgrade}
              size="small"
            >
              {__('Upgrade', 'flux-media')}
            </Button>
          </Grid>
        )}
      </Grid>
      <Divider sx={{ mb: 3 }} />
        <Grid container spacing={3}>
          {/* Image Quota */}
          <Grid item xs={12} md={6}>
            <Box>
              <Grid container alignItems="center" sx={{ mb: 1 }}>
                <Grid item>
                  <Image color="primary" sx={{ mr: 1 }} />
                </Grid>
                <Grid item>
                  <Typography variant="h6">
                    {__('Image Conversions', 'flux-media')}
                  </Typography>
                </Grid>
              </Grid>
              
              <Box sx={{ mb: 2 }}>
                <Typography variant="h4" component="div" color={isImageQuotaExceeded ? 'error' : 'text.primary'}>
                  {images.used} / {images.limit}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {images.remaining || 0} remaining
                </Typography>
              </Box>
              
              <LinearProgress
                variant="determinate"
                value={Math.min(images.progress || 0, 100)}
                color={getProgressColor(images.progress || 0, isImageQuotaExceeded)}
                sx={{ height: 8, borderRadius: 4, mb: 1 }}
              />
              
              <Typography variant="caption" color="text.secondary">
                {(images.progress || 0).toFixed(1)}% used
              </Typography>
            </Box>
          </Grid>

          {/* Video Quota */}
          <Grid item xs={12} md={6}>
            <Box>
              <Box sx={{ display: 'flex', alignItems: 'center', mb: 1 }}>
                <VideoLibrary color="secondary" sx={{ mr: 1 }} />
                <Typography variant="h6">
                  Video Conversions
                </Typography>
              </Box>
              
              <Box sx={{ mb: 2 }}>
                <Typography variant="h4" component="div" color={isVideoQuotaExceeded ? 'error' : 'text.primary'}>
                  {videos.used} / {videos.limit}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {videos.remaining || 0} remaining
                </Typography>
              </Box>
              
              <LinearProgress
                variant="determinate"
                value={Math.min(videos.progress || 0, 100)}
                color={getProgressColor(videos.progress || 0, isVideoQuotaExceeded)}
                sx={{ height: 8, borderRadius: 4, mb: 1 }}
              />
              
              <Typography variant="caption" color="text.secondary">
                {(videos.progress || 0).toFixed(1)}% used
              </Typography>
            </Box>
          </Grid>

          {/* Quota Status */}
          <Grid item xs={12}>
            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
              <Typography variant="body2" color="text.secondary">
                {__('Next reset:', 'flux-media')} {quota.next_reset ? formatDate(quota.next_reset) : __('Unknown', 'flux-media')}
              </Typography>
              <Chip
                label={plan.toUpperCase()}
                color={plan === 'free' ? 'default' : 'primary'}
                size="small"
              />
            </Box>

            {/* Quota Exceeded Warning */}
            {isAnyQuotaExceeded && (
              <Alert severity="warning" sx={{ mb: 2 }}>
                <AlertTitle>Quota Exceeded</AlertTitle>
                <Typography variant="body2">
                  {isImageQuotaExceeded && isVideoQuotaExceeded && 
                    'Both image and video conversion quotas have been exceeded. Upgrade to continue converting media.'}
                  {isImageQuotaExceeded && !isVideoQuotaExceeded && 
                    'Image conversion quota has been exceeded. You can still convert videos or upgrade for unlimited conversions.'}
                  {!isImageQuotaExceeded && isVideoQuotaExceeded && 
                    'Video conversion quota has been exceeded. You can still convert images or upgrade for unlimited conversions.'}
                </Typography>
              </Alert>
            )}

            {/* Free Plan Benefits */}
            {quota.plan === 'free' && !isAnyQuotaExceeded && (
              <Alert severity="info">
                <AlertTitle>Free Plan Benefits</AlertTitle>
                <Typography variant="body2">
                  You're using the free plan with {quota.images.limit} image conversions and {quota.videos.limit} video conversions per month. 
                  Upgrade to unlock unlimited conversions, CDN integration, and premium features.
                </Typography>
              </Alert>
            )}
          </Grid>
        </Grid>
    </Box>
  );
};

export default QuotaProgressCard;
