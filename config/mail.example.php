<?php
// დააკოპირე როგორც config/mail.php და შეავსე. config/mail.php git-ში არ ინახება.
// Gmail-ის მაგალითი: Google ანგარიში → უსაფრთხოება → 2-ნაბიჯიანი ვერიფიკაცია → App passwords.
return [
    'transport' => 'smtp',          // 'log' = წერილები storage/logs/mail.log-ში (ლოკალური ტესტი)
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',          // tls | ssl | none
    'username' => 'you@gmail.com',
    'password' => 'app-password',
    'from_email' => 'you@gmail.com',
    'from_name' => 'Sport Blog',
];
