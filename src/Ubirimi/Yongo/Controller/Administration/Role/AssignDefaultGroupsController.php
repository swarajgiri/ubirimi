<?php

namespace Ubirimi\Yongo\Controller\Administration\Role;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\UbirimiController;
use Ubirimi\Util;
use Ubirimi\Yongo\Repository\Permission\Role;
use Ubirimi\SystemProduct;
use Ubirimi\Repository\Log;

class AssignDefaultGroupsController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $permissionRoleId = $request->request->get('role_id');
        $groupArrayIds = $request->request->get('group_arr');

        $currentDate = Util::getServerCurrentDateTime();
        $permissionRole = Role::getById($permissionRoleId);
        Role::deleteDefaultGroupsByPermissionRoleId($permissionRoleId);
        Role::addDefaultGroups($permissionRoleId, $groupArrayIds, $currentDate);

        Log::add(
            $session->get('client/id'),
            SystemProduct::SYS_PRODUCT_YONGO,
            $session->get('user/id'),
            'UPDATE Yongo Project Role ' . $permissionRole['name'] . ' Definition',
            $currentDate
        );

        return new Response('');
    }
}
