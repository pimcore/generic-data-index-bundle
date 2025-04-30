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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\Search\Document\SearchResult\SearchResultItem;

/**
 * @internal
 */
trait PageSnippetTrait
{
    private ?string $controller;

    private ?string $template;

    private ?int $contentMainDocumentId;

    private bool $supportsContentMain;

    private bool $missingRequiredEditable;

    private bool $staticGeneratorEnabled;

    private ?int $staticGeneratorLifetime;

    public function getController(): ?string
    {
        return $this->controller;
    }

    public function setController(?string $controller): static
    {
        $this->controller = $controller;

        return $this;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function getContentMainDocumentId(): ?int
    {
        return $this->contentMainDocumentId;
    }

    public function setContentMainDocumentId(?int $contentMainDocumentId): static
    {
        $this->contentMainDocumentId = $contentMainDocumentId;

        return $this;
    }

    public function isSupportsContentMain(): bool
    {
        return $this->supportsContentMain;
    }

    public function setSupportsContentMain(bool $supportsContentMain): static
    {
        $this->supportsContentMain = $supportsContentMain;

        return $this;
    }

    public function isMissingRequiredEditable(): bool
    {
        return $this->missingRequiredEditable;
    }

    public function setMissingRequiredEditable(?bool $missingRequiredEditable): static
    {
        $this->missingRequiredEditable = (bool)$missingRequiredEditable;

        return $this;
    }

    public function isStaticGeneratorEnabled(): bool
    {
        return $this->staticGeneratorEnabled;
    }

    public function setStaticGeneratorEnabled(?bool $staticGeneratorEnabled): static
    {
        $this->staticGeneratorEnabled = (bool)$staticGeneratorEnabled;

        return $this;
    }

    public function getStaticGeneratorLifetime(): ?int
    {
        return $this->staticGeneratorLifetime;
    }

    public function setStaticGeneratorLifetime(?int $staticGeneratorLifetime): static
    {
        $this->staticGeneratorLifetime = $staticGeneratorLifetime;

        return $this;
    }
}
