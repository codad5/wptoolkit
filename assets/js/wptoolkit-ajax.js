/**
 * WPToolkit Ajax Helper - JavaScript companion class
 *
 * Provides a clean, modern interface for handling AJAX requests
 * with the WPToolkit Ajax PHP class.
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later
 */

class WPToolkitAjax {
  /**
   * Constructor
   * @param {Object} config Configuration object from wp_localize_script
   * @param {Object} options Additional options
   */
  constructor(config = {}, options = {}) {
    this.config = {
      ajax_url: config.ajax_url || ajaxurl || "/wp-admin/admin-ajax.php",
      nonces: config.nonces || {},
      app_slug: config.app_slug || "wptoolkit",
      actions: config.actions || [],
      ...config,
    };

    this.options = {
      timeout: 30000, // 30 seconds
      retries: 3,
      retryDelay: 1000, // 1 second
      debug: false,
      globalErrorHandler: null,
      globalSuccessHandler: null,
      ...options,
    };

    this.requestQueue = new Map();
    this.eventListeners = new Map();

    // Bind methods
    this.send = this.send.bind(this);
    this.get = this.get.bind(this);
    this.post = this.post.bind(this);

    if (this.options.debug) {
      console.log("WPToolkitAjax initialized:", this.config);
    }
  }

  /**
   * Send an AJAX request
   * @param {string} action Action name
   * @param {Object} data Request data
   * @param {Object} options Request options
   * @returns {Promise} Promise that resolves with response
   */
  async send(action, data = {}, options = {}) {
    const requestOptions = {
      method: "POST",
      timeout: this.options.timeout,
      retries: this.options.retries,
      retryDelay: this.options.retryDelay,
      cache: false,
      ...options,
    };

    // Generate request ID for tracking
    const requestId = this.generateRequestId();

    try {
      // Prepare request data
      const requestData = await this.prepareRequestData(
        action,
        data,
        requestOptions
      );

      // Add to queue for tracking
      this.requestQueue.set(requestId, {
        action,
        data: requestData,
        options: requestOptions,
        timestamp: Date.now(),
      });

      // Emit before send event
      this.emit("beforeSend", { action, data: requestData, requestId });

      // Make the request with retries
      const response = await this.makeRequestWithRetries(
        requestData,
        requestOptions
      );

      // Process response
      const processedResponse = this.processResponse(response, action);

      // Remove from queue
      this.requestQueue.delete(requestId);

      // Emit success event
      this.emit("success", { action, response: processedResponse, requestId });

      // Call global success handler
      if (typeof this.options.globalSuccessHandler === "function") {
        this.options.globalSuccessHandler(processedResponse, action);
      }

      return processedResponse;
    } catch (error) {
      // Remove from queue
      this.requestQueue.delete(requestId);

      // Emit error event
      this.emit("error", { action, error, requestId });

      // Call global error handler
      if (typeof this.options.globalErrorHandler === "function") {
        this.options.globalErrorHandler(error, action);
      }

      throw error;
    }
  }

  /**
   * Send a GET-style request (data in URL params)
   * @param {string} action Action name
   * @param {Object} data Request data
   * @param {Object} options Request options
   * @returns {Promise} Promise that resolves with response
   */
  get(action, data = {}, options = {}) {
    return this.send(action, data, { ...options, method: "GET" });
  }

  /**
   * Send a POST request
   * @param {string} action Action name
   * @param {Object} data Request data
   * @param {Object} options Request options
   * @returns {Promise} Promise that resolves with response
   */
  post(action, data = {}, options = {}) {
    return this.send(action, data, { ...options, method: "POST" });
  }

  /**
   * Upload files via AJAX
   * @param {string} action Action name
   * @param {FormData|HTMLFormElement} formData Form data or form element
   * @param {Object} options Request options
   * @returns {Promise} Promise that resolves with response
   */
  async upload(action, formData, options = {}) {
    let data;

    if (formData instanceof HTMLFormElement) {
      data = new FormData(formData);
    } else if (formData instanceof FormData) {
      data = formData;
    } else {
      throw new Error("formData must be FormData instance or form element");
    }

    return this.send(action, data, {
      ...options,
      processData: false,
      contentType: false,
      uploadProgress: options.uploadProgress || null,
    });
  }

  /**
   * Send multiple requests in parallel
   * @param {Array} requests Array of request configurations
   * @returns {Promise} Promise that resolves with all responses
   */
  async parallel(requests) {
    const promises = requests.map((request) => {
      const { action, data = {}, options = {} } = request;
      return this.send(action, data, options);
    });

    return Promise.all(promises);
  }

  /**
   * Send multiple requests in sequence
   * @param {Array} requests Array of request configurations
   * @returns {Promise} Promise that resolves with all responses
   */
  async sequence(requests) {
    const results = [];

    for (const request of requests) {
      const { action, data = {}, options = {} } = request;
      const result = await this.send(action, data, options);
      results.push(result);
    }

    return results;
  }

