<?php
    use Ubirimi\Agile\Repository\AgileBoard;
    use Ubirimi\Agile\Repository\AgileSprint;
    use Ubirimi\Util;

    Util::checkUserIsLoggedInAndRedirect();

    $name = $_POST['name'];
    $boardId = $_POST['board_id'];

    $date = Util::getCurrentDateTime($session->get('client/settings/timezone'));
    AgileSprint::add($boardId, $name, $date, $loggedInUserId);