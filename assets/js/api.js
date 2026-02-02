/**
 * API Utilities
 * Handles all HTTP requests to the backend
 */

const API = {
    // Base request method
    async request(endpoint, options = {}) {
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': App.csrfToken,
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache',
                ...options.headers
            },
            credentials: 'same-origin', // Include cookies for session
            cache: 'no-store', // Disable fetch caching
            ...options
        };

        try {
            const response = await fetch(endpoint, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || `Request failed: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);

            // Show user-friendly error messages
            if (error.message.includes('Unauthorized')) {
                showToast('Session expired. Please refresh the page.', 'error');
            } else if (error.message.includes('Invalid CSRF token')) {
                showToast('Security token expired. Please refresh the page.', 'error');
            } else if (!error.message.includes('Request failed')) {
                showToast(error.message, 'error');
            }

            throw error;
        }
    },

    // GET request
    get(endpoint) {
        return this.request(endpoint);
    },

    // POST request
    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    // PUT request
    put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    // PATCH request
    patch(endpoint, data) {
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    },

    // DELETE request
    delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    },

    // File upload
    upload(endpoint, formData, showLoading = true) {
        // Add CSRF token to FormData
        formData.append('csrf_token', App.csrfToken);

        if (showLoading) Loading.show();
        return this.request(endpoint, {
            method: 'POST',
            body: formData,
            headers: {} // Let browser set Content-Type for FormData
        }).finally(() => {
            if (showLoading) Loading.hide();
        });
    }
};