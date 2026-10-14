<?php

use Flarum\Extend;
use Itqan\Statistics\Api\Controller\AddTeamMembersController;
use Itqan\Statistics\Api\Controller\CreateTeamController;
use Itqan\Statistics\Api\Controller\DeleteTeamController;
use Itqan\Statistics\Api\Controller\ListTeamsController;
use Itqan\Statistics\Api\Controller\RebuildStatisticsController;
use Itqan\Statistics\Api\Controller\RemoveTeamMemberController;
use Itqan\Statistics\Api\Controller\SearchUsersController;
use Itqan\Statistics\Api\Controller\ShowDashboardController;
use Itqan\Statistics\Api\Controller\UpdateTeamController;
use Itqan\Statistics\Console\RebuildCommand;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Routes('api'))
        ->get('/itqan-statistics/dashboard', 'itqan-statistics.dashboard', ShowDashboardController::class)
        ->post('/itqan-statistics/rebuild', 'itqan-statistics.rebuild', RebuildStatisticsController::class)
        ->get('/itqan-statistics/users', 'itqan-statistics.users.search', SearchUsersController::class)
        ->get('/itqan-statistics/teams', 'itqan-statistics.teams.index', ListTeamsController::class)
        ->post('/itqan-statistics/teams', 'itqan-statistics.teams.create', CreateTeamController::class)
        ->patch('/itqan-statistics/teams/{id:\d+}', 'itqan-statistics.teams.update', UpdateTeamController::class)
        ->delete('/itqan-statistics/teams/{id:\d+}', 'itqan-statistics.teams.delete', DeleteTeamController::class)
        ->post('/itqan-statistics/teams/{id:\d+}/members', 'itqan-statistics.teams.members.add', AddTeamMembersController::class)
        ->delete('/itqan-statistics/teams/{id:\d+}/members/{userId:\d+}', 'itqan-statistics.teams.members.remove', RemoveTeamMemberController::class),

    (new Extend\Settings())
        ->default('itqan-statistics.timezone', 'Asia/Riyadh')
        ->default('itqan-statistics.default_period', 'mtd'),

    (new Extend\Console())
        ->command(RebuildCommand::class),
];
