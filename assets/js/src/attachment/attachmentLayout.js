/**
 * Attachment panel layout constants (container-query SSOT).
 *
 * Compact density applies when the WordPress parent container is at or below
 * ATTACHMENT_PANEL_COMPACT_MAX (not the browser viewport).
 *
 * @since 4.3.0
 */

/** @type {number} Max container inline size (px) for compact stacked layout. */
export const ATTACHMENT_PANEL_COMPACT_MAX = 480;

/**
 * CSS container query for comfortable (non-compact) attachment layout.
 *
 * @since 4.3.0
 * @type {string}
 */
export const ATTACHMENT_PANEL_COMFORTABLE_QUERY = `@container (min-width: ${
  ATTACHMENT_PANEL_COMPACT_MAX + 1
}px)`;
