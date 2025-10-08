import React, { useState, useEffect } from 'react';
import {
  Typography,
  Box,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Chip,
  TextField,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Pagination,
  Grid,
  Alert,
  Skeleton,
  IconButton,
  Tooltip,
  Switch,
  FormControlLabel,
  Divider,
} from '@mui/material';
import {
  Refresh,
  Search,
  FilterList,
} from '@mui/icons-material';
import { __ } from '@wordpress/i18n';
import { useQuery } from '@tanstack/react-query';
import { apiService } from '@flux-media/services/api';
import { useAutoSaveForm } from '@flux-media/hooks/useAutoSaveForm';
import { AutoSaveStatus } from '@flux-media/contexts/AutoSaveContext';

/**
 * Logs page component with pagination and filtering
 */
const LogsPage = () => {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [level, setLevel] = useState('');
  const [search, setSearch] = useState('');
  const [enableLogging, setEnableLogging] = useState(false);
  const [error, setError] = useState(null);

  // Auto-save hook for logging setting
  const { debouncedSave } = useAutoSaveForm('logs', { enable_logging: enableLogging });

  // Load initial logging setting
  useEffect(() => {
    const loadSettings = async () => {
      try {
        const response = await apiService.getOptions();
        if (response && typeof response === 'object') {
          setEnableLogging(response.enable_logging || false);
        }
      } catch (err) {
        console.error('Failed to load logging setting:', err);
        setError(__('Failed to load logging setting', 'flux-media'));
      }
    };

    loadSettings();
  }, []);

  // Fetch logs with React Query (only when logging is enabled)
  const {
    data: logsData,
    isLoading,
    error: logsError,
    refetch,
  } = useQuery({
    queryKey: ['logs', page, perPage, level, search],
    queryFn: () => apiService.getLogs({ page, per_page: perPage, level, search }),
    keepPreviousData: true,
    enabled: enableLogging, // Only fetch when logging is enabled
  });

  const handleLoggingToggle = (event) => {
    const newValue = event.target.checked;
    setEnableLogging(newValue);
    setError(null);
    
    // Trigger auto-save
    debouncedSave({ enable_logging: newValue });
  };

  const handlePageChange = (event, newPage) => {
    setPage(newPage);
  };

  const handlePerPageChange = (event) => {
    setPerPage(event.target.value);
    setPage(1); // Reset to first page when changing per page
  };

  const handleLevelChange = (event) => {
    setLevel(event.target.value);
    setPage(1); // Reset to first page when filtering
  };

  const handleSearchChange = (event) => {
    setSearch(event.target.value);
    setPage(1); // Reset to first page when searching
  };

  const handleRefresh = () => {
    refetch();
  };

  const getLevelColor = (level) => {
    switch (level) {
      case 'ERROR':
      case 'CRITICAL':
        return 'error';
      case 'WARNING':
        return 'warning';
      default:
        return 'default';
    }
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString();
  };

  const formatContext = (context) => {
    if (!context) return '';
    if (typeof context === 'string') return context;
    return JSON.stringify(context, null, 2);
  };

  if (error) {
    return (
      <Alert severity="error" sx={{ mb: 3 }}>
        {error}
      </Alert>
    );
  }

  return (
    <Box>
      <Grid container justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
        <Grid item>
          <Typography variant="h5" gutterBottom>
            {__('System Logs', 'flux-media')}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {__('View error and warning logs from Flux Media operations', 'flux-media')}
          </Typography>
        </Grid>
        <Grid item>
          <AutoSaveStatus saveKey="logs" />
        </Grid>
      </Grid>

      {/* Logging Toggle */}
      <Box sx={{ mb: 3, p: 2, bgcolor: 'background.paper', borderRadius: 1, border: '1px solid', borderColor: 'divider' }}>
        <Grid container justifyContent="space-between" alignItems="center">
          <Grid item>
            <Typography variant="h6" gutterBottom>
              {__('Logging Settings', 'flux-media')}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {__('Enable or disable system logging. When disabled, no new logs will be created and existing logs will not be displayed.', 'flux-media')}
            </Typography>
          </Grid>
          <Grid item>
            <FormControlLabel
              control={
                <Switch
                  checked={enableLogging}
                  onChange={handleLoggingToggle}
                  color="primary"
                />
              }
              label={__('Enable Logging', 'flux-media')}
            />
          </Grid>
        </Grid>
      </Box>

      {/* Show message when logging is disabled */}
      {!enableLogging && (
        <Alert severity="info" sx={{ mb: 3 }}>
          <Typography variant="body2">
            {__('Logging is currently disabled. Enable logging above to view system logs and start recording new log entries.', 'flux-media')}
          </Typography>
        </Alert>
      )}

      {/* Logs content - only show when logging is enabled */}
      {enableLogging && (
        <>
          <Grid container justifyContent="space-between" alignItems="center" sx={{ mb: 3 }}>
            <Grid item>
              <Typography variant="h6" gutterBottom>
                {__('Log Entries', 'flux-media')}
              </Typography>
            </Grid>
            <Grid item>
              <Tooltip title={__('Refresh logs', 'flux-media')}>
                <IconButton onClick={handleRefresh} disabled={isLoading}>
                  <Refresh />
                </IconButton>
              </Tooltip>
            </Grid>
          </Grid>

          {logsError && (
            <Alert severity="error" sx={{ mb: 3 }}>
              {__('Error loading logs:', 'flux-media')} {logsError?.message || __('Unknown error occurred', 'flux-media')}
            </Alert>
          )}

      {/* Filters */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid item xs={12} sm={6} md={3}>
          <TextField
            fullWidth
            label={__('Search logs', 'flux-media')}
            value={search}
            onChange={handleSearchChange}
            InputProps={{
              startAdornment: <Search sx={{ mr: 1, color: 'text.secondary' }} />,
            }}
            size="small"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <FormControl fullWidth size="small">
            <InputLabel>{__('Log Level', 'flux-media')}</InputLabel>
            <Select
              value={level}
              onChange={handleLevelChange}
              label={__('Log Level', 'flux-media')}
            >
              <MenuItem value="">{__('All Levels', 'flux-media')}</MenuItem>
              <MenuItem value="ERROR">{__('Error', 'flux-media')}</MenuItem>
              <MenuItem value="WARNING">{__('Warning', 'flux-media')}</MenuItem>
              <MenuItem value="CRITICAL">{__('Critical', 'flux-media')}</MenuItem>
            </Select>
          </FormControl>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <FormControl fullWidth size="small">
            <InputLabel>{__('Per Page', 'flux-media')}</InputLabel>
            <Select
              value={perPage}
              onChange={handlePerPageChange}
              label={__('Per Page', 'flux-media')}
            >
              <MenuItem value={10}>10</MenuItem>
              <MenuItem value={20}>20</MenuItem>
              <MenuItem value={50}>50</MenuItem>
              <MenuItem value={100}>100</MenuItem>
            </Select>
          </FormControl>
        </Grid>
      </Grid>

      {/* Logs Table */}
      <TableContainer component={Paper}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>{__('Level', 'flux-media')}</TableCell>
              <TableCell>{__('Message', 'flux-media')}</TableCell>
              <TableCell>{__('Context', 'flux-media')}</TableCell>
              <TableCell>{__('Date', 'flux-media')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {isLoading ? (
              // Loading skeleton
              Array.from({ length: perPage }).map((_, index) => (
                <TableRow key={index}>
                  <TableCell>
                    <Skeleton variant="rectangular" width={60} height={24} sx={{ borderRadius: 1 }} />
                  </TableCell>
                  <TableCell>
                    <Skeleton variant="text" width="80%" />
                  </TableCell>
                  <TableCell>
                    <Skeleton variant="text" width="60%" />
                  </TableCell>
                  <TableCell>
                    <Skeleton variant="text" width={120} />
                  </TableCell>
                </TableRow>
              ))
            ) : logsData?.logs?.length > 0 ? (
              logsData.logs.map((log) => (
                <TableRow key={log.id} hover>
                  <TableCell>
                    <Chip
                      label={log.level}
                      color={getLevelColor(log.level)}
                      size="small"
                    />
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2" sx={{ wordBreak: 'break-word' }}>
                      {log.message}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    {log.context && (
                      <Typography
                        variant="caption"
                        sx={{
                          fontFamily: 'monospace',
                          fontSize: '0.75rem',
                          wordBreak: 'break-all',
                          display: 'block',
                          maxWidth: 200,
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                          whiteSpace: 'nowrap',
                        }}
                        title={formatContext(log.context)}
                      >
                        {formatContext(log.context)}
                      </Typography>
                    )}
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2" color="text.secondary">
                      {formatDate(log.created_at)}
                    </Typography>
                  </TableCell>
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={4} align="center">
                  <Typography variant="body2" color="text.secondary">
                    {__('No logs found', 'flux-media')}
                  </Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>

      {/* Pagination */}
      {logsData?.pagination && logsData.pagination.total_pages > 1 && (
        <Box sx={{ display: 'flex', justifyContent: 'center', mt: 3 }}>
          <Pagination
            count={logsData.pagination.total_pages}
            page={page}
            onChange={handlePageChange}
            color="primary"
            showFirstButton
            showLastButton
          />
        </Box>
      )}

          {/* Pagination Info */}
          {logsData?.pagination && (
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 2 }}>
              <Typography variant="body2" color="text.secondary">
                {__('Showing', 'flux-media')} {((page - 1) * perPage) + 1} - {Math.min(page * perPage, logsData.pagination.total)} {__('of', 'flux-media')} {logsData.pagination.total} {__('logs', 'flux-media')}
              </Typography>
            </Box>
          )}
        </>
      )}
    </Box>
  );
};

export default LogsPage;
