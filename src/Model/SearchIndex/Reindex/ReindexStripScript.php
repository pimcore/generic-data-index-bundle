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

namespace Pimcore\Bundle\GenericDataIndexBundle\Model\SearchIndex\Reindex;

use InvalidArgumentException;

/**
 * The script of a _reindex that removes fields from every copied document.
 *
 * The Painless source is fixed: the field paths are passed as params and never become part of
 * the source. The script walks maps and lists, so a path reaches into localized fields
 * (field.language), object bricks and every item of a nested list (field collections, blocks).
 * Keys with dots (e.g. classification store keys like "spec.width") stay one literal key in the
 * document while the engine maps them as objects, so each step also tries the joined segments, and
 * removing a key also removes the literal keys below it ("spec" removes "spec.width"). The stored
 * checksum is removed too: the index service skips a document whose rebuilt version has the same
 * checksum, and the rebuild must write every document to refill the stripped fields.
 *
 * @internal
 */
final readonly class ReindexStripScript
{
    private const SOURCE = <<<'PAINLESS'
        void removeWithDottedChildren(Map node, String key) {
          node.remove(key);
          String prefix = key + '.';
          for (def k : new ArrayList(node.keySet())) { if (k.startsWith(prefix)) { node.remove(k); } }
        }
        void strip(def node, List path, int i) {
          if (node instanceof List) { for (def item : node) { strip(item, path, i); } return; }
          if (!(node instanceof Map)) { return; }
          String key = null;
          for (int j = i; j < path.size(); j++) {
            key = key == null ? path.get(j) : key + '.' + path.get(j);
            if (j == path.size() - 1) { removeWithDottedChildren(node, key); return; }
            if (node.containsKey(key)) { strip(node.get(key), path, j + 1); }
          }
        }
        for (def p : params.paths) { strip(ctx._source, p, 0); }
        if (ctx._source.system_fields instanceof Map) { ctx._source.system_fields.remove('checksum'); }
        PAINLESS;

    /** @var string[][] */
    private array $paths;

    /**
     * @param string[][] $paths path segments per field, e.g. [['standard_fields', 'price']]
     */
    public function __construct(array $paths)
    {
        if ($paths === []) {
            throw new InvalidArgumentException('A strip script needs at least one field path.');
        }
        foreach ($paths as $path) {
            if (!is_array($path) || $path === [] || !array_is_list($path)) {
                throw new InvalidArgumentException('Each field path must be a non-empty list of segments.');
            }
            foreach ($path as $segment) {
                if (!is_string($segment) || $segment === '') {
                    throw new InvalidArgumentException('Field path segments must be non-empty strings.');
                }
            }
        }
        $this->paths = array_values($paths);
    }

    /**
     * @return string[][]
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * The "script" part of a _reindex request body.
     *
     * @return array{lang: string, source: string, params: array{paths: string[][]}}
     */
    public function toRequestScript(): array
    {
        return [
            'lang' => 'painless',
            'source' => self::SOURCE,
            'params' => ['paths' => $this->paths],
        ];
    }
}
