<?php

/*
 *  Copyright (C) 2012-2015 SC Ubirimi SRL <info-copyright@ubirimi.com>
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Ubirimi\Yongo\Controller\Administration\Workflow\Transition;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Ubirimi\UbirimiController;
use Ubirimi\Util;
use Ubirimi\Yongo\Repository\Screen\Screen;
use Ubirimi\Yongo\Repository\Workflow\Workflow;

class TransitionScreenController extends UbirimiController
{
    public function indexAction(Request $request, SessionInterface $session)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $clientId = $session->get('client/id');

        $stepIdFrom = $request->get('id_from');
        $stepIdTo = $request->get('id_to');
        $workflowId = $request->get('workflow_id');

        $workflowMetadata = $this->getRepository(Workflow::class)->getMetaDataById($workflowId);

        $workflowData = $this->getRepository(Workflow::class)->getDataByStepIdFromAndStepIdTo($workflowId, $stepIdFrom, $stepIdTo);
        $transitionName = $workflowData['transition_name'];
        $screens = $this->getRepository(Screen::class)->getAll($clientId);
        $initialStep = $this->getRepository(Workflow::class)->getInitialStep($workflowId);

        return $this->render(__DIR__ . '/../../../../Resources/views/administration/workflow/transition/TransitionScreen.php', get_defined_vars());
    }
}