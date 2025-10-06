/**
 * API service for Flux Media WordPress plugin using WordPress apiFetch
 */

import apiFetch from '@wordpress/api-fetch';

class ApiService {
  constructor() {
    this.namespace = 'flux-media/v1';
    
    // Configure apiFetch with proper API root
    const apiRoot = window.fluxMediaAdmin?.apiUrl || '/wp-json/';
    apiFetch.use(apiFetch.createRootURLMiddleware(apiRoot));
    
    console.log('API Service initialized with root:', apiRoot);
  }

  /**
   * Make a request using WordPress apiFetch
   * @param {string} endpoint - The API endpoint
   * @param {Object} options - Request options
   * @returns {Promise} - API response
   */
  async request(endpoint, options = {}) {
    const defaultOptions = {
      path: `/${this.namespace}${endpoint}`,
      method: 'GET',
      headers: {
        'X-WP-Nonce': window.fluxMediaAdmin?.nonce || '',
        'Content-Type': 'application/json',
      },
    };

    const mergedOptions = {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...(options.headers || {}),
      },
    };

    console.log('API Request:', {
      endpoint,
      path: mergedOptions.path,
      method: mergedOptions.method,
      fullPath: `/${this.namespace}${endpoint}`,
      apiRoot: window.fluxMediaAdmin?.apiUrl,
      nonce: window.fluxMediaAdmin?.nonce,
      headers: mergedOptions.headers,
      options: mergedOptions
    });

    try {
      const response = await apiFetch(mergedOptions);
      
      console.log('API Response:', response);
      
      // Handle the new structured response format
      if (response && typeof response === 'object' && response.success !== undefined) {
        // New format: { success: true, data: {...}, message: "...", timestamp: "..." }
        return response.data;
      }
      
      // Legacy format: return data directly
      return response;
    } catch (error) {
      console.error('API Error:', error);
      console.error('Error details:', {
        message: error.message,
        code: error.code,
        status: error.status,
        data: error.data
      });
      
      // Throw the error for React Query to handle
      throw error;
    }
  }

  // System endpoints
  async getSystemStatus() {
    return this.request('/system/status');
  }

  // Conversion endpoints
  async getConversionStats(filters = {}) {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value) params.append(key, value);
    });
    
    const queryString = params.toString();
    const endpoint = queryString ? `/conversions/stats?${queryString}` : '/conversions/stats';
    
    return this.request(endpoint);
  }

  async getRecentConversions(limit = 10) {
    return this.request(`/conversions/recent?limit=${limit}`);
  }

  // Quota endpoints
  async getQuotaProgress() {
    return this.request('/quota/progress');
  }

  async getPlanInfo() {
    return this.request('/quota/plan');
  }

  // Options endpoints
  async getOptions() {
    return this.request('/options');
  }

  async updateOptions(options) {
    return this.request('/options', {
      method: 'POST',
      body: JSON.stringify(options),
    });
  }



  // Conversion operations
  async startConversion(attachmentId, format) {
    return this.request('/conversions/start', {
      method: 'POST',
      body: JSON.stringify({ attachmentId, format }),
    });
  }

  async cancelConversion(jobId) {
    return this.request(`/conversions/cancel/${jobId}`, {
      method: 'POST',
    });
  }

  async bulkConvert(formats) {
    return this.request('/conversions/bulk', {
      method: 'POST',
      body: JSON.stringify({ formats }),
    });
  }

  // File operations
  async deleteConvertedFile(attachmentId, format) {
    return this.request(`/files/delete/${attachmentId}/${format}`, {
      method: 'DELETE',
    });
  }

  // Logs
  async getLogs(params = {}) {
    const queryParams = new URLSearchParams();
    
    if (params.page) queryParams.append('page', params.page.toString());
    if (params.per_page) queryParams.append('per_page', params.per_page.toString());
    if (params.level) queryParams.append('level', params.level);
    if (params.search) queryParams.append('search', params.search);
    
    return this.request(`/logs?${queryParams.toString()}`);
  }

  // Cleanup operations
  async cleanupTempFiles() {
    return this.request('/cleanup/temp-files', {
      method: 'POST',
    });
  }

  async cleanupOldRecords(days = 30) {
    return this.request('/cleanup/old-records', {
      method: 'POST',
      body: JSON.stringify({ days }),
    });
  }
}

// Export singleton instance
export const apiService = new ApiService();
export default apiService;
