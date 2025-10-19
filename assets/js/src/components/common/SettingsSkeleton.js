import React from 'react';
import { Box, Grid, Skeleton, Divider } from '@mui/material';

/**
 * Skeleton loader component for settings page sections
 */
const SettingsSkeleton = () => {
  return (
    <Box>
      {/* Header Skeleton */}
      <Grid container justifyContent="space-between" alignItems="flex-start" sx={{ mb: 4 }}>
        <Grid item>
          <Skeleton variant="text" width={300} height={48} sx={{ mb: 1 }} />
          <Skeleton variant="text" width={400} height={24} />
        </Grid>
      </Grid>

      <Grid container spacing={3}>
        {/* General Settings Skeleton */}
        <Grid item xs={12} md={6}>
          <Divider sx={{ mb: 3 }} />
          <Box>
            <Skeleton variant="text" width={200} height={32} sx={{ mb: 2 }} />
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              {/* Auto-convert switch */}
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Skeleton variant="rectangular" width={44} height={24} sx={{ borderRadius: 12 }} />
                <Skeleton variant="text" width={150} height={24} />
              </Box>
              
              {/* Bulk conversion switch */}
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Skeleton variant="rectangular" width={44} height={24} sx={{ borderRadius: 12 }} />
                <Skeleton variant="text" width={180} height={24} />
              </Box>
              <Skeleton variant="text" width="100%" height={20} />
              
              {/* Hybrid approach switch */}
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Skeleton variant="rectangular" width={44} height={24} sx={{ borderRadius: 12 }} />
                <Skeleton variant="text" width={250} height={24} />
              </Box>
              <Skeleton variant="text" width="100%" height={40} />
              
              {/* WebP/AVIF switches */}
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Skeleton variant="rectangular" width={44} height={24} sx={{ borderRadius: 12 }} />
                <Skeleton variant="text" width={180} height={24} />
              </Box>
              
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Skeleton variant="rectangular" width={44} height={24} sx={{ borderRadius: 12 }} />
                <Skeleton variant="text" width={180} height={24} />
              </Box>
            </Box>
          </Box>
        </Grid>

        {/* Image Quality Settings Skeleton */}
        <Grid item xs={12} md={6}>
          <Divider sx={{ mb: 3 }} />
          <Box>
            <Skeleton variant="text" width={250} height={32} sx={{ mb: 2 }} />
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
              {/* WebP Quality */}
              <Box>
                <Skeleton variant="text" width={120} height={24} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="100%" height={20} sx={{ mb: 1 }} />
                <Skeleton variant="rectangular" width="100%" height={6} sx={{ borderRadius: 3 }} />
              </Box>
              
              {/* AVIF Quality */}
              <Box>
                <Skeleton variant="text" width={120} height={24} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="100%" height={20} sx={{ mb: 1 }} />
                <Skeleton variant="rectangular" width="100%" height={6} sx={{ borderRadius: 3 }} />
              </Box>
              
              {/* AVIF Speed */}
              <Box>
                <Skeleton variant="text" width={120} height={24} sx={{ mb: 1 }} />
                <Skeleton variant="text" width="100%" height={20} sx={{ mb: 1 }} />
                <Skeleton variant="rectangular" width="100%" height={6} sx={{ borderRadius: 3 }} />
              </Box>
            </Box>
          </Box>
        </Grid>

        {/* License Settings Skeleton */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Skeleton variant="text" width={200} height={32} sx={{ mb: 1 }} />
            <Skeleton variant="text" width="100%" height={20} sx={{ mb: 2 }} />
            <Skeleton variant="rectangular" width={400} height={56} sx={{ borderRadius: 1 }} />
          </Box>
        </Grid>

        {/* Newsletter Subscription Skeleton */}
        <Grid item xs={12}>
          <Divider sx={{ my: 2 }} />
          <Box>
            <Skeleton variant="text" width={150} height={32} sx={{ mb: 1 }} />
            <Skeleton variant="text" width="100%" height={20} sx={{ mb: 2 }} />
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, maxWidth: 400 }}>
              <Skeleton variant="rectangular" width="100%" height={56} sx={{ borderRadius: 1 }} />
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                <Skeleton variant="rectangular" width={20} height={20} sx={{ borderRadius: 1 }} />
                <Skeleton variant="text" width={200} height={20} />
              </Box>
              <Skeleton variant="rectangular" width={120} height={36} sx={{ borderRadius: 1 }} />
            </Box>
          </Box>
        </Grid>
      </Grid>
    </Box>
  );
};

export default SettingsSkeleton;
