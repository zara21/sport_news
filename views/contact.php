<?php
require_once __DIR__ . '/../includes/static_page.php';
require_once __DIR__ . '/../includes/mailer.php';

$contactEmail = site_contact_email();
$errors = '';
$sent = false;
$form = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $_) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $rateKey = auth_rate_limit_key('contact');

    if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors = 'ფორმის სესია ამოიწურა. სცადეთ თავიდან.';
    } elseif (($_POST['website'] ?? '') !== '') {
        // ბოტების ხაფანგი (დამალული ველი) — ვაჩვენებთ წარმატებას, მაგრამ არაფერს ვაგზავნით
        $sent = true;
    } elseif (auth_is_rate_limited($rateKey, 3, 3600)) {
        http_response_code(429);
        $errors = 'ძალიან ბევრი შეტყობინებაა. სცადეთ მოგვიანებით.';
    } elseif ($form['name'] === '' || $form['message'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors = 'შეავსეთ სახელი, სწორი ელფოსტა და შეტყობინება.';
    } elseif (mb_strlen($form['message'], 'UTF-8') > 5000 || mb_strlen($form['name'], 'UTF-8') > 100 || mb_strlen($form['subject'], 'UTF-8') > 150) {
        $errors = 'ტექსტი ძალიან გრძელია.';
    } else {
        auth_record_failure($rateKey, 3600);
        $recipient = $contactEmail !== '' ? $contactEmail : (string) (mail_config()['from_email'] ?? '');
        $subject = 'საკონტაქტო ფორმა: ' . ($form['subject'] !== '' ? $form['subject'] : 'შეტყობინება');
        $body = "სახელი: {$form['name']}\nელფოსტა: {$form['email']}\n\n{$form['message']}\n";
        if (send_mail($recipient, preg_replace('/[\r\n]+/', ' ', $subject), $body)) {
            $sent = true;
            $form = array_map(static fn() => '', $form);
        } else {
            $errors = 'შეტყობინება ვერ გაიგზავნა. სცადეთ მოგვიანებით.';
        }
    }
}

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

static_page_start('კონტაქტი', 'დაგვიკავშირდით', 'შეკითხვა, შესწორება ან თანამშრომლობის შეთავაზება — მოგვწერეთ.');
?>
<div class="contact-grid">
    <section class="contact-card">
        <?php if ($sent): ?>
            <div class="static-alert static-alert--success">შეტყობინება გაიგზავნა. გიპასუხებთ მითითებულ ელფოსტაზე.</div>
        <?php endif; ?>
        <?php if ($errors !== ''): ?>
            <div class="static-alert static-alert--error"><?= $h($errors) ?></div>
        <?php endif; ?>
        <form method="post" class="contact-form">
            <input type="hidden" name="csrf_token" value="<?= $h(form_csrf_token()) ?>">
            <div class="contact-form__trap" aria-hidden="true">
                <label>ვებსაიტი <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="contact-form__row">
                <label>სახელი<input type="text" name="name" maxlength="100" required value="<?= $h($form['name']) ?>"></label>
                <label>ელფოსტა<input type="email" name="email" maxlength="190" required value="<?= $h($form['email']) ?>"></label>
            </div>
            <label>თემა<input type="text" name="subject" maxlength="150" value="<?= $h($form['subject']) ?>"></label>
            <label>შეტყობინება<textarea name="message" rows="6" maxlength="5000" required><?= $h($form['message']) ?></textarea></label>
            <button type="submit" class="sb-btn sb-btn--accent">გაგზავნა <i class="fas fa-paper-plane" aria-hidden="true"></i></button>
        </form>
    </section>

    <aside class="contact-aside">
        <h2>სხვა გზები</h2>
        <?php if ($contactEmail !== ''): ?>
            <p><i class="fas fa-envelope" aria-hidden="true"></i> <a href="mailto:<?= $h($contactEmail) ?>"><?= $h($contactEmail) ?></a></p>
        <?php endif; ?>
        <p>სტატიაში შეცდომა შენიშნეთ? მიუთითეთ სტატიის ბმული — სწრაფად გავასწორებთ.</p>
        <p>პერსონალური მონაცემების შესახებ იხილეთ <a href="/sport_blog/views/privacy">კონფიდენციალურობის პოლიტიკა</a>.</p>
    </aside>
</div>
<?php static_page_end(); ?>
