<?php
// Push-შეტყობინებები (Firebase Cloud Messaging, HTTP v1).
// დააკოპირე როგორც config/firebase.php და შეავსე. config/firebase.php და service account JSON git-ში არ ინახება.
//
// 1) Firebase Console → Project settings → General → Your apps → Web app: აქედან web-ის პარამეტრები
// 2) Project settings → Cloud Messaging → Web Push certificates → Generate key pair: vapid_key
// 3) Project settings → Service accounts → Generate new private key: JSON ფაილი შეინახე
//    config/firebase-service-account.json-ად (ან მიუთითე სხვა მისამართი ქვემოთ)
return [
    'api_key' => '',
    'auth_domain' => 'your-project.firebaseapp.com',
    'project_id' => 'your-project',
    'messaging_sender_id' => '',
    'app_id' => '',
    'vapid_key' => '',
    'service_account_file' => __DIR__ . '/firebase-service-account.json',
];
