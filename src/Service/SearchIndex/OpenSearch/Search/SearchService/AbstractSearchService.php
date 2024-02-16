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

namespace Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\OpenSearch\Search\SearchService;

use Pimcore\Bundle\GenericDataIndexBundle\Exception\ValidationFailedException;
use Pimcore\Bundle\GenericDataIndexBundle\Model\OpenSearch\Search;
use Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Interfaces\SearchInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Search\Pagination\PaginationInfoServiceInterface;
use Pimcore\Bundle\GenericDataIndexBundle\Service\Search\SearchService\SearchServiceInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @internal
 */
abstract class AbstractSearchService implements SearchServiceInterface
{
    protected ValidatorInterface $validator;

    protected PaginationInfoServiceInterface $paginationInfoService;

    /**
     * @throws ValidationFailedException
     */
    protected function validateSearchModel(SearchInterface $search): self
    {

        $errors = $this->validator->validate($search);

        foreach($search->getModifiers() as $modifier) {
            $errors->addAll($this->validator->validate($modifier));
        }

        if (count($errors) > 0) {
            throw new ValidationFailedException($search, $errors);
        }

        return $this;
    }

    protected function createPaginatedSearch(int $page, int $pageSize): Search
    {
        return new Search(
            from: $this->paginationInfoService->calculateFrom($page, $pageSize),
            size: $pageSize
        );
    }



    #[Required]
    public function setServices(
        PaginationInfoServiceInterface $paginationInfoService,
        ValidatorInterface $validator,
    ): AbstractSearchService
    {
        $this->paginationInfoService = $paginationInfoService;
        $this->validator = $validator;

        return $this;
    }
}
