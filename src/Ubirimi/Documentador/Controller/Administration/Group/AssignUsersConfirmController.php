<?php

namespace Ubirimi\Documentador\Controller\Administration\Group;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\UbirimiController;
use Ubirimi\Util;

class AssignUsersConfirmController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $clientId = $session->get('client/id');
        $groupId = $request->get('id');

        $group = $this->getRepository('ubirimi.user.group')->getMetadataById($groupId);
        $allUsers = $this->getRepository('ubirimi.general.client')->getUsers($clientId);
        $groupUsers = $this->getRepository('ubirimi.user.group')->getDataByGroupId($groupId);

        $groupUsersArrayIds = array();

        while ($groupUsers && $user = $groupUsers->fetch_array(MYSQLI_ASSOC))
            $groupUsersArrayIds[] = $user['user_id'];
        if ($groupUsers)
            $groupUsers->data_seek(0);

        $firstSelected = true;

        return $this->render(__DIR__ . '/../../../Resources/views/administration/group/AssignUsersConfirm.php', get_defined_vars());
    }
}