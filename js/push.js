/* Push-შეტყობინებების ჩართვა/გამორთვა (შეტყობინებების გვერდზე) */
(() => {
  const box = document.querySelector('[data-push-box]');
  if (!box) return;

  const button = box.querySelector('[data-push-toggle]');
  const status = box.querySelector('[data-push-status]');
  const config = JSON.parse(box.dataset.config || 'null');
  const vapidKey = box.dataset.vapidKey || '';
  const csrf = box.dataset.csrf || '';
  const SDK = 'https://www.gstatic.com/firebasejs/10.12.5/';
  const STORAGE_KEY = 'sb-push-token';

  const setStatus = (text, enabled) => {
    status.textContent = text;
    button.textContent = enabled ? 'გამორთვა' : 'ჩართვა';
    button.dataset.enabled = enabled ? '1' : '0';
    box.classList.toggle('is-enabled', enabled);
  };

  const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
  if (!config || !supported) {
    setStatus(!config ? 'Push-შეტყობინებები ჯერ არ არის ჩართული საიტზე.' : 'ეს ბრაუზერი push-შეტყობინებებს არ უჭერს მხარს (საჭიროა HTTPS).', false);
    button.disabled = true;
    return;
  }

  const loadScript = (src) => new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve();
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = () => reject(new Error('SDK load failed'));
    document.head.appendChild(script);
  });

  let messagingPromise = null;
  const getMessaging = () => {
    messagingPromise ??= (async () => {
      await loadScript(SDK + 'firebase-app-compat.js');
      await loadScript(SDK + 'firebase-messaging-compat.js');
      if (!firebase.apps.length) firebase.initializeApp(config);
      return firebase.messaging();
    })();
    return messagingPromise;
  };

  const sendToken = async (token, action) => {
    const response = await fetch('/sport_blog/api/save_token.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ token, action }),
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('save_token ' + response.status);
  };

  const readStored = () => { try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; } };
  const store = (value) => { try { value ? localStorage.setItem(STORAGE_KEY, value) : localStorage.removeItem(STORAGE_KEY); } catch (e) {} };

  const enable = async () => {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      setStatus('შეტყობინებების ნებართვა უარყოფილია. შეცვალეთ ბრაუზერის პარამეტრებში.', false);
      return;
    }
    const registration = await navigator.serviceWorker.register('/sport_blog/firebase-messaging-sw.js', { scope: '/sport_blog/' });
    const messaging = await getMessaging();
    const token = await messaging.getToken({ vapidKey, serviceWorkerRegistration: registration });
    if (!token) throw new Error('no token');
    await sendToken(token, 'subscribe');
    store(token);
    setStatus('Push-შეტყობინებები ჩართულია ამ მოწყობილობაზე.', true);
  };

  const disable = async () => {
    const token = readStored();
    try {
      const messaging = await getMessaging();
      await messaging.deleteToken();
    } catch (e) {}
    if (token) await sendToken(token, 'unsubscribe');
    store(null);
    setStatus('Push-შეტყობინებები გამორთულია.', false);
  };

  setStatus(
    Notification.permission === 'granted' && readStored() ? 'Push-შეტყობინებები ჩართულია ამ მოწყობილობაზე.' : 'მიიღეთ შეტყობინება ახალ სტატიებზე, მაშინაც კი, როცა საიტი დახურულია.',
    Notification.permission === 'granted' && !!readStored()
  );

  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      button.dataset.enabled === '1' ? await disable() : await enable();
    } catch (error) {
      console.error(error);
      setStatus('ვერ მოხერხდა. სცადეთ თავიდან.', false);
    } finally {
      button.disabled = false;
    }
  });
})();
