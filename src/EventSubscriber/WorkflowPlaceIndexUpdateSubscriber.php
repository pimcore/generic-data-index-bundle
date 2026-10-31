<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\EventSubscriber;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\IndexQueueOperation;
use Pimcore\Bundle\GenericDataIndexBundle\Installer;
use Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\HitData;
use Pimcore\Bundle\GenericDataIndexBundle\Repository\IndexQueueRepository;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexQueue\QueueMessagesDispatcher;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\IndexService\ElementTypeAdapter\AdapterServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\TimeServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Traits\LoggerAwareTrait;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Event\Workflow\GlobalActionEvent;
use Pimcore\Event\WorkflowEvents as PimcoreWorkflowEvents;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Workflow\MarkingStore\StateTableMarkingStore;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\WorkflowEvents;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The state_table marking store persists places on its own: transitions, global actions and the initial
 * place written on first access do not necessarily save the element (and trigger the regular index update).
 *
 * Only the element is enqueued, never indexed synchronously. The elements are collected in memory and written to the
 * queue (and its messages dispatched) when the request, command or message is done: until then a failing element
 * save can still roll the place back, and neither the scheduler nor another dispatch can pick the element up early.
 *
 * @internal
 */
final class WorkflowPlaceIndexUpdateSubscriber implements EventSubscriberInterface
{
    use LoggerAwareTrait;

    /**
     * @var array<string, HitData>
     */
    private array $pendingHits = [];

    public function __construct(
        private readonly Installer $installer,
        private readonly AdapterServiceInterface $adapterService,
        private readonly IndexQueueRepository $indexQueueRepository,
        private readonly TimeServiceInterface $timeService,
        private readonly QueueMessagesDispatcher $queueMessagesDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkflowEvents::ENTERED => 'onEntered',
            PimcoreWorkflowEvents::POST_GLOBAL_ACTION => 'onPostGlobalAction',
            AssetEvents::POST_DELETE => 'onElementDeleted',
            DataObjectEvents::POST_DELETE => 'onElementDeleted',
            DocumentEvents::POST_DELETE => 'onElementDeleted',
            KernelEvents::TERMINATE => 'dispatchQueueMessages',
            ConsoleEvents::TERMINATE => 'dispatchQueueMessages',
            WorkerMessageHandledEvent::class => 'dispatchQueueMessages',
            WorkerMessageFailedEvent::class => 'dispatchQueueMessages',
        ];
    }

    public function onEntered(EnteredEvent $event): void
    {
        $this->enqueue($event->getSubject(), $event->getWorkflow());
    }

    public function onPostGlobalAction(GlobalActionEvent $event): void
    {
        // Without target places the marking is not changed.
        if ($event->getGlobalAction()->getTos() === []) {
            return;
        }

        $this->enqueue($event->getSubject(), $event->getWorkflow());
    }

    /**
     * The regular delete subscribers queue a DELETE for the element. Writing the collected update at terminate
     * would overwrite that entry (one queue row per element) and leave the deleted element in the index.
     */
    public function onElementDeleted(ElementEventInterface $event): void
    {
        if ($this->pendingHits === []) {
            return;
        }

        $element = $event->getElement();
        try {
            unset($this->pendingHits[$this->getHitKey($element)]);
        } catch (Exception $e) {
            $this->logger->error('Removing the deleted element from the collected workflow changes failed', [
                'elementId' => $element->getId(),
                'elementType' => $element->getType(),
                'exception' => $e,
            ]);
        }
    }

    public function dispatchQueueMessages(): void
    {
        if ($this->pendingHits === []) {
            return;
        }

        $hits = array_values($this->pendingHits);
        $this->pendingHits = [];

        try {
            $this->indexQueueRepository->enqueueByItemList(
                $hits,
                IndexQueueOperation::UPDATE,
                $this->timeService->getCurrentMillisecondTimestamp()
            );
            $this->queueMessagesDispatcher->dispatchQueueMessages();
        } catch (Exception $e) {
            // A workflow change must not fail the request because of the index. The queue entries which were
            // written stay and are dispatched with the next queue messages.
            $this->logger->error('Enqueueing the elements after a workflow place change failed', [
                'elementIds' => array_map(static fn (HitData $hit): string => $hit->getId(), $hits),
                'exception' => $e,
            ]);
        }
    }

    private function enqueue(mixed $element, WorkflowInterface $workflow): void
    {
        if (
            !$element instanceof ElementInterface ||
            !$workflow->getMarkingStore() instanceof StateTableMarkingStore ||
            !$this->installer->isInstalled()
        ) {
            return;
        }

        try {
            // Workflow places are not inherited and not part of other elements, so no related element is enqueued.
            $typeAdapter = $this->adapterService->getTypeAdapter($element);
            $hit = new HitData(
                (string) $element->getId(),
                $typeAdapter->getElementType(),
                $typeAdapter->getIndexNameShortByElement($element)
            );
            $this->pendingHits[$this->getHitKey($element)] = $hit;
        } catch (Exception $e) {
            // A workflow change (or Pimcore Studio reading the initial place) must not fail because of the index.
            $this->logger->error('Collecting the element after a workflow place change failed', [
                'elementId' => $element->getId(),
                'elementType' => $element->getType(),
                'workflow' => $workflow->getName(),
                'exception' => $e,
            ]);
        }
    }

    private function getHitKey(ElementInterface $element): string
    {
        return $this->adapterService->getTypeAdapter($element)->getElementType() . '_' . $element->getId();
    }
}
