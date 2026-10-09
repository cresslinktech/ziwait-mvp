// Lets a signed-in restaurant user turn new-order alerts on or off for this browser.
const root = document.querySelector('[data-push]');

if (root) {
    const button = root.querySelector('[data-push-toggle]');
    const status = root.querySelector('[data-push-status]');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    const urlBase64ToUint8Array = (b64) => {
        const padding = '='.repeat((4 - (b64.length % 4)) % 4);
        const raw = atob((b64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, (c) => c.charCodeAt(0));
    };

    const send = (method, body) => fetch(root.dataset.url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    });

    const render = async () => {
        if (!supported) { status.textContent = 'This browser does not support push alerts. On iPhone, add ZIWAIT to your Home Screen first.'; button.hidden = true; return; }
        if (Notification.permission === 'denied') { status.textContent = 'Notifications are blocked for this site in your browser settings.'; button.hidden = true; return; }
        const reg = await navigator.serviceWorker.getRegistration('/sw.js');
        const sub = reg && await reg.pushManager.getSubscription();
        button.hidden = false;
        button.dataset.on = sub ? '1' : '0';
        button.textContent = sub ? 'Turn off order alerts on this device' : 'Turn on order alerts on this device';
        status.textContent = sub ? 'Order alerts are on for this device.' : 'Order alerts are off for this device.';
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const reg = await navigator.serviceWorker.register('/sw.js');
            await navigator.serviceWorker.ready;
            const existing = await reg.pushManager.getSubscription();

            if (existing) {
                await send('DELETE', { endpoint: existing.endpoint });
                await existing.unsubscribe();
            } else {
                if ((await Notification.requestPermission()) !== 'granted') { await render(); return; }
                const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(root.dataset.key) });
                const json = sub.toJSON();
                const res = await send('POST', { endpoint: json.endpoint, keys: json.keys, content_encoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] });
                if (!res.ok) { await sub.unsubscribe(); status.textContent = 'Could not save this device. Please try again.'; return; }
            }
        } catch (e) {
            status.textContent = 'Something went wrong turning alerts on. Please try again.';
            console.error(e);
            return;
        } finally {
            button.disabled = false;
        }
        await render();
    });

    render();
}
