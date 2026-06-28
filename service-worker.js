// WHOIS 查询工具 Service Worker
// 缓存策略：静态资源用缓存优先，whois.php 与 locales/*.json 走网络。

const CACHE_NAME = 'whois-cache-v1';
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/manifest.json',
    '/whois.json'
];

// 安装时预缓存核心静态资源
self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(STATIC_ASSETS).catch(function () {
                // 部分资源加载失败也不阻断安装
                return Promise.resolve();
            });
        })
    );
    self.skipWaiting();
});

// 激活时清理旧缓存
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (k) { return k !== CACHE_NAME; })
                    .map(function (k) { return caches.delete(k); })
            );
        })
    );
    self.clients.claim();
});

// 请求拦截
self.addEventListener('fetch', function (event) {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // 后端接口与 locales 文案走网络优先（保证数据新鲜）
    if (url.pathname.endsWith('/whois.php') || url.pathname.indexOf('/locales/') !== -1) {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match(req);
            })
        );
        return;
    }

    // 同源静态资源走缓存优先
    if (url.origin === self.location.origin) {
        event.respondWith(
            caches.match(req).then(function (cached) {
                if (cached) {
                    // 后台更新缓存
                    fetch(req).then(function (resp) {
                        if (resp && resp.status === 200) {
                            caches.open(CACHE_NAME).then(function (cache) {
                                cache.put(req, resp);
                            });
                        }
                    }).catch(function () {});
                    return cached;
                }
                return fetch(req).then(function (resp) {
                    if (resp && resp.status === 200) {
                        const copy = resp.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(req, copy);
                        });
                    }
                    return resp;
                }).catch(function () {
                    return caches.match('/index.html');
                });
            })
        );
        return;
    }

    // 第三方 CDN 资源走网络，失败回退缓存
    event.respondWith(
        fetch(req).then(function (resp) {
            if (resp && resp.status === 200) {
                const copy = resp.clone();
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(req, copy);
                });
            }
            return resp;
        }).catch(function () {
            return caches.match(req);
        })
    );
});
