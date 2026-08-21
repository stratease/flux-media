/**
 * Attachment details React island entry.
 *
 * Mounts AttachmentDetails panels with TanStack Query and keeps deprecated
 * global AJAX helpers as thin delegates for external compatibility.
 *
 * Classic edit mounts via PHP attachment_fields_to_edit. Media modals inject
 * the island after `.attachment-info` (not inside compat `td.field`).
 *
 * @since 4.3.0
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider } from '@mui/material/styles';
import theme from '@flux-plugins-common/theme';
import AttachmentDetails from '../attachment/AttachmentDetails';
import {
  convertAttachment,
  disableConversion,
  enableConversion,
} from '../attachment/attachmentApi';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

/**
 * Convert a specific attachment via AJAX.
 *
 * @deprecated 4.3.0 Prefer React panel actions; retained for external callers.
 * @since 0.1.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<boolean>}
 */
export function fluxMediaConvertAttachment(attachmentId) {
  return convertAttachment(attachmentId)
    .then(() => true)
    .catch((error) => {
      window.alert(error?.message || 'Conversion request failed');
      return false;
    });
}

/**
 * Disable conversion for an attachment.
 *
 * @deprecated 4.3.0 Prefer React panel actions; retained for external callers.
 * @since 0.1.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<boolean>}
 */
export function fluxMediaDisableConversion(attachmentId) {
  return disableConversion(attachmentId)
    .then(() => true)
    .catch(() => false);
}

/**
 * Enable conversion for an attachment.
 *
 * @deprecated 4.3.0 Prefer React panel actions; retained for external callers.
 * @since 0.1.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Promise<boolean>}
 */
export function fluxMediaEnableConversion(attachmentId) {
  return enableConversion(attachmentId)
    .then(() => true)
    .catch(() => false);
}

window.fluxMediaConvertAttachment = fluxMediaConvertAttachment;
window.fluxMediaDisableConversion = fluxMediaDisableConversion;
window.fluxMediaEnableConversion = fluxMediaEnableConversion;

/**
 * Resolve attachment ID from a mount node.
 *
 * @since 4.3.0
 * @param {Element} mountNode Mount element.
 * @returns {number}
 */
function resolveAttachmentId(mountNode) {
  const fromMount = mountNode.getAttribute('data-flux-media-attachment-id');
  if (fromMount) {
    return parseInt(fromMount, 10) || 0;
  }

  const rootEl = mountNode.closest('[data-flux-media-attachment-root="1"]');
  const fromRoot = rootEl?.getAttribute('data-flux-media-attachment-id');
  return fromRoot ? parseInt(fromRoot, 10) || 0 : 0;
}

/**
 * Build mount markup matching AttachmentDetailsMountRenderer::build_mount_html.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {HTMLElement} Root element ready to insert.
 */
function createMountRoot(attachmentId) {
  const id = String(attachmentId);
  const root = document.createElement('div');
  root.className = 'flux-media-optimizer-attachment-root';
  root.setAttribute('data-flux-media-attachment-root', '1');
  root.setAttribute('data-flux-media-attachment-id', id);

  const app = document.createElement('div');
  app.id = `flux-media-optimizer-attachment-${id}`;
  app.className = 'flux-media-optimizer-attachment-app';
  app.setAttribute('data-flux-media-attachment-app', '1');
  app.setAttribute('data-flux-media-attachment-id', id);
  app.innerHTML =
    '<div class="flux-media-optimizer-attachment-skeleton" data-flux-media-attachment-skeleton="1" aria-hidden="true">' +
    '<div class="flux-media-optimizer-attachment-skeleton__header"></div>' +
    '<div class="flux-media-optimizer-attachment-skeleton__row"></div>' +
    '<div class="flux-media-optimizer-attachment-skeleton__row"></div>' +
    '<div class="flux-media-optimizer-attachment-skeleton__row flux-media-optimizer-attachment-skeleton__row--short"></div>' +
    '</div>';

  root.appendChild(app);
  return root;
}

/**
 * Resolve the selected attachment ID for a media modal details pane.
 *
 * @since 4.3.0
 * @param {Element} detailsEl `.attachment-details` element.
 * @returns {number}
 */
function resolveModalAttachmentId(detailsEl) {
  const selected = document.querySelector(
    '.media-modal .attachment.selected, .media-frame .attachment.selected, .attachments-browser .attachment.selected'
  );
  const selectedId = selected?.getAttribute('data-id');
  if (selectedId) {
    return parseInt(selectedId, 10) || 0;
  }

  try {
    const frame = window.wp?.media?.frame;
    if (frame?.state) {
      const selection = frame.state().get('selection');
      const model =
        (selection?.single && selection.single()) ||
        (selection?.first && selection.first()) ||
        null;
      const json = model?.toJSON ? model.toJSON() : null;
      if (json?.id) {
        return parseInt(json.id, 10) || 0;
      }
    }
  } catch (error) {
    // Frame may be mid-transition; fall through.
  }

  const dataId = detailsEl.getAttribute('data-id');
  if (dataId) {
    return parseInt(dataId, 10) || 0;
  }

  return 0;
}