  /**
   * Cancel a request by ID
   * @param {string} requestId Request ID
   * @returns {boolean} Whether request was cancelled
   */
  cancel(requestId) {
    if (this.requestQueue.has(requestId)) {
      this.requestQueue.delete(requestId);
      this.emit("cancelled", { requestId });
      return true;
    }
    return false;
  }

  /**
   * Cancel all pending requests
   * @returns {number} Number of cancelled requests
   */
  cancelAll() {
    const count = this.requestQueue.size;
    this.requestQueue.clear();
    this.emit("allCancelled", { count });
    return count;
  }

  /**
   * Get pending requests
   * @returns {Array} Array of pending requests
   */
  getPendingRequests() {
    return Array.from(this.requestQueue.entries()).map(([id, request]) => ({
      id,
      ...request,
    }));
  }

  /**
   * Add event listener
   * @param {string} event Event name
   * @param {Function} callback Callback function
   */
  on(event, callback) {
    if (!this.eventListeners.has(event)) {
      this.eventListeners.set(event, []);
    }
    this.eventListeners.get(event).push(callback);
  }

  /**
   * Remove event listener
   * @param {string} event Event name
   * @param {Function} callback Callback function
   */
  off(event, callback) {
    if (this.eventListeners.has(event)) {
      const callbacks = this.eventListeners.get(event);
      const index = callbacks.indexOf(callback);
      if (index > -1) {
        callbacks.splice(index, 1);
      }
    }
  }

  /**
   * Emit event
   * @param {string} event Event name
   * @param {Object} data Event data
   */
  emit(event, data) {
    if (this.eventListeners.has(event)) {
      this.eventListeners.get(event).forEach((callback) => {
        try {
          callback(data);
        } catch (error) {
          console.error(`Error in event listener for ${event}:`, error);
        }
      });
    }
  }

  /**
   * Set nonce for an action
   * @param {string} action Action name
   * @param {string} nonce Nonce value
   */
  setNonce(action, nonce) {
    this.config.nonces[action] = nonce;
  }

  /**
   * Get nonce for an action
   * @param {string} action Action name
   * @returns {string|null} Nonce value or null
   */
  getNonce(action) {
    return this.config.nonces[action] || null;
  }

  /**
   * Update configuration
   * @param {Object} newConfig New configuration
   */
  updateConfig(newConfig) {
    this.config = { ...this.config, ...newConfig };
  }

  /**
   * Create a specialized instance for a specific action
   * @param {string} action Action name
   * @param {Object} defaultData Default data for requests
   * @returns {Object} Specialized instance
   */
  createActionInstance(action, defaultData = {}) {
    const instance = {
      send: (data = {}, options = {}) => {
        return this.send(action, { ...defaultData, ...data }, options);
      },
      get: (data = {}, options = {}) => {
        return this.get(action, { ...defaultData, ...data }, options);
      },
      post: (data = {}, options = {}) => {
        return this.post(action, { ...defaultData, ...data }, options);
      },
      upload: (formData, options = {}) => {
        return this.upload(action, formData, options);
      },
    };

    return instance;
  }

  /**
   * Prepare request data
   * @param {string} action Action name
   * @param {Object} data Request data
   * @param {Object} options Request options
   * @returns {Promise<FormData|URLSearchParams>} Prepared data
   */
  async prepareRequestData(action, data, options) {
    const isFormData = data instanceof FormData;
    const requestData = isFormData ? data : new FormData();

    if (!isFormData) {
      // Convert object to FormData
      Object.keys(data).forEach((key) => {
        const value = data[key];
        if (value !== null && value !== undefined) {
          if (typeof value === "object" && !(value instanceof File)) {
            requestData.append(key, JSON.stringify(value));
          } else {
            requestData.append(key, value);
          }
        }
      });
    }

    // Add WordPress action
    requestData.append("action", `${this.config.app_slug}_${action}`);

    // Add nonce if available
    const nonce = this.getNonce(action);
    if (nonce) {
      requestData.append("_wpnonce", nonce);
    }

    return requestData;
  }

  /**
   * Make request with retry logic
   * @param {FormData} requestData Request data
   * @param {Object} options Request options
   * @returns {Promise<Response>} Response object
   */
  async makeRequestWithRetries(requestData, options) {
    let lastError;

    for (let attempt = 0; attempt < options.retries; attempt++) {
      try {
        const response = await this.makeRequest(requestData, options);
        return response;
      } catch (error) {
        lastError = error;

        // Don't retry on client errors (4xx)
        if (error.status >= 400 && error.status < 500) {
          throw error;
        }

        // Wait before retry
        if (attempt < options.retries - 1) {
          await this.delay(options.retryDelay * (attempt + 1));
        }
      }
    }

    throw lastError;
  }

