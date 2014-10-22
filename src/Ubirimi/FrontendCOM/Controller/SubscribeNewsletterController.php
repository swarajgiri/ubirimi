<?php
use Symfony\Component\HttpFoundation\RedirectResponse;
use Ubirimi\Repository\Newsletter;
    use Ubirimi\Util;

    if (isset($request->request->get('subscribe'))) {
        $emailAddress = Util::cleanRegularInputField($_POST['email_address']);
        $validEmailAddress = Util::isValidEmail($emailAddress);

        if ($validEmailAddress) {
            $currentDate = Util::getServerCurrentDateTime();
            $isDuplicate = Newsletter::checkEmailAddressDuplication($emailAddress);

            if (!$isDuplicate) {
                Newsletter::addSubscription($emailAddress, $currentDate);
                return new RedirectResponse('/subscribe-newsletter-done');
            }
        }
    }

    $page = 'newsletter';
    $content = 'SubscribeNewsletter.php';

    require_once __DIR__ . '/../Resources/views/_main.php';
