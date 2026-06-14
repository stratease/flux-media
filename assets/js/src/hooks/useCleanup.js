import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiService } from '@flux-media-optimizer/services/api';

/**
 * React Query hook for cleaning up temp files
 */
export const useCleanupTempFiles = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: () => apiService.cleanupTempFiles(),
    onSuccess: () => {
      // Invalidate system status to reflect cleanup
      queryClient.invalidateQueries({ queryKey: ['system'] });
    },
  });
};

/**
 * React Query hook for cleaning up old records
 */
export const useCleanupOldRecords = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (days = 30) => apiService.cleanupOldRecords(days),
    onSuccess: () => {
      // Invalidate conversion-related queries
      queryClient.invalidateQueries({ queryKey: ['conversions'] });
    },
  });
};
