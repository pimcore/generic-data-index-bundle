<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces;

interface AdapterSearchInterface
{
    public function getFrom(): ?int;

    public function getSize(): ?int;

    public function toArray(): array;
}