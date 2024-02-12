<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under following license:
 * - Pimcore Commercial License (PCL)
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     PCL
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\DependencyInjection\Compiler;

use Exception;
use Pimcore\Bundle\GenericDataIndexBundle\Enum\DependencyInjection\CompilerPassTag;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Modifier\SearchModifierContextInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Modifier\SearchModifierInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\ModifierService\SearchModifierServiceInterface;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SearchModifierHandlerPass implements CompilerPassInterface
{
    /**
     * @throws ReflectionException
     */
    public function process(ContainerBuilder $container): void
    {
        $taggedServiceIds = $container->findTaggedServiceIds(
            CompilerPassTag::SEARCH_MODIFIER_HANDLER->value,
            true
        );

        $searchModifierServiceDefinition = $container->getDefinition(SearchModifierServiceInterface::class);

        foreach ($taggedServiceIds as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $className = $this->getServiceClass($container, $serviceId);
                $r = $container->getReflectionClass($className);
                if($r === null) {
                    continue;
                }

                $method = $tag['method'] ?? '__invoke';

                $handles = $this->guessHandledClasses($r, $serviceId, $method);

                foreach($handles as $handledClass) {
                    $searchModifierServiceDefinition->addMethodCall('addSearchModifierHandler', [$handledClass, new Reference($serviceId), $method]);
                }

            }
        }
    }

    private function guessHandledClasses(\ReflectionClass $handlerClass, string $serviceId, string $methodName): iterable
    {
        try {
            $method = $handlerClass->getMethod($methodName);
        } catch (\ReflectionException) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": class "%s" must have an "%s()" method.', $serviceId, $handlerClass->getName(), $methodName));
        }

        if ($method->getNumberOfRequiredParameters() !== 2) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": method "%s::%s()" requires exactly two arguments, first one being the search modifier model it handles and second one the SearchModifierContext object.', $serviceId, $handlerClass->getName(), $methodName));
        }

        $parameters = $method->getParameters();

        /** @var ReflectionNamedType|ReflectionUnionType|null */
        $searchModifierType = $parameters[0]->getType();
        $searchModifierValid = $this->checkArgumentInstanceOf(
            $searchModifierType,
            SearchModifierInterface::class
        );
        //@todo check for ReflectionUnionType if !$searchModifierValid

        if(!$searchModifierValid) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": argument "$%s" of method "%s::%s()" must have a type-hint corresponding to the search modifier model class it handles (implementing SearchModifierInterface).', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $methodName));
        }

        $contextType = $parameters[1]->getType();
        $contextTypeValid = $this->checkArgumentInstanceOf(
            $contextType,
            SearchModifierContextInterface::class,
            true
        );

        if(!$contextTypeValid) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": argument "$%s" of method "%s::%s()" must have a type-hint on SearchModifierContextInterface.', $serviceId, $parameters[1]->getName(), $handlerClass->getName(), $methodName));
        }

        //        try ($parameters[0]->getType()->getTypes()) {
        //            throw new RuntimeException(sprintf('Invalid handler service "%s": argument "$%s" of method "%s::%s()" must have a type-hint corresponding to the search modifier model class it handles.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $methodName));
        //        } catch (\ReflectionException) {
        //            // noop
        //        }

        if ($searchModifierType instanceof ReflectionUnionType) {
            $types = [];
            $invalidTypes = [];
            foreach ($searchModifierType->getTypes() as $type) {
                if (!$type->isBuiltin()) {
                    $types[] = (string) $type;
                } else {
                    $invalidTypes[] = (string) $type;
                }
            }

            if ($types) {
                return ('__invoke' === $methodName) ? $types : array_fill_keys($types, $methodName);
            }

            throw new RuntimeException(sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::__invoke()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), implode('|', $invalidTypes)));
        }

        if ($searchModifierType->isBuiltin()) {
            throw new RuntimeException(sprintf('Invalid handler service "%s": type-hint of argument "$%s" in method "%s::%s()" must be a class , "%s" given.', $serviceId, $parameters[0]->getName(), $handlerClass->getName(), $methodName, $type instanceof ReflectionNamedType ? $type->getName() : (string) $type));
        }

        return ('__invoke' === $methodName) ? [$searchModifierType->getName()] : [$searchModifierType->getName() => $methodName];
    }

    private function checkArgumentInstanceOf(
        ReflectionNamedType|ReflectionUnionType|null $type,
        string $classOrInterface,
        bool $interfaceAllowed = false
    ): bool {
        try {
            $searchModifierValid = $type instanceof ReflectionNamedType
                && (
                    ($interfaceAllowed && $classOrInterface === $type->getName())
                    || in_array($classOrInterface, class_implements($type->getName()), true)
                );

            //@todo check for ReflectionUnionType if !$searchModifierValid
            return $searchModifierValid;
        } catch(Exception $e) {
            return false;
        }
    }

    private function getServiceClass(ContainerBuilder $container, string $serviceId): string
    {
        while (true) {
            $definition = $container->findDefinition($serviceId);

            if ($definition instanceof ChildDefinition && !$definition->getClass()) {
                $serviceId = $definition->getParent();

                continue;
            }

            return $definition->getClass();
        }
    }
}
