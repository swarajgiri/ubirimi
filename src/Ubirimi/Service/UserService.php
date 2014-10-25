<?php

namespace Ubirimi\Service;

use Ubirimi\Calendar\Repository\Calendar\UbirimiCalendar;
use Ubirimi\Calendar\Repository\Reminder\ReminderPeriod;
use Ubirimi\Calendar\Repository\Reminder\ReminderType;
use Ubirimi\Container\UbirimiContainer;
use Ubirimi\Event\UbirimiEvents;
use Ubirimi\Event\UserEvent;
use Ubirimi\Repository\General\UbirimiClient;
use Ubirimi\Repository\User\UbirimiGroup;
use Ubirimi\Repository\User\UbirimiUser as UserRepository;
use Ubirimi\SvnHosting\Repository\SvnRepository;
use Ubirimi\Util;
use Ubirimi\Yongo\Repository\Permission\GlobalPermission;


class UserService extends UbirimiService
{
    public function newUser($data)
    {
        $currentDate = Util::getServerCurrentDateTime();

        $issuesPerPage = $this->getRepository(UbirimiClient::class)->getYongoSetting($data['clientId'], 'issues_per_page');

        if (array_key_exists('isCustomer', $data) && $data['isCustomer']) {
            $data['customer_service_desk_flag'] = 1;
        } else {
            $data['customer_service_desk_flag'] = 0;
            $data['isCustomer'] = 0;
        }

        if (!array_key_exists('username', $data)) {
            $data['username'] = null;
        }

        if (!array_key_exists('password', $data)) {
            $data['password'] = null;
        }

        if (!array_key_exists('country', $data)) {
            $data['country'] = null;
        }

        $result = UserRepository::add($data['clientId'], $data['firstName'], $data['lastName'], $data['email'],
                                      $data['username'], $data['password'], $issuesPerPage,
                                      $data['customer_service_desk_flag'], $data['country'], $currentDate);

        $userId = $result[0];

        $defaultColumns = 'code#summary#priority#status#created#type#updated#reporter#assignee';
        UserRepository::updateDisplayColumns($userId, $defaultColumns);

        // add default calendar
        $calendarId = UbirimiCalendar::save($userId, $data['firstName'] . ' ' . $data['lastName'], 'My default calendar', '#A1FF9E', $currentDate, 1);

        if (!$data['isCustomer']) {
            // add default reminders
            UbirimiCalendar::addReminder($calendarId, ReminderType::REMINDER_EMAIL, ReminderPeriod::PERIOD_MINUTE, 30);

            // add the newly created user to the Ubirimi Users Global Permission Groups
            $groups = UbirimiContainer::get()['repository']->get(GlobalPermission::class)->getDataByPermissionId($data['clientId'], GlobalPermission::GLOBAL_PERMISSION_YONGO_USERS);
            while ($groups && $group = $groups->fetch_array(MYSQLI_ASSOC)) {
                $this->getRepository(UbirimiGroup::class)->addData($group['id'], array($userId), $currentDate);
            }
        }

        if (isset($data['svnRepoId'])) {
            /* also add user to svn_repository_user table */
            UbirimiContainer::get()['repository']->get(SvnRepository::class)->addUser($data['svnRepoId'], $userId);

            $userEvent = new UserEvent(UserEvent::STATUS_NEW_SVN, $data['firstName'], $data['lastName'], $data['username'], null, $data['email'], array('repositoryName' => $data['svnRepositoryName']));
            UbirimiContainer::get()['dispatcher']->dispatch(UbirimiEvents::USER, $userEvent);
        }

        $userEvent = new UserEvent(
            UserEvent::STATUS_NEW,
            $data['firstName'],
            $data['lastName'],
            $data['username'],
            $data['password'],
            $data['email'],
            array(
                'clientDomain' => $data['clientDomain'],
                'isCustomer' => $data['isCustomer'],
                'clientId' => $data['clientId']
            )
        );

        // todo: fix the commented lines
//        $logEvent = new LogEvent(SystemProduct::SYS_PRODUCT_GENERAL_SETTINGS, 'ADD User ' . $data['username']);

//        UbirimiContainer::get()['dispatcher']->dispatch(UbirimiEvents::LOG, $logEvent);
        UbirimiContainer::get()['dispatcher']->dispatch(UbirimiEvents::USER, $userEvent);

        return $userId;
    }
}
