<?php

namespace Ubirimi\General\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\UbirimiController;
use Ubirimi\Util;

class EditSettingsController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {

        Util::checkUserIsLoggedInAndRedirect();

        $clientId = $session->get('client/id');

        $session->set('selected_product_id', -1);
        $menuSelectedCategory = 'general_overview';

        $timezoneData = explode("/", $session->get('client/settings/timezone'));
        $timezoneContinent = $timezoneData[0];
        $timeZoneContinents = array('Africa' => 1, 'America' => 2, 'Antarctica' => 4, 'Arctic' => 8, 'Asia' => 16,
            'Atlantic' => 32, 'Australia' => 64, 'Europe' => 128, 'Indian' => 256, 'Pacific' => 512);
        $timeZoneCountry = $timezoneData[1];

        $clientSettings = $this->getRepository('ubirimi.general.client')->getSettings($clientId);
        $client = $this->getRepository('ubirimi.general.client')->getById($clientId);

        if ($request->request->has('update_configuration')) {

            $language = Util::cleanRegularInputField($request->request->get('language'));
            $timezone = Util::cleanRegularInputField($request->request->get('zone'));
            $titleName = Util::cleanRegularInputField($_POST['title_name']);
            $operatingMode = Util::cleanRegularInputField($request->request->get('mode'));

            $parameters = array(array('field' => 'title_name', 'value' => $titleName, 'type' => 's'),
                array('field' => 'operating_mode', 'value' => $operatingMode, 'type' => 's'),
                array('field' => 'language', 'value' => $language, 'type' => 's'),
                array('field' => 'timezone', 'value' => $timezone, 'type' => 's'));

            $this->getRepository('ubirimi.general.client')->updateProductSettings($clientId, 'client_settings', $parameters);

            $session->set('client/settings/language', $language);
            $session->set('client/settings/timezone', $timezone);

            return new RedirectResponse('/general-settings/view-general');
        }

        $sectionPageTitle = $session->get('client/settings/title_name') . ' / General Settings / Update';

        return $this->render(__DIR__ . '/../Resources/views/EditSettings.php', get_defined_vars());
    }
}