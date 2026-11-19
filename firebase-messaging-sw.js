importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.1/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "Შენი ApiKey",
  authDomain: "შენი-project.firebaseapp.com",
  projectId: "შენი-project-id",
  storageBucket: "შენი-project.appspot.com",
  messagingSenderId: "შენი SenderId",
  appId: "შენი appId",
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function(payload) {
  console.log('[firebase-messaging-sw.js] Background message received ', payload);

  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: payload.notification.icon || '/icon.png',
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
