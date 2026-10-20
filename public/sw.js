'use strict';

// Keep account, booking and payment requests on the network; no personal data is cached.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET' || event.request.mode !== 'navigate') return;
    event.respondWith(fetch(event.request).catch(() => new Response(
        '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8">' +
        '<meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>جوسبا | لا يوجد اتصال</title>' +
        '<body style="margin:0;background:#fffaf2;color:#30271c;font-family:Arial;text-align:center;padding:20vh 24px">' +
        '<h1>أنت غير متصل بالإنترنت</h1><p>تحقق من اتصالك للمتابعة إلى جوسبا.</p>' +
        '<p lang="en" dir="ltr">You are offline. Connect to the internet to continue.</p>' +
        '<button onclick="location.reload()" style="padding:14px 28px;background:#745126;color:white;border:0;border-radius:12px;font-size:16px">إعادة المحاولة / Retry</button></body></html>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
    )));
});
