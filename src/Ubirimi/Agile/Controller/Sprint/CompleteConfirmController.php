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

namespace Ubirimi\Agile\Controller\Sprint;

use Symfony\Component\HttpFoundation\Request;
use Ubirimi\Agile\Repository\Board\Board;
use Ubirimi\Agile\Repository\Sprint\Sprint;
use Ubirimi\UbirimiController;
use Ubirimi\Util;

class CompleteConfirmController extends UbirimiController
{
    public function indexAction(Request $request)
    {
        Util::checkUserIsLoggedInAndRedirect();

        $sprintId = $request->get('id');
        $boardId = $request->get('board_id');

        $sprint = $this->getRepository(Sprint::class)->getById($sprintId);
        $lastColumn = $this->getRepository(Board::class)->getLastColumn($boardId);
        $completeStatuses = $this->getRepository(Board::class)->getColumnStatuses($lastColumn['id'], 'array', 'id');

        $issuesInSprintCount = $this->getRepository(Sprint::class)->getSprintIssuesCount($sprintId);
        $completedIssuesInSprint = $this->getRepository(Sprint::class)->getCompletedIssuesCountBySprintId($sprintId, $completeStatuses);

        return $this->render(__DIR__ . '/../../Resources/views/sprint/CompleteConfirm.php', get_defined_vars());
    }
}
