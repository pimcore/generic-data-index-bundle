<?php

namespace Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex;

enum FieldCategory: string
{
    case SYSTEM_FIELDS = 'system_fields';
    case STANDARD_FIELDS = 'standard_fields';
    case CUSTOM_FIELDS = 'custom_fields';
}
