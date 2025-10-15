/**
 * Attachment page JavaScript functionality for Flux Media plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

/**
 * Convert a specific attachment.
 *
 * @since 0.1.0
 * @param {number} attachmentId The attachment ID to convert.
 */
function fluxMediaConvertAttachment(attachmentId) {
    if (!attachmentId) {
        alert('Invalid attachment ID');
        return;
    }

    // Show loading state
    const button = event.target;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Converting...';

    // Make AJAX request
    const data = {
        action: 'flux_media_convert_attachment',
        attachment_id: attachmentId,
        nonce: fluxMediaAdmin.convertNonce
    };

    fetch(fluxMediaAdmin.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Show success message
            showNotice('Conversion completed successfully!', 'success');
            // Reload the page to show updated conversion status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            showNotice('Conversion failed: ' + (result.data || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Conversion error:', error);
        showNotice('Conversion failed: Network error', 'error');
    })
    .finally(() => {
        // Restore button state
        button.disabled = false;
        button.textContent = originalText;
    });
}

/**
 * Disable conversion for a specific attachment.
 *
 * @since 0.1.0
 * @param {number} attachmentId The attachment ID to disable conversion for.
 */
function fluxMediaDisableConversion(attachmentId) {
    if (!attachmentId) {
        alert('Invalid attachment ID');
        return;
    }

    if (!confirm('Are you sure you want to disable conversion for this attachment? This will prevent it from being processed in future bulk operations.')) {
        return;
    }

    // Show loading state
    const button = event.target;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Disabling...';

    // Make AJAX request
    const data = {
        action: 'flux_media_disable_conversion',
        attachment_id: attachmentId,
        nonce: fluxMediaAdmin.disableNonce
    };

    fetch(fluxMediaAdmin.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Show success message
            showNotice('Conversion disabled successfully!', 'success');
            // Reload the page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            showNotice('Failed to disable conversion: ' + (result.data || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Disable conversion error:', error);
        showNotice('Failed to disable conversion: Network error', 'error');
    })
    .finally(() => {
        // Restore button state
        button.disabled = false;
        button.textContent = originalText;
    });
}

/**
 * Enable conversion for a specific attachment.
 *
 * @since 0.1.0
 * @param {number} attachmentId The attachment ID to enable conversion for.
 */
function fluxMediaEnableConversion(attachmentId) {
    if (!attachmentId) {
        alert('Invalid attachment ID');
        return;
    }

    // Show loading state
    const button = event.target;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Enabling...';

    // Make AJAX request
    const data = {
        action: 'flux_media_enable_conversion',
        attachment_id: attachmentId,
        nonce: fluxMediaAdmin.enableNonce
    };

    fetch(fluxMediaAdmin.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Show success message
            showNotice('Conversion enabled successfully!', 'success');
            // Reload the page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            showNotice('Failed to enable conversion: ' + (result.data || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Enable conversion error:', error);
        showNotice('Failed to enable conversion: Network error', 'error');
    })
    .finally(() => {
        // Restore button state
        button.disabled = false;
        button.textContent = originalText;
    });
}

/**
 * Show a notice message to the user.
 *
 * @since 0.1.0
 * @param {string} message The message to display.
 * @param {string} type The type of notice ('success', 'error', 'warning', 'info').
 */
function showNotice(message, type = 'info') {
    // Remove any existing notices
    const existingNotices = document.querySelectorAll('.flux-media-notice');
    existingNotices.forEach(notice => notice.remove());

    // Create notice element
    const notice = document.createElement('div');
    notice.className = `flux-media-notice notice notice-${type} is-dismissible`;
    notice.style.cssText = 'position: fixed; top: 32px; right: 20px; z-index: 999999; max-width: 400px;';
    
    const noticeContent = document.createElement('p');
    noticeContent.textContent = message;
    notice.appendChild(noticeContent);

    // Add dismiss button
    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.className = 'notice-dismiss';
    dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
    dismissButton.onclick = () => notice.remove();
    notice.appendChild(dismissButton);

    // Add to page
    document.body.appendChild(notice);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (notice.parentNode) {
            notice.remove();
        }
    }, 5000);
}

// Make functions globally available
window.fluxMediaConvertAttachment = fluxMediaConvertAttachment;
window.fluxMediaDisableConversion = fluxMediaDisableConversion;
window.fluxMediaEnableConversion = fluxMediaEnableConversion;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Flux Media attachment functionality loaded');
    });
} else {
    console.log('Flux Media attachment functionality loaded');
}
