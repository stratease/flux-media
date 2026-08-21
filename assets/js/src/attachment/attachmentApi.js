/**
 * Attachment-details API helpers for the self-contained island bundle.
 *
 * Uses fetch + localized REST/AJAX config from fluxMediaAdmin (no @wordpress/*).
 *
 * @since 4.3.0
 */

/**
 * Resolve REST root URL for plugin endpoints.
 *
 * @since 4.3.0
 * @returns {string}
 */
function getRestRoot() {
  const root = typeof fluxMediaAdmin !== 'undefined' ? fluxMediaAdmin.apiUrl : '';
  if (!root) {
    return '/wp-json/flux-media-optimizer/v1/';
  }
  return root.endsWith('/') ? root : `${root}/`;
}

/**
 * Fetch attachment optimization details.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<Object>}
 */
export async function fetchAttachmentDetails(attachmentId) {
  const nonce = typeof fluxMediaAdmin !== 'undefined' ? fluxMediaAdmin.nonce : '';
  const response = await fetch(`${getRestRoot()}attachments/${attachmentId}/details`, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': nonce || '',
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    const message = `HTTP ${response.status}`;
    throw new Error(message);
  }

  const payload = await response.json();
  if (payload && typeof payload === 'object' && payload.success !== undefined) {
    if (!payload.success) {
      throw new Error(payload.message || 'Failed to load attachment details');
    }
    return payload.data;
  }

  return payload;
}

/**
 * Post an attachment AJAX action.
 *
 * @since 4.3.0
 * @param {string} action AJAX action name.
 * @param {string} nonce Localized nonce.
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<Object>}
 */
async function postAttachmentAction(action, nonce, attachmentId) {
  if (!attachmentId || typeof fluxMediaAdmin === 'undefined') {
    throw new Error('Missing attachment configuration');
  }

  const response = await fetch(fluxMediaAdmin.ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      action,
      attachment_id: String(attachmentId),
      nonce,
    }),
  });

  const result = await response.json();
  if (!result.success) {
    throw new Error(result.data || 'Action failed');
  }

  return result.data;
}

/**
 * Convert or re-convert an attachment.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<Object>}
 */
export function convertAttachment(attachmentId) {
  return postAttachmentAction(
    'flux_media_optimizer_convert_attachment',
    fluxMediaAdmin.convertNonce,
    attachmentId
  );
}

/**
 * Disable conversion for an attachment.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<Object>}
 */
export function disableConversion(attachmentId) {
  return postAttachmentAction(
    'flux_media_optimizer_disable_conversion',
    fluxMediaAdmin.disableNonce,
    attachmentId
  );
}

/**
 * Enable conversion for an attachment.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<Object>}
 */
export function enableConversion(attachmentId) {
  return postAttachmentAction(
    'flux_media_optimizer_enable_conversion',
    fluxMediaAdmin.enableNonce,
    attachmentId
  );
}
