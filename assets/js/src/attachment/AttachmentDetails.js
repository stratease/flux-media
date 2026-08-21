import React, { useEffect, useMemo, useState } from 'react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Skeleton from '@mui/material/Skeleton';
import Typography from '@mui/material/Typography';
import BrandIcon from '@flux-plugins-common/components/PageLayout/BrandIcon';
import SizeAccordionRow from './SizeAccordionRow';
import OverflowTooltipText from './OverflowTooltipText';
import { ATTACHMENT_PANEL_COMFORTABLE_QUERY } from './attachmentLayout';
import { useAttachmentActions, useAttachmentDetails } from './useAttachmentDetails';

/**
 * Compact loading skeleton while details fetch.
 *
 * @since 4.3.0
 * @returns {JSX.Element}
 */
const AttachmentDetailsSkeleton = () => (
  <Box
    className="flux-media-optimizer-attachment-details"
    data-flux-media-attachment-skeleton="1"
    sx={{
      background: '#fff',
      border: '1px solid #dcdcde',
      borderRadius: '4px',
      p: 2,
      my: 1,
      maxWidth: '100%',
      boxSizing: 'border-box',
    }}
  >
    <Skeleton variant="rounded" height={18} width="45%" sx={{ mb: 2 }} />
    <Skeleton variant="rounded" height={12} sx={{ mb: 1 }} />
    <Skeleton variant="rounded" height={12} sx={{ mb: 1 }} />
    <Skeleton variant="rounded" height={12} width="70%" />
  </Box>
);

/**
 * Flux Media Optimizer attachment details panel.
 *
 * Layout density follows parent container width via CSS container queries
 * (compact at ≤480px), not the browser viewport.
 *
 * @since 4.3.0
 * @param {Object} props Component props.
 * @param {number} props.attachmentId Attachment ID.
 * @returns {JSX.Element}
 */
