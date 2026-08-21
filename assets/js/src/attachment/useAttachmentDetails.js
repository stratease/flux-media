/**
 * TanStack Query hooks for attachment details panel.
 *
 * @since 4.3.0
 */

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  convertAttachment,
  disableConversion,
  enableConversion,
  fetchAttachmentDetails,
} from './attachmentApi';

export const ATTACHMENT_DETAILS_QUERY_KEY = 'attachmentDetails';

/**
 * Polling interval while conversion is pending (milliseconds).
 *
 * @since 4.3.0
 * @type {number}
 */
export const PENDING_REFETCH_MS = 15000;

/**
 * Query key factory for attachment details.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {Array}
 */
export function attachmentDetailsQueryKey(attachmentId) {
  return [ATTACHMENT_DETAILS_QUERY_KEY, attachmentId];
}

/**
 * Load attachment details; poll every 15s while status is pending.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {import('@tanstack/react-query').UseQueryResult}
 */
export function useAttachmentDetails(attachmentId) {
  return useQuery({
    queryKey: attachmentDetailsQueryKey(attachmentId),
    queryFn: () => fetchAttachmentDetails(attachmentId),
    enabled: Boolean(attachmentId),
    staleTime: 5 * 1000,
    refetchOnWindowFocus: false,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'pending' ? PENDING_REFETCH_MS : false;
    },
  });
}

/**
 * Convert / disable / enable mutations with query invalidation.
 *
 * @since 4.3.0
 * @param {number} attachmentId Attachment ID.
 * @returns {{convert: Function, disable: Function, enable: Function, error: string, isBusy: boolean}}
 */
export function useAttachmentActions(attachmentId) {
  const queryClient = useQueryClient();
  const key = attachmentDetailsQueryKey(attachmentId);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: key });

  const convert = useMutation({
    mutationFn: () => convertAttachment(attachmentId),
    onMutate: async () => {
      await queryClient.cancelQueries({ queryKey: key });
      const previous = queryClient.getQueryData(key);
      if (previous) {
        queryClient.setQueryData(key, {
          ...previous,
          status: 'pending',
          statusLabel: previous.statusLabel || 'Pending',
          processing: true,
          actions: {
            ...previous.actions,
            canConvert: false,
            canDisable: false,
            convertLabel: previous.actions?.convertLabel || 'Processing…',
          },
        });
      }
      return { previous };
    },
    onError: (_error, _vars, context) => {
      if (context?.previous) {
        queryClient.setQueryData(key, context.previous);
      }
    },
    onSettled: invalidate,
  });

  const disable = useMutation({
    mutationFn: () => disableConversion(attachmentId),
    onSettled: invalidate,
  });

  const enable = useMutation({
    mutationFn: () => enableConversion(attachmentId),
    onSettled: invalidate,
  });

  const active = convert.isPending || disable.isPending || enable.isPending;
  const error =
    convert.error?.message || disable.error?.message || enable.error?.message || '';

  return {
    convert: convert.mutateAsync,
    disable: disable.mutateAsync,
    enable: enable.mutateAsync,
    error,
    isBusy: active,
  };
}
