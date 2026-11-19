<?php
// დააკოპირე ეს ფაილი როგორც config/google_oauth.php და შეავსე მნიშვნელობები.
// ალტერნატივა: გარემოს ცვლადები GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI.
// config/google_oauth.php არ უნდა მოხვდეს git-ში.
return [
    'client_id' => '',
    'client_secret' => '',
    // ცარიელი = ავტომატურად: <საიტი>/auth_system/google_login.php
    'redirect_uri' => '',
];
