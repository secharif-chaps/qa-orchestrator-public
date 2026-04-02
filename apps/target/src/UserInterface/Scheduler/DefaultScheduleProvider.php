<?php

declare(strict_types=1);

namespace App\UserInterface\Scheduler;

use App\Application\Agent\TimeoutStaleExecutionsAction;
use App\Application\WatchFile\Quota\CheckDocumentQuotaAction;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class DefaultScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        #[Autowire('%env(int:AGENT_EXECUTION_TIMEOUT_SECONDS)%')]
        private readonly int $agentExecutionTimeout,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(
                RecurringMessage::every(
                    \sprintf('%d seconds', $this->agentExecutionTimeout),
                    new TimeoutStaleExecutionsAction()
                )
            )
            ->add(RecurringMessage::every('1 hour', new CheckDocumentQuotaAction()));
    }
}
