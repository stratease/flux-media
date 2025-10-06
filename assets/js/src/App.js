import React from 'react';
import { HashRouter as Router, Routes, Route, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { Box, Typography, Container, Tabs, Tab, Paper, Grid } from '@mui/material';
import { __ } from '@wordpress/i18n';
import { ErrorBoundary, FluxMediaIcon } from '@flux-media/components';
import OverviewPage from '@flux-media/pages/OverviewPage';
import SettingsPage from '@flux-media/pages/SettingsPage';
import LogsPage from '@flux-media/components/pages/LogsPage';
import theme from '@flux-media/theme';
import { AutoSaveProvider } from '@flux-media/contexts/AutoSaveContext';

// Create a client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});


/**
 * Navigation component with tabs using React Router
 */
const Navigation = () => {
  const location = useLocation();
  const navigate = useNavigate();

  const getTabValue = (pathname) => {
    switch (pathname) {
      case '/overview':
        return 0;
      case '/settings':
        return 1;
      case '/logs':
        return 2;
      default:
        return 0;
    }
  };

  const handleTabChange = (event, newValue) => {
    const paths = ['/overview', '/settings', '/logs'];
    navigate(paths[newValue]);
  };

  return (
    <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 3 }}>
      <Grid container alignItems="center" sx={{ mb: 2 }}>
        <Grid item>
          <FluxMediaIcon size={40} sx={{ mr: 2 }} />
        </Grid>
        <Grid item>
          <Typography variant="h4" component="h1">
            {__('Flux Media', 'flux-media')}
          </Typography>
        </Grid>
      </Grid>
      <Tabs
        value={getTabValue(location.pathname)}
        onChange={handleTabChange}
        aria-label={__('Flux Media navigation tabs', 'flux-media')}
        textColor="primary"
        indicatorColor="primary"
      >
        <Tab label={__('Overview', 'flux-media')} />
        <Tab label={__('Settings', 'flux-media')} />
        <Tab label={__('Logs', 'flux-media')} />
      </Tabs>
    </Box>
  );
};

/**
 * Main App component with React Router
 */
const App = () => {
  // Handle initial route from WordPress admin menu
  React.useEffect(() => {
    const container = document.getElementById('flux-media-app');
    const initialHash = container?.dataset.initialHash;
    
    if (initialHash) {
      const hash = initialHash.startsWith('#') ? initialHash.slice(1) : initialHash;
      // Set the initial hash if it's different from current
      if (window.location.hash !== `#${hash}`) {
        window.location.hash = hash;
      }
    }
  }, []);

  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider theme={theme}>
          <CssBaseline />
          <AutoSaveProvider>
            <Router>
              <Container maxWidth="xl" sx={{ py: 4 }}>
                <Paper elevation={1} sx={{ p: 3 }}>
                  <Navigation />
                  <Routes>
                    <Route path="/overview" element={<OverviewPage />} />
                    <Route path="/settings" element={<SettingsPage />} />
                    <Route path="/logs" element={<LogsPage />} />
                    <Route path="/" element={<Navigate to="/overview" replace />} />
                  </Routes>
                </Paper>
              </Container>
            </Router>
          </AutoSaveProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </ErrorBoundary>
  );
};

export default App;
