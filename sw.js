/**
 * Service Worker for DBEDC File Tracker PWA v2.0
 * Enables offline functionality, caching, and push notifications
 */

const CACHE_NAME = 'file-tracker-v2.0';
const urlsToCache = [
    '/dashboard.php',
    '/assets/css/app.css',
    '/assets/js/app.js',
    '/login.php',
    '/manifest.json',
    '/assets/icon-192.png',
    '/assets/icon-512.png'
];

// Install event - cache resources
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache v2.0');
                return cache.addAll(urlsToCache);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
    // Only cache GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Cache hit - return response
                if (response) {
                    // Return cached response but also update cache in background
                    event.waitUntil(
                        fetch(event.request).then(res => {
                            if (res && res.status === 200) {
                                caches.open(CACHE_NAME).then(cache => {
                                    cache.put(event.request, res);
                                });
                            }
                        }).catch(() => {})
                    );
                    return response;
                }
                
                // Clone the request
                const fetchRequest = event.request.clone();
                
                return fetch(fetchRequest).then(response => {
                    // Check if valid response
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    
                    // Clone the response
                    const responseToCache = response.clone();
                    
                    // Cache the response for future use
                    caches.open(CACHE_NAME)
                        .then(cache => {
                            cache.put(event.request, responseToCache);
                        });
                    
                    return response;
                });
            })
            .catch(() => {
                // Return offline page if available
                return caches.match('/dashboard.php');
            })
    );
});

// Push notification event
self.addEventListener('push', event => {
    let data = {};
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data.body = event.data.text();
        }
    }
    
    const title = data.title || 'File Tracker Notification';
    const options = {
        body: data.body || 'You have a new notification',
        icon: '/assets/icon-192.png',
        badge: '/assets/icon-192.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/dashboard.php',
            type: data.type || 'general',
            id: data.id || null
        },
        actions: [
            {
                action: 'open',
                title: 'Open'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ],
        tag: data.tag || 'file-tracker-notification',
        renotify: true
    };
    
    // Add image if provided
    if (data.image) {
        options.image = data.image;
    }
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Notification click event
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'dismiss') {
        return;
    }
    
    const urlToOpen = event.notification.data?.url || '/dashboard.php';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Check if there's already a window open
                for (const client of clientList) {
                    if (client.url.includes('/dashboard.php') && 'focus' in client) {
                        client.focus();
                        client.postMessage({
                            type: 'NOTIFICATION_CLICK',
                            data: event.notification.data
                        });
                        return;
                    }
                }
                
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// Notification close event
self.addEventListener('notificationclose', event => {
    // Log notification closed for analytics
    if (event.notification.tag) {
        console.log('Notification closed:', event.notification.tag);
    }
});

// Background sync for offline actions (future implementation)
self.addEventListener('sync', event => {
    if (event.tag === 'sync-tasks') {
        event.waitUntil(syncTasks());
    }
});

async function syncTasks() {
    // Placeholder for offline sync functionality
    console.log('Background sync triggered');
}
