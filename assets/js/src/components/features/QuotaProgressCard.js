import React from 'react';
import {
  Typography,
  Box,
  Grid,
  Alert,
  AlertTitle,
  Divider,
  Skeleton,
} from '@mui/material';
import {
  Image,
} from '@mui/icons-material';
import { __ } from '@wordpress/i18n';

/**
 * Dumb component for displaying conversion status
 */
const ConversionStatusCard = ({ conversionStats, loading, error }) => {
  // Handle loading state
  if (loading) {
    return (
      <Box>
        <Grid container justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
          <Grid item>
            <Skeleton variant="text" width="35%" height={40} sx={{ mb: 1 }} />
            <Skeleton variant="text" width="25%" height={24} />
          </Grid>
        </Grid>
        <Divider sx={{ mb: 3 }} />
        
        <Grid container spacing={3}>
          {/* Conversion Stats Skeleton */}
          <Grid item xs={12} md={6}>
            <Box>
              <Skeleton variant="text" width="40%" height={32} sx={{ mb: 2 }} />
              <Skeleton variant="text" width="60%" height={48} sx={{ mb: 1 }} />
              <Skeleton variant="text" width="30%" height={20} sx={{ mb: 2 }} />
            </Box>
          </Grid>

          {/* System Status Skeleton */}
          <Grid item xs={12} md={6}>
            <Box>
              <Skeleton variant="text" width="40%" height={32} sx={{ mb: 2 }} />
              <Skeleton variant="text" width="60%" height={48} sx={{ mb: 1 }} />
              <Skeleton variant="text" width="30%" height={20} sx={{ mb: 2 }} />
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
        Error loading conversion status: {error?.message || 'Unknown error occurred'}
      </Alert>
    );
  }

  // Handle no data state
  if (!conversionStats) {
    return (
      <Alert severity="warning" sx={{ mb: 3 }}>
        No conversion data available
      </Alert>
    );
  }

  // Safely access conversion statistics with fallbacks
  const totalConversions = conversionStats.total_conversions || 0;
  const totalSavings = conversionStats.total_savings_bytes || 0;
  const totalSavingsPercentage = conversionStats.total_savings_percentage || 0;
  const recentConversions = conversionStats.recent?.count || 0;

  return (
    <Box>
      <Grid container justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
        <Grid item>
          <Typography variant="h5" gutterBottom>
            {__('Conversion Status', 'flux-media')}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {__('Image and video optimization statistics', 'flux-media')}
          </Typography>
        </Grid>
      </Grid>
      <Divider sx={{ mb: 3 }} />
      
      <Grid container spacing={3}>
        {/* Conversion Statistics */}
        <Grid item xs={12} md={6}>
          <Box>
            <Grid container alignItems="center" sx={{ mb: 1 }}>
              <Grid item>
                <Image color="primary" sx={{ mr: 1 }} />
              </Grid>
              <Grid item>
                <Typography variant="h6">
                  {__('Total Conversions', 'flux-media')}
                </Typography>
              </Grid>
            </Grid>
            
            <Box sx={{ mb: 2 }}>
              <Typography variant="h4" component="div" color="text.primary">
                {totalConversions.toLocaleString()}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {__('Files optimized', 'flux-media')}
              </Typography>
            </Box>
          </Box>
        </Grid>

        {/* Storage Savings */}
        <Grid item xs={12} md={6}>
          <Box>
            <Grid container alignItems="center" sx={{ mb: 1 }}>
              <Grid item>
                <Image color="secondary" sx={{ mr: 1 }} />
              </Grid>
              <Grid item>
                <Typography variant="h6">
                  {__('Storage Savings', 'flux-media')}
                </Typography>
              </Grid>
            </Grid>
            
            <Box sx={{ mb: 2 }}>
              <Typography variant="h4" component="div" color="text.primary">
                {totalSavingsPercentage.toFixed(1)}%
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {__('Space saved', 'flux-media')}
              </Typography>
            </Box>
          </Box>
        </Grid>

        {/* Recent Activity */}
        <Grid item xs={12}>
          <Alert severity="info">
            <AlertTitle>{__('Recent Activity', 'flux-media')}</AlertTitle>
            <Typography variant="body2">
              {recentConversions > 0 
                ? __('%d conversions completed in the last 30 days', 'flux-media').replace('%d', recentConversions)
                : __('No recent conversions', 'flux-media')
              }
            </Typography>
          </Alert>
        </Grid>
      </Grid>
    </Box>
  );
};

export default ConversionStatusCard;
