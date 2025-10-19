import React from 'react';
import { Grid, Typography, Box } from '@mui/material';
import { __ } from '@wordpress/i18n';
import { SystemStatusCard, QuotaProgressCard } from '@flux-media/components';
import { useSystemStatus } from '@flux-media/hooks/useSystemStatus';
import { useQuotaProgress } from '@flux-media/hooks/useQuotaProgress';
import { useConversions } from '@flux-media/hooks/useConversions';

/**
 * Overview page component showing system status, quota, and conversion statistics.
 *
 * @since 0.1.0
 */
const OverviewPage = () => {
  const { data: systemStatus, isLoading: systemLoading } = useSystemStatus();
  const { data: quotaData, isLoading: quotaLoading } = useQuotaProgress();
  const { data: conversionsData, isLoading: conversionsLoading } = useConversions();

  const getSavingsStats = () => {
    if (!conversionsData) return null;
    
    const { total_original_bytes, total_converted_bytes, total_savings_bytes, total_savings_percentage } = conversionsData;
    
    return {
      originalSize: total_original_bytes,
      convertedSize: total_converted_bytes,
      savings: total_savings_bytes,
      percentage: total_savings_percentage
    };
  };


  return (
    <Box>
      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <SystemStatusCard 
            status={systemStatus} 
            loading={systemLoading}
          />
        </Grid>
        
        {/* Temporarily hidden quota section */}
        {/* <Grid item xs={12} md={6}>
          <QuotaProgressCard 
            quota={quotaData} 
            loading={quotaLoading}
          />
        </Grid> */}
      </Grid>

      {/* Conversion Savings Section - without Paper wrapper */}
      {!conversionsLoading && getSavingsStats() && (
        <Box sx={{ mt: 4 }}>
          <Typography variant="h5" component="h2" gutterBottom>
            {__('Conversion Savings', 'flux-media')}
          </Typography>
          
          <Grid container spacing={3}>
            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6" color="primary">
                  {getSavingsStats().percentage}%
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Space Saved', 'flux-media')}
                </Typography>
              </Box>
            </Grid>
            
            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6">
                  {getSavingsStats().originalSize ? (getSavingsStats().originalSize / 1024 / 1024).toFixed(1) + ' MB' : '0 MB'}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Original Size', 'flux-media')}
                </Typography>
              </Box>
            </Grid>
            
            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6">
                  {getSavingsStats().convertedSize ? (getSavingsStats().convertedSize / 1024 / 1024).toFixed(1) + ' MB' : '0 MB'}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Converted Size', 'flux-media')}
                </Typography>
              </Box>
            </Grid>
            
            <Grid item xs={12} sm={6} md={3}>
              <Box sx={{ textAlign: 'center', p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                <Typography variant="h6" color="success.main">
                  {getSavingsStats().savings ? (getSavingsStats().savings / 1024 / 1024).toFixed(1) + ' MB' : '0 MB'}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {__('Total Savings', 'flux-media')}
                </Typography>
              </Box>
            </Grid>
          </Grid>
        </Box>
      )}

    </Box>
  );
};

export default OverviewPage;
