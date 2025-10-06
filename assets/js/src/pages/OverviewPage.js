import React from 'react';
import { Typography, Box, Grid } from '@mui/material';
import { __ } from '@wordpress/i18n';
import { SystemStatusContainer, QuotaProgressContainer } from '@flux-media/components';

/**
 * Overview page component
 */
const OverviewPage = () => {
  return (
    <Box>
      <Grid container spacing={3}>
        {/* System Status */}
        <Grid item xs={12}>
          <SystemStatusContainer />
        </Grid>

        {/* Quota Progress */}
        <Grid item xs={12}>
          <QuotaProgressContainer />
        </Grid>

        {/* Conversion Statistics */}
        <Grid item xs={12}>
          <Typography variant="h4" gutterBottom>
            {__('Conversion Statistics', 'flux-media')}
          </Typography>
          <Typography variant="body1" color="text.secondary">
            {__('Conversion statistics will be displayed here.', 'flux-media')}
          </Typography>
        </Grid>
      </Grid>
    </Box>
  );
};

export default OverviewPage;