/**
 * Inject (or refresh) the Flux island into the media modal attachment details.
 *
 * Places the root after `.attachment-info` so it uses full sidebar content width
 * instead of the compat `td.field` table cell.
 *
 * @since 4.3.0
 * @param {Element} detailsEl `.attachment-details` element inside a media frame.
 * @returns {void}
 */
function ensureModalAttachmentMount(detailsEl) {
  if (!(detailsEl instanceof Element)) {
    return;
  }

  if (!detailsEl.classList.contains('attachment-details')) {
    detailsEl = detailsEl.closest('.attachment-details');
  }
  if (!detailsEl || !detailsEl.closest('.media-modal, .media-frame')) {
    return;
  }

  // Classic post.php edit is not a media frame; PHP field handles that surface.
  if (detailsEl.closest('#post-body, .wp-admin.post-type-attachment #post')) {
    return;
  }

  const attachmentId = resolveModalAttachmentId(detailsEl);
  if (!attachmentId) {
    return;
  }

  const existing = detailsEl.querySelector(':scope > [data-flux-media-attachment-root="1"]');
  if (existing) {
    const existingId = parseInt(existing.getAttribute('data-flux-media-attachment-id'), 10) || 0;
    if (existingId === attachmentId) {
      mountAttachmentIslands(existing);
      return;
    }
    existing.remove();
  }

  // Remove any stale compat-table mount if a theme/plugin still rendered one.
  detailsEl
    .querySelectorAll(
      'table.compat-attachment-fields [data-flux-media-attachment-root="1"]'
    )
    .forEach((node) => node.remove());

  const root = createMountRoot(attachmentId);
  const info = detailsEl.querySelector(':scope > .attachment-info, .attachment-info');
  if (info && detailsEl.contains(info)) {
    info.insertAdjacentElement('afterend', root);
  } else {
    detailsEl.insertBefore(root, detailsEl.firstChild);
  }

  mountAttachmentIslands(root);
}

/**
 * Scan for media-modal attachment details and ensure mounts.
 *
 * @since 4.3.0
 * @param {ParentNode} [root=document] Scan root.
 * @returns {void}
 */
function ensureModalMountsIn(root = document) {
  const scope = root.querySelectorAll ? root : document;
  const detailsList = [];

  if (scope instanceof Element) {
    if (scope.matches('.attachment-details')) {
      detailsList.push(scope);
    }
    if (scope.querySelectorAll) {
      scope.querySelectorAll('.media-modal .attachment-details, .media-frame .attachment-details').forEach((el) => {
        detailsList.push(el);
      });
    }
  } else if (scope.querySelectorAll) {
    scope
      .querySelectorAll('.media-modal .attachment-details, .media-frame .attachment-details')
      .forEach((el) => detailsList.push(el));
  }

  detailsList.forEach((detailsEl) => ensureModalAttachmentMount(detailsEl));
}

/**
 * Mount React islands for each attachment details root in a container.
 *
 * Idempotent: skips nodes already marked `data-flux-mounted`.
 *
 * @since 4.3.0
 * @param {ParentNode} [root=document] Scan root (document or mutation subtree).
 * @returns {void}
 */
function mountAttachmentIslands(root = document) {
  const roots = root.querySelectorAll
    ? root.querySelectorAll('[data-flux-media-attachment-app="1"]')
    : [];
  roots.forEach((mountNode) => {
    if (mountNode.dataset.fluxMounted === '1') {
      return;
    }

    const attachmentId = resolveAttachmentId(mountNode);
    if (!attachmentId) {
      return;
    }

    mountNode.dataset.fluxMounted = '1';
    const reactRoot = createRoot(mountNode);
    reactRoot.render(
      <QueryClientProvider client={queryClient}>
        <ThemeProvider theme={theme}>
          <AttachmentDetails attachmentId={attachmentId} />
        </ThemeProvider>
      </QueryClientProvider>
    );
  });
}

/**
 * Observe DOM for media-modal / grid attachment fields injected after load.
 *
 * Prefer MutationObserver over patching wp.media.view.Attachment.Details so
 * editor modals and classic screens mount without WordPress view inheritance.
 *
 * @since 4.3.0
 * @returns {void}
 */
function observeAttachmentMountPoints() {
  if (typeof MutationObserver === 'undefined' || !document.body) {
    return;
  }

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) {
          return;
        }
        if (node.matches('[data-flux-media-attachment-app="1"]')) {
          mountAttachmentIslands(node.parentNode || document);
          return;
        }
        if (node.querySelector('[data-flux-media-attachment-app="1"]')) {
          mountAttachmentIslands(node);
        }
        if (
          node.matches('.attachment-details') ||
          node.querySelector('.attachment-details')
        ) {
          ensureModalMountsIn(node);
        }
      });
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
}

function bootAttachmentIslands() {
  mountAttachmentIslands();
  ensureModalMountsIn(document);
  observeAttachmentMountPoints();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootAttachmentIslands);
} else {
  bootAttachmentIslands();
}

export {
  mountAttachmentIslands,
  observeAttachmentMountPoints,
  ensureModalAttachmentMount,
  ensureModalMountsIn,
};
