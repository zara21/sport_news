<?php
/**
 * ადმინის ანგარიშის შექმნა ან არსებული ანგარიშის პაროლის აღდგენა (მხოლოდ CLI).
 *
 *   C:\xampp2\php\php.exe scripts\create_admin.php
 *
 * პაროლი შეგყავთ დამალულად; ის არსად იწერება — ბაზაში მხოლოდ hash ინახება.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/db_connect.php';

function prompt(string $label, string $default = ''): string
{
    echo $label . ($default !== '' ? " [{$default}]" : '') . ': ';
    $value = trim((string) fgets(STDIN));
    return $value !== '' ? $value : $default;
}

function prompt_hidden(string $label): string
{
    echo $label . ': ';
    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows: PowerShell-ის Read-Host -AsSecureString ეკრანზე სიმბოლოებს არ აჩვენებს
        $command = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; '
            . '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); '
            . '[Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)); '
            . '[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)"';
        $value = (string) shell_exec($command);
    } else {
        system('stty -echo');
        $value = (string) fgets(STDIN);
        system('stty echo');
        echo "\n";
    }
    return rtrim($value, "\r\n");
}

echo "=== Sport Blog: ადმინის ანგარიში ===\n\n";

$email = strtolower(prompt('ელფოსტა'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "ელფოსტის ფორმატი არასწორია.\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, first_name, last_name, status FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo "ანგარიში უკვე არსებობს (#{$existing['id']}, როლი: {$existing['status']}). შეიცვლება პაროლი და როლი გახდება Super Administrator.\n";
    $firstName = $existing['first_name'];
    $lastName = $existing['last_name'];
} else {
    echo "ახალი ანგარიში შეიქმნება როლით Super Administrator.\n";
    $firstName = prompt('სახელი', 'Admin');
    $lastName = prompt('გვარი', 'User');
}

$password = prompt_hidden('ახალი პაროლი (მინ. 10 სიმბოლო)');
if (strlen($password) < 10) {
    fwrite(STDERR, "პაროლი მინიმუმ 10 სიმბოლო უნდა იყოს.\n");
    exit(1);
}
if (prompt_hidden('გაიმეორეთ პაროლი') !== $password) {
    fwrite(STDERR, "პაროლები არ ემთხვევა.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
unset($password);

if ($existing) {
    $pdo->prepare("UPDATE users SET password = ?, status = 'Super Administrator' WHERE id = ?")->execute([$hash, (int) $existing['id']]);
    $userId = (int) $existing['id'];
} else {
    $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, status) VALUES (?, ?, ?, ?, 'Super Administrator')")
        ->execute([$firstName, $lastName, $email, $hash]);
    $userId = (int) $pdo->lastInsertId();
}

// შესვლის მცდელობების ლიმიტის გასუფთავება ამ ელფოსტისთვის (ნებისმიერი IP-დან)
foreach (glob(__DIR__ . '/../storage/rate_limits/*.json') ?: [] as $file) {
    @unlink($file);
}

echo "\n✔ მზადაა (#{$userId}). შესვლა: http://localhost/sport_blog/auth_system/login\n";