const AttachmentDetails = ({ attachmentId }) => {
  const { data: payload, isLoading, isError, error, refetch, isFetching } =
    useAttachmentDetails(attachmentId);
  const actionsApi = useAttachmentActions(attachmentId);
  const sizes = payload?.sizes || [];
  const [expanded, setExpanded] = useState({});
  const [busy, setBusy] = useState('');

  const columns = payload?.columns || {};
  const formatColumns = payload?.formatColumns || [];
  const labels = payload?.labels || {};
  const actions = (payload && payload['actions']) || {};
  const firstSizeName = sizes[0]?.name;
  const processing = Boolean(payload?.processing);
  const actionsLocked = Boolean(busy) || processing || actionsApi.isBusy;
  const titleText = payload?.title || 'Flux Media Optimizer';

  useEffect(() => {
    if (!sizes.length) {
      return;
    }
    setExpanded((prev) => {
      if (Object.keys(prev).length) {
        return prev;
      }
      return { [sizes[0].name]: true };
    });
  }, [sizes]);

  const toggleRow = (name) => {
    setExpanded((prev) => ({
      ...prev,
      [name]: !prev[name],
    }));
  };

  const runAction = async (key, handler) => {
    if (!handler || busy || processing || actionsApi.isBusy) {
      return;
    }
    setBusy(key);
    try {
      await handler();
    } finally {
      setBusy('');
    }
  };

  const header = useMemo(
    () => (
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          gap: 1.5,
          mb: 2,
          pb: 1.5,
          borderBottom: '1px solid',
          borderColor: 'divider',
          minWidth: 0,
          maxWidth: '100%',
        }}
        data-flux-media-attachment-header="1"
      >
        <BrandIcon size={payload?.brandIconSize || 28} />
        <OverflowTooltipText
          text={titleText}
          component="h3"
          data-flux-media-attachment-title="1"
          sx={{ m: 0, fontSize: '16px', fontWeight: 600, lineHeight: 1.2, flex: 1 }}
        />
      </Box>
    ),
    [payload?.brandIconSize, titleText]
  );

  if (isLoading && !payload) {
    return <AttachmentDetailsSkeleton />;
  }

  if (isError && !payload) {
    return (
      <Box
        className="flux-media-optimizer-attachment-details"
        data-flux-media-attachment-details="1"
        sx={{
          background: '#fff',
          border: '1px solid #dcdcde',
          borderRadius: '4px',
          p: 2,
          my: 1,
          maxWidth: '100%',
          boxSizing: 'border-box',
        }}
      >
        <Alert
          severity="error"
          action={
            <Button color="inherit" size="small" onClick={() => refetch()}>
              {labels.retry || 'Retry'}
            </Button>
          }
          data-flux-media-attachment-load-error="1"
        >
          {error?.message || labels.loadError || 'Unable to load optimization details.'}
        </Alert>
      </Box>
    );
  }

  return (
    <Box
      className="flux-media-optimizer-attachment-details"
      data-flux-media-attachment-details="1"
      sx={{
        background: '#fff',
        border: '1px solid #dcdcde',
        borderRadius: '4px',
        p: 2,
        my: 1,
        fontSize: '13px',
        maxWidth: '100%',
        width: '100%',
        boxSizing: 'border-box',
        overflow: 'hidden',
        minWidth: 0,
      }}
    >
      {header}

      {payload?.error ? (
        <Alert severity="error" sx={{ mb: 2 }} data-flux-media-attachment-error="1">
          {payload.error}
          {payload.retryText ? ` (${payload.retryText})` : ''}
        </Alert>
      ) : null}

      {actionsApi.error ? (
        <Alert severity="error" sx={{ mb: 2 }} data-flux-media-attachment-action-error="1">
          {actionsApi.error}
        </Alert>
      ) : null}

      {processing ? (
        <Alert severity="info" sx={{ mb: 2 }} data-flux-media-attachment-pending="1">
          {payload?.statusLabel || 'Pending'}
          {isFetching ? ` — ${labels.pendingRefresh || 'Checking for updates…'}` : ''}
        </Alert>
      ) : null}

      {sizes.length > 0 ? (
        <Box data-flux-media-attachment-sizes="1" sx={{ maxWidth: '100%', minWidth: 0 }}>
          <Box
            sx={{
              display: 'none',
              px: 1,
              pb: 1,
              color: '#646970',
              fontWeight: 600,
              fontSize: '12px',
              maxWidth: '100%',
              [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
                display: 'block',
              },
            }}
            data-flux-media-attachment-column-headers="1"
          >
            <Box
              sx={{
                display: 'flex',
                alignItems: 'center',
                gap: 1,
                minWidth: 0,
                maxWidth: '100%',
              }}
            >
              <Box sx={{ flex: 1, minWidth: 0 }}>
                <span>{columns.mediaSize || columns.imageSize || 'Image size'}</span>
              </Box>
              <Box sx={{ flex: '0 0 auto', width: 72, minWidth: 0 }}>
                <span>{columns.original || 'Original'}</span>
              </Box>
              {formatColumns.map((column) => (
                <Box key={column.key} sx={{ flex: '0 0 auto', width: 72, minWidth: 0 }}>
                  <span style={{ color: column.color }}>{column.label}</span>
                </Box>
              ))}
              <Box sx={{ flex: '0 0 auto' }}>
                <span>{columns.savings || 'Savings'}</span>
              </Box>
              <Box sx={{ flex: '0 0 auto', width: 32 }} />
            </Box>
          </Box>

          {sizes.map((size) => (
            <SizeAccordionRow
              key={size.name}
              size={size}
              formatColumns={formatColumns}
              labels={labels}
              expanded={Boolean(expanded[size.name])}
              onToggle={() => toggleRow(size.name)}
              showUpsell={Boolean(payload.showUpsell) && size.name === firstSizeName}
              upsellUrl={payload.upsellUrl}
              upsellMessage={payload.upsellMessage}
              upsellLinkLabel={payload.upsellLinkLabel}
            />
          ))}
        </Box>
      ) : (
        <Typography sx={{ color: '#646970', mb: 2 }} data-flux-media-attachment-empty="1">
          {payload?.statusLabel || labels.empty || 'No conversions yet'}
          {payload?.retryText ? ` — ${payload.retryText}` : ''}
        </Typography>
      )}

      {payload?.showUpsell && sizes.length === 0 ? (
        <Box sx={{ mb: 2 }} data-flux-media-cdn-upsell-wrap="1">
          <Box
            sx={{
              display: 'flex',
              gap: 1,
              alignItems: 'flex-start',
              p: 1.25,
              borderRadius: 1,
              background: '#e8f4fc',
              border: '1px solid #b3d9ff',
              fontSize: '12px',
              color: '#1d2327',
              maxWidth: '100%',
            }}
            data-flux-media-cdn-upsell="1"
          >
            <Typography sx={{ fontSize: '12px', m: 0, wordBreak: 'break-word' }}>
              {payload.upsellMessage}{' '}
              <a
                href={payload.upsellUrl}
                target="_blank"
                rel="noopener noreferrer"
                style={{ color: '#2271b1', fontWeight: 600 }}
                data-flux-media-cdn-upsell-link="1"
              >
                {payload.upsellLinkLabel}
              </a>
              .
            </Typography>
          </Box>
        </Box>
      ) : null}

      <Box
        sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', mt: 2 }}
        data-flux-media-attachment-actions="1"
      >
        {actions.canConvert ? (
          <Button
            variant="contained"
            size="small"
            disabled={actionsLocked}
            onClick={() => runAction('convert', actionsApi.convert)}
            data-flux-media-action="convert"
          >
            {busy === 'convert' || processing ? '…' : actions.convertLabel}
          </Button>
        ) : processing ? (
          <Button variant="contained" size="small" disabled data-flux-media-action="processing">
            {actions.convertLabel || 'Processing…'}
          </Button>
        ) : null}
        {actions.canDisable ? (
          <Button
            variant="outlined"
            size="small"
            disabled={actionsLocked}
            onClick={() => runAction('disable', actionsApi.disable)}
            data-flux-media-action="disable"
          >
            {actions.disableLabel}
          </Button>
        ) : null}
        {actions.canEnable ? (
          <Button
            variant="contained"
            color="success"
            size="small"
            disabled={actionsLocked}
            onClick={() => runAction('enable', actionsApi.enable)}
            data-flux-media-action="enable"
          >
            {actions.enableLabel}
          </Button>
        ) : null}
      </Box>
    </Box>
  );
};

export default AttachmentDetails;
