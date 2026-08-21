import React from 'react';
import Box from '@mui/material/Box';
import Tooltip from '@mui/material/Tooltip';

/**
 * Single-line ellipsis text with tooltip and aria-label for the full string.
 *
 * @since 4.3.0
 * @param {Object} props Component props.
 * @param {string} props.text Full text shown truncated and in tooltip/aria.
 * @param {string} [props.component='span'] Root element type.
 * @param {Object} [props.sx] Additional MUI sx styles.
 * @returns {JSX.Element}
 */
const OverflowTooltipText = ({ text, component = 'span', sx = {}, ...rest }) => {
  const label = text == null ? '' : String(text);

  return (
    <Tooltip title={label}>
      <Box
        component={component}
        sx={{
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap',
          minWidth: 0,
          maxWidth: '100%',
          ...sx,
        }}
        aria-label={label}
        {...rest}
      >
        {label}
      </Box>
    </Tooltip>
  );
};

export default OverflowTooltipText;
