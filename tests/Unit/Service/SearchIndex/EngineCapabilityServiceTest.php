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

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Service\SearchIndex;

use Codeception\Test\Unit;
use IteratorAggregate;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\ClassificationStore\EngineCapabilityService;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineFeature;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineInterface;
use Pimcore\Model\DataObject\Classificationstore\Engine\EngineResolver;
use Pimcore\Model\DataObject\Exception\ClassificationStoreEngineNotAvailableException;
use Pimcore\Model\DataObject\Exception\UnknownClassificationStoreEngineException;
use Throwable;
use Traversable;

/**
 * The resolver is final, so the failure is raised where it really originates: while the registered
 * engines are enumerated, which is what every resolution starts with.
 *
 * @internal
 */
final class EngineCapabilityServiceTest extends Unit
{
    public function testAnEngineThatIsNotRegisteredHoldsNothing(): void
    {
        $service = $this->createService(new UnknownClassificationStoreEngineException(7, 'bogus'));

        $this->assertFalse($service->supports(null, EngineFeature::MultiValue));
        $this->assertFalse($service->supports(null, EngineFeature::Variants));
    }

    public function testAnEngineThisReleaseDoesNotShipHoldsNothing(): void
    {
        $service = $this->createService(new ClassificationStoreEngineNotAvailableException(7, 'later'));

        $this->assertFalse($service->supports(null, EngineFeature::MultiValue));
    }

    public function testAResolvedEngineAnswersForItself(): void
    {
        $engine = $this->makeEmpty(EngineInterface::class, [
            'supports' => static fn (EngineFeature $feature): bool => $feature === EngineFeature::Variants,
        ]);
        $service = new EngineCapabilityService(new EngineResolver(['eav' => $engine]));

        $this->assertTrue($service->supports(null, EngineFeature::Variants));
        $this->assertFalse($service->supports(null, EngineFeature::MultiValue));
    }

    /**
     * The registered engines reach the resolver as a tagged iterator, which is traversed afresh on
     * every enumeration - so the failure has to be raised again for the second call, not once.
     */
    private function createService(Throwable $failure): EngineCapabilityService
    {
        $engines = new class($failure) implements IteratorAggregate {
            public function __construct(private readonly Throwable $failure)
            {
            }

            public function getIterator(): Traversable
            {
                throw $this->failure;
            }
        };

        return new EngineCapabilityService(new EngineResolver($engines));
    }
}
