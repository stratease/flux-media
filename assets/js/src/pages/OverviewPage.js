import React from 'react';
import { Grid, Typography, Box, Alert, AlertTitle, Link } from '@mui/material';
import { __ } from '@wordpress/i18n';
import { ImageStatusCard, VideoStatusCard, PHPConfigurationCard } from '@flux-media-optimizer/components';
import { useSystemStatus } from '@flux-media-optimizer/hooks/useSystemStatus';
import { useConversions } from '@flux-media-optimizer/hooks/useConversions';

/**
 * Format byte count as megabytes for display.
 *
 * @since 4.2.0
 * @param {number} bytes Byte count.
 * @return {string} Formatted size string.
 */
const formatMegabytes = (bytes) => {
  const value = Number(bytes) || 0;
  if (value <= 0) {
    return '0 MB';
  }

  return `${(value / 1024 / 1024).toFixed(1)} MB`;
};

/**
 * Normalize conversion stats from API payload.
 *
 * @since 4.2.0
 * @param {Object|null} conversionsData Raw conversions stats payload.
 * @return {Object|null} Normalized stats or null when unavailable.
 */
const normalizeConversionStats = (conversionsData) => {
  if (!conversionsData) {
    return null;
  }

  return {
    originalSize: Number(conversionsData.total_original_bytes) || 0,
    convertedSize: Number(conversionsData.total_converted_bytes) || 0,
    savings: Number(conversionsData.total_savings_bytes) || 0,
    percentage: Number(conversionsData.total_savings_percentage) || 0,
    failedConversions: Number(conversionsData.failed_conversions) || 0,
  };
};

/**
 * Overview page component showing system status and conversion statistics.
 *
 * @since 0.1.0
 */
const OverviewPage = () => {
  const { data: systemStatus, isLoading: systemLoading } = useSystemStatus();
  const { data: conversionsData, isLoading: conversionsLoading } = useConversions();
  const conversionStats = normalizeConversionStats(conversionsData);
  const hasFailedConversions = Boolean(conversionStats && conversionStats.failedConversions > 0);
  const mediaLibraryFailedUrl = `${window.fluxMediaAdmin?.adminUrl || '/wp-admin/'}upload.php?flux_optimization_status=failed`;

  return (
    <>
      {!conversionsLoading && hasFailedConversions && (
        <Box sx={{ mb: 3 }}>
          <Alert severity="warning">
            <AlertTitle>
              {__('Failed optimizations need attention', 'flux-media-optimizer')}
            </AlertTitle>
            {__(
              'Some media items failed optimization.',
              'flux-media-optimizer'
            )}{' '}
            <Link href={mediaLibraryFailedUrl} underline="hover">
              {__('Open Media Library', 'flux-media-optimizer')}
            </Link>
            {__(
              ', filter by Failed, then retry or reconvert those items.',
              'flux-media-optimizer'
            )}
          </Alert>
        </Box>
      )}

      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <ImageStatusCard
            status={systemStatus}
            loading={systemLoading}
          />
        </Grid>

        <Grid item xs={12} md={6}>
          <VideoStatusCard
            status={systemStatus}
            loading={systemLoading}
          />
        </Grid>
        <Grid item xs={12} md={6}>
          <PHPConfigurationCard
            status={systemStatus}
            loading={systemLoading}
          />
        </Grid>
      </Grid>

      {!conversionsLoading && conversionStats && (
        <Box sx={{ mt: 4 }}>
          <Typography variant="h5" component="h2" gutterBottom>
            {__('Conversion Savings', 'flux-media-optimizer')}
          </Typography>

          <Grid container spacing={3}>
            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6" color="primary">
                  {conversionStats.percentage}%
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Bandwidth Saved', 'flux-media-optimizer')}
                </Typography>
              </Box>
            </Grid>

            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6">
                  {formatMegabytes(conversionStats.originalSize)}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Original Size', 'flux-media-optimizer')}
                </Typography>
              </Box>
            </Grid>

            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6">
                  {formatMegabytes(conversionStats.convertedSize)}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Converted Size', 'flux-media-optimizer')}
                </Typography>
              </Box>
            </Grid>

            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6" color="success.main">
                  {formatMegabytes(conversionStats.savings)}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Total Savings', 'flux-media-optimizer')}
                </Typography>
              </Box>
            </Grid>
          </Grid>
        </Box>
      )}
    </>
  );
};

export default OverviewPage;
