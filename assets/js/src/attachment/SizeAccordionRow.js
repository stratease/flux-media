import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import Collapse from '@mui/material/Collapse';
import OpenInNewIcon from '@mui/icons-material/OpenInNew';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { ATTACHMENT_PANEL_COMFORTABLE_QUERY } from './attachmentLayout';

/**
 * Format size label for summary cells.
 *
 * @since 4.3.0
 * @param {Object|null} variant Format variant.
 * @returns {string}
 */
function variantSizeLabel(variant) {
  if (!variant) {
    return '—';
  }
  return variant.sizeLabel || '—';
}

/**
 * Compact-mode field label; hidden when the container is comfortable width.
 *
 * @since 4.3.0
 * @param {Object} props Props.
 * @param {string} props.children Label text.
 * @returns {JSX.Element}
 */
const CompactFieldLabel = ({ children }) => (
  <Typography
    data-flux-media-compact-label="1"
    sx={{
      fontSize: '11px',
      color: '#646970',
      display: 'block',
      [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
        display: 'none',
      },
    }}
  >
    {children}
  </Typography>
);

/**
 * One accordion row for an image or video size.
 *
 * Summary and detail density follow parent container width via CSS container
 * queries (compact at ≤480px), not viewport breakpoints.
 *
 * @since 4.3.0
 * @param {Object} props Props.
 * @returns {JSX.Element}
 */
