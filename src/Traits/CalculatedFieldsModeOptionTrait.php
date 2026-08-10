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

namespace Pimcore\Bundle\GenericDataIndexBundle\Traits;

use Pimcore\Bundle\GenericDataIndexBundle\Enum\SearchIndex\CalculatedFieldsIndexMode;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\CalculatedFieldsIndexModeResolverInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Adds a --calculated-fields-mode option to a reindex command. The override is set on the
 * resolver before the queue is dispatched synchronously; QueueMessageService reads it there
 * and carries it on each IndexUpdateQueueMessage, so it reaches the worker that extracts the
 * values (extraction never happens in the command process itself).
 *
 * @internal
 */
trait CalculatedFieldsModeOptionTrait
{
    private const OPTION_CALCULATED_FIELDS_MODE = 'calculated-fields-mode';

    private CalculatedFieldsIndexModeResolverInterface $calculatedFieldsIndexModeResolver;

    #[Required]
    public function setCalculatedFieldsIndexModeResolver(
        CalculatedFieldsIndexModeResolverInterface $calculatedFieldsIndexModeResolver
    ): void {
        $this->calculatedFieldsIndexModeResolver = $calculatedFieldsIndexModeResolver;
    }

    protected function addCalculatedFieldsModeOption(): void
    {
        $this->addOption(
            self::OPTION_CALCULATED_FIELDS_MODE,
            null,
            InputOption::VALUE_REQUIRED,
            'Override calculated_fields_index_mode for this run (live|query_store), e.g. "live" '
            . 'to refresh calculated values in the index while the configured mode is query_store.'
        );
    }

    /**
     * Applies the override if the option was given. Returns false on an invalid value so the
     * caller can abort with a non-zero exit code.
     */
    protected function applyCalculatedFieldsModeOption(InputInterface $input, OutputInterface $output): bool
    {
        $value = $input->getOption(self::OPTION_CALCULATED_FIELDS_MODE);
        if ($value === null) {
            return true;
        }

        $mode = CalculatedFieldsIndexMode::tryFrom((string) $value);
        if ($mode === null) {
            $output->writeln(sprintf(
                '<error>Invalid --%s "%s". Valid values: %s</error>',
                self::OPTION_CALCULATED_FIELDS_MODE,
                $value,
                implode(', ', array_column(CalculatedFieldsIndexMode::cases(), 'value'))
            ));

            return false;
        }

        $this->calculatedFieldsIndexModeResolver->overrideMode($mode);

        return true;
    }
}
