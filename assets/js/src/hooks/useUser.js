import { useMutation } from '@tanstack/react-query';

/**
 * React Query hook for subscribing to newsletter
 */
export const useSubscribeNewsletter = () => {
  return useMutation({
    mutationFn: async (formData) => {
      const formDataToSubmit = new FormData();
      formDataToSubmit.append('ne', formData.email);
      formDataToSubmit.append('ny', '1'); // Privacy checkbox value
      formDataToSubmit.append('nlang', ''); // Language field

      const response = await fetch('https://fluxplugins.com/wp-admin/admin-ajax.php?action=tnp&na=s', {
        method: 'POST',
        body: formDataToSubmit,
        mode: 'no-cors', // Handle CORS for external domain
      });

      // Since we're using no-cors, we can't read the response
      // Return success if no network error occurred
      return { success: true };
    },
    onError: (error) => {
      console.error('Newsletter subscription error:', error);
    },
  });
};