const SizeAccordionRow = ({
  size,
  formatColumns,
  labels,
  expanded,
  onToggle,
  showUpsell,
  upsellUrl,
  upsellMessage,
  upsellLinkLabel,
}) => {
  const [copied, setCopied] = useState('');
  const columns = Array.isArray(formatColumns) ? formatColumns : [];
  const coreBadgeLabel = labels?.coreBadge || 'Core';

  const copyUrl = async (key, url) => {
    if (!url || !navigator.clipboard) {
      return;
    }
    try {
      await navigator.clipboard.writeText(url);
      setCopied(key);
      window.setTimeout(() => setCopied(''), 1500);
    } catch (error) {
      // Ignore clipboard failures in restricted contexts.
    }
  };

  const renderVariantCard = (variant, color, bg) => {
    if (!variant) {
      return (
        <Box
          sx={{
            p: 1.5,
            border: '1px dashed #dcdcde',
            borderRadius: 1,
            color: '#646970',
            minWidth: 0,
            maxWidth: '100%',
            width: '100%',
            boxSizing: 'border-box',
          }}
        >
          —
        </Box>
      );
    }

    return (
      <Box
        sx={{
          p: 1.5,
          borderRadius: 1,
          border: `1px solid ${color}33`,
          background: bg,
          minWidth: 0,
          maxWidth: '100%',
          width: '100%',
          boxSizing: 'border-box',
        }}
        data-flux-media-variant={variant.format}
      >
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1, minWidth: 0 }}>
          <Chip
            label={variant.label}
            size="small"
            sx={{ bgcolor: color, color: '#fff', fontWeight: 600, height: 22 }}
          />
          <Typography sx={{ color, fontWeight: 600, fontSize: '13px' }}>
            {variant.sizeLabel}
          </Typography>
        </Box>
        <Typography sx={{ fontSize: '11px', color: '#646970', mb: 0.5 }}>
          {labels?.url || 'URL'}
        </Typography>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, minWidth: 0 }}>
          <Typography
            component="span"
            sx={{
              fontSize: '11px',
              color: '#2c3338',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap',
              flex: 1,
              minWidth: 0,
            }}
            title={variant.url}
          >
            {variant.url || '—'}
          </Typography>
          {variant.url ? (
            <>
              <IconButton
                size="small"
                component="a"
                href={variant.url}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={labels?.openUrl || 'Open in new tab'}
                data-flux-media-url-open={variant.format}
              >
                <OpenInNewIcon fontSize="inherit" />
              </IconButton>
              <IconButton
                size="small"
                onClick={() => copyUrl(variant.format, variant.url)}
                aria-label={labels?.copyUrl || 'Copy to clipboard'}
                data-flux-media-url-copy={variant.format}
              >
                <ContentCopyIcon fontSize="inherit" />
              </IconButton>
            </>
          ) : null}
        </Box>
        {copied === variant.format ? (
          <Typography sx={{ fontSize: '11px', color: '#00a32a', mt: 0.5 }}>
            {labels?.copied || 'Copied'}
          </Typography>
        ) : null}
      </Box>
    );
  };

  const summaryCellSx = {
    minWidth: 0,
    maxWidth: '100%',
    width: '100%',
    [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
      width: 'auto',
      flex: '0 0 auto',
    },
  };

  return (
    <Box
      sx={{ borderTop: '1px solid #dcdcde', maxWidth: '100%' }}
      data-flux-media-size-row={size.name}
      data-flux-media-size-expanded={expanded ? '1' : '0'}
    >
      <Box
        role="button"
        tabIndex={0}
        onClick={onToggle}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            onToggle();
          }
        }}
        sx={{
          px: 1,
          py: 1.25,
          cursor: 'pointer',
          maxWidth: '100%',
          '&:hover': { background: '#f6f7f7' },
        }}
        data-flux-media-size-summary="1"
      >
        <Box
          sx={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'stretch',
            gap: 1,
            maxWidth: '100%',
            minWidth: 0,
            [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
              flexDirection: 'row',
              alignItems: 'center',
              flexWrap: 'nowrap',
            },
          }}
        >
          <Box
            sx={{
              ...summaryCellSx,
              [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
                flex: '1 1 auto',
                width: 'auto',
                minWidth: 0,
              },
            }}
          >
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, minWidth: 0, maxWidth: '100%' }}>
              <Typography
                sx={{
                  fontWeight: 600,
                  fontSize: '13px',
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  whiteSpace: 'nowrap',
                  minWidth: 0,
                  flex: 1,
                }}
                title={
                  size.width && size.height
                    ? `${size.label} (${size.width}×${size.height})`
                    : size.label
                }
              >
                {size.label}
                {size.width && size.height ? ` (${size.width}×${size.height})` : ''}
              </Typography>
              {size.source === 'core' ? (
                <Tooltip title={coreBadgeLabel}>
                  <Chip
                    label={coreBadgeLabel}
                    size="small"
                    color="primary"
                    aria-label={coreBadgeLabel}
                    sx={{
                      height: 20,
                      fontSize: '11px',
                      maxWidth: 96,
                      flexShrink: 0,
                      '& .MuiChip-label': {
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        display: 'block',
                        px: 0.75,
                      },
                    }}
                  />
                </Tooltip>
              ) : null}
            </Box>
          </Box>

          <Box
            sx={{
              ...summaryCellSx,
              [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
                width: 72,
                flex: '0 0 auto',
              },
            }}
          >
            <CompactFieldLabel>Original</CompactFieldLabel>
            <Typography sx={{ fontSize: '12px', color: '#646970' }}>
              {variantSizeLabel(size.original)}
            </Typography>
          </Box>

          {columns.map((column) => (
            <Box
              key={column.key}
              sx={{
                ...summaryCellSx,
                [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
                  width: 72,
                  flex: '0 0 auto',
                },
              }}
            >
              <CompactFieldLabel>{column.label}</CompactFieldLabel>
              <Typography sx={{ fontSize: '12px', color: column.color, fontWeight: 600 }}>
                {variantSizeLabel(size.variants?.[column.key])}
              </Typography>
            </Box>
          ))}

          <Box sx={summaryCellSx}>
            <CompactFieldLabel>Savings</CompactFieldLabel>
            {size.savingsLabel ? (
              <Chip
                label={size.savingsLabel}
                size="small"
                sx={{
                  bgcolor: size.savingsPercent > 0 ? '#e8f5e8' : '#ffebee',
                  color: size.savingsPercent > 0 ? '#2e7d32' : '#c62828',
                  fontWeight: 600,
                  height: 22,
                  maxWidth: '100%',
                }}
                data-flux-media-savings="1"
              />
            ) : (
              <Typography sx={{ fontSize: '12px', color: '#646970' }}>—</Typography>
            )}
          </Box>

          <Box
            sx={{
              alignSelf: 'flex-end',
              [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
                alignSelf: 'center',
                ml: 0,
                flex: '0 0 auto',
              },
            }}
          >
            <IconButton
              size="small"
              aria-expanded={expanded}
              aria-label={expanded ? labels?.collapse || 'Collapse' : labels?.expand || 'Expand'}
              sx={{
                transform: expanded ? 'rotate(180deg)' : 'rotate(0deg)',
                transition: 'transform 0.2s',
              }}
            >
              <ExpandMoreIcon fontSize="small" />
            </IconButton>
          </Box>
        </Box>
      </Box>

      <Collapse in={expanded}>
        <Box
          sx={{
            p: 1.5,
            background: '#fafafa',
            maxWidth: '100%',
            overflow: 'hidden',
          }}
          data-flux-media-size-details="1"
        >
          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: 'minmax(0, 1fr)',
              gap: 1.5,
              maxWidth: '100%',
              [ATTACHMENT_PANEL_COMFORTABLE_QUERY]: {
                gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
              },
            }}
          >
            <Box sx={{ minWidth: 0, maxWidth: '100%' }}>
              {renderVariantCard(size.original, '#646970', '#f0f0f1')}
            </Box>
            {columns.map((column) => (
              <Box key={column.key} sx={{ minWidth: 0, maxWidth: '100%' }}>
                {renderVariantCard(
                  size.variants?.[column.key],
                  column.color,
                  `${column.color}14`
                )}
              </Box>
            ))}
            <Box sx={{ minWidth: 0, maxWidth: '100%' }}>
              {size.savingsLabel ? (
                <Box sx={{ mb: 1.5 }}>
                  <Chip
                    label={size.savingsLabel}
                    sx={{
                      bgcolor: size.savingsPercent > 0 ? '#e8f5e8' : '#ffebee',
                      color: size.savingsPercent > 0 ? '#2e7d32' : '#c62828',
                      fontWeight: 700,
                      mb: 0.5,
                    }}
                    data-flux-media-savings-detail="1"
                  />
                  {size.savedLabel ? (
                    <Typography sx={{ fontSize: '12px', color: '#646970' }}>
                      {size.savedLabel}
                    </Typography>
                  ) : null}
                </Box>
              ) : null}

              {showUpsell ? (
                <AlertLikeUpsell
                  url={upsellUrl}
                  message={upsellMessage}
                  linkLabel={upsellLinkLabel}
                />
              ) : null}
            </Box>
          </Box>
        </Box>
      </Collapse>
    </Box>
  );
};

/**
 * CDN upsell notice for the first expanded size row.
 *
 * @since 4.3.0
 * @param {Object} props Props.
 * @returns {JSX.Element}
 */
const AlertLikeUpsell = ({ url, message, linkLabel }) => (
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
    <InfoOutlinedIcon sx={{ color: '#2271b1', fontSize: 18, mt: '1px', flexShrink: 0 }} />
    <Typography sx={{ fontSize: '12px', m: 0, wordBreak: 'break-word' }}>
      {message}{' '}
      <a
        href={url}
        target="_blank"
        rel="noopener noreferrer"
        style={{ color: '#2271b1', fontWeight: 600 }}
        data-flux-media-cdn-upsell-link="1"
      >
        {linkLabel}
      </a>
      .
    </Typography>
  </Box>
);

export default SizeAccordionRow;