  /**
   * Make the actual HTTP request
   * @param {FormData} requestData Request data
   * @param {Object} options Request options
   * @returns {Promise<Object>} Response data
   */
  async makeRequest(requestData, options) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), options.timeout);

    try {
      const fetchOptions = {
        method: options.method,
        body: requestData,
        signal: controller.signal,
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      };

      // Handle upload progress
      if (
        options.uploadProgress &&
        typeof options.uploadProgress === "function"
      ) {
        // Note: Fetch API doesn't support upload progress natively
        // This would require a custom implementation or XHR fallback
        console.warn("Upload progress not supported with fetch API");
      }

      const response = await fetch(this.config.ajax_url, fetchOptions);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const text = await response.text();

      // Try to parse as JSON
      try {
        return JSON.parse(text);
      } catch (e) {
        // If not JSON, return as text
        return { data: text, raw: true };
      }
    } finally {
      clearTimeout(timeoutId);
    }
  }

  /**
   * Process response data
   * @param {Object} response Raw response
   * @param {string} action Action name
   * @returns {Object} Processed response
   */
  processResponse(response, action) {
    if (this.options.debug) {
      console.log(`Ajax response for ${action}:`, response);
    }

    // Handle WordPress AJAX response format
    if (response.success !== undefined) {
      if (response.success) {
        return response.data;
      } else {
        const error = new Error(
          response.data?.message || "Ajax request failed"
        );
        error.code = response.data?.code || "ajax_error";
        error.data = response.data;
        throw error;
      }
    }

    // Handle custom response format
    if (response.error) {
      const error = new Error(response.message || "Ajax request failed");
      error.code = response.code || "ajax_error";
      error.data = response.data;
      throw error;
    }

    return response;
  }

  /**
   * Generate unique request ID
   * @returns {string} Request ID
   */
  generateRequestId() {
    return `${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Delay utility
   * @param {number} ms Milliseconds to delay
   * @returns {Promise} Promise that resolves after delay
   */
  delay(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Create a cached request method
   * @param {number} ttl Cache TTL in milliseconds
   * @returns {Function} Cached request function
   */
  createCachedRequest(ttl = 300000) {
    // 5 minutes default
    const cache = new Map();

    return async (action, data = {}, options = {}) => {
      const cacheKey = JSON.stringify({ action, data });
      const cached = cache.get(cacheKey);

      if (cached && Date.now() - cached.timestamp < ttl) {
        return cached.data;
      }

      const response = await this.send(action, data, options);
      cache.set(cacheKey, {
        data: response,
        timestamp: Date.now(),
      });

      return response;
    };
  }

  /**
   * Create a debounced request method
   * @param {number} delay Debounce delay in milliseconds
   * @returns {Function} Debounced request function
   */
  createDebouncedRequest(delay = 300) {
    const timers = new Map();

    return (action, data = {}, options = {}) => {
      return new Promise((resolve, reject) => {
        const key = `${action}_${JSON.stringify(data)}`;

        // Clear existing timer
        if (timers.has(key)) {
          clearTimeout(timers.get(key).timer);
        }

        // Set new timer
        const timer = setTimeout(async () => {
          try {
            const response = await this.send(action, data, options);
            resolve(response);
          } catch (error) {
            reject(error);
          }
          timers.delete(key);
        }, delay);

        timers.set(key, { timer, resolve, reject });
      });
    };
  }

  /**
   * Create a throttled request method
   * @param {number} limit Throttle limit in milliseconds
   * @returns {Function} Throttled request function
   */
  createThrottledRequest(limit = 1000) {
    const lastCalled = new Map();

    return async (action, data = {}, options = {}) => {
      const key = `${action}_${JSON.stringify(data)}`;
      const now = Date.now();
      const last = lastCalled.get(key) || 0;

      if (now - last < limit) {
        throw new Error("Request throttled");
      }

      lastCalled.set(key, now);
      return this.send(action, data, options);
    };
  }
}

/**
 * jQuery plugin wrapper for backward compatibility
 */
if (typeof jQuery !== "undefined") {
  jQuery.fn.wptkAjax = function (action, data = {}, options = {}) {
    // Create instance if not exists
    if (!this.data("wptk-ajax")) {
      const config = window.wptkAjaxConfig || {};
      this.data("wptk-ajax", new WPToolkitAjax(config));
    }

    const instance = this.data("wptk-ajax");
    return instance.send(action, data, options);
  };
}

/**
 * Global convenience function
 */
window.wptkAjax = function (config = {}) {
  // Use global config if available
  const globalConfig = window.wptkAjaxConfig || {};
  return new WPToolkitAjax({ ...globalConfig, ...config });
};

/**
 * Auto-initialize if config is available
 */
if (typeof window.wptkAjaxConfig !== "undefined") {
  window.wptkAjaxInstance = new WPToolkitAjax(window.wptkAjaxConfig);
}

// Export for module systems
if (typeof module !== "undefined" && module.exports) {
  module.exports = WPToolkitAjax;
}

if (typeof define === "function" && define.amd) {
  define([], function () {
    return WPToolkitAjax;
  });
}
