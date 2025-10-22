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
import { __ } from '@wordpress/i18n';

/**
 * Dumb component for displaying PHP configuration
 *
 * @since TBD
 */
const PHPConfigurationCard = ({ status, loading, error }) => {
  // Handle loading state
  if (loading) {
    return (
      <Box>
        <Box sx={{ mb: 3 }}>
          <Skeleton variant="text" width="30%" height={40} sx={{ mb: 1 }} />
          <Skeleton variant="text" width="50%" height={24} />
        </Box>
        <Divider sx={{ mb: 3 }} />
        
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
      </Box>
    );
  }

  // Handle error state
  if (error) {
    return (
      <Alert severity="error" sx={{ mb: 3 }}>
        {__('Error loading PHP configuration:', 'flux-media')} {error?.message || __('Unknown error occurred', 'flux-media')}
      </Alert>
    );
  }

  // Handle no data state
  if (!status) {
    return (
      <Alert severity="warning" sx={{ mb: 3 }}>
        {__('No PHP configuration data available', 'flux-media')}
      </Alert>
    );
  }

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h5" gutterBottom>
          {__('PHP Configuration', 'flux-media')}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {__('Current PHP settings and system requirements', 'flux-media')}
        </Typography>
      </Box>
      <Divider sx={{ mb: 3 }} />
      
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
    </Box>
  );
};

export default PHPConfigurationCard;
