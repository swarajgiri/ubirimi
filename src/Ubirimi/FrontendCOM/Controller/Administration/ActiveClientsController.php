<?php

namespace Ubirimi\FrontendCOM\Controller\Administration;

use Ubirimi\Repository\Client;
use Ubirimi\UbirimiController;
use Ubirimi\Util;

class ActiveClientsController extends UbirimiController
{
    public function indexAction()
    {
        Util::checkSuperUserIsLoggedIn();

        $clients = Client::getLastMonthActiveClients();

        $selectedOption = 'active_clients_last_month';

        return $this->render(__DIR__ . '/../../Resources/views/administration/ActiveClients.php', get_defined_vars());
    }
}
