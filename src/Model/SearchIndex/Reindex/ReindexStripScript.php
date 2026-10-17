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
use stdClass;

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
    /**
     * Removes the stripped fields in one pass per object. params.tree holds the paths as a trie of
     * {c: children by segment, e: strip here}. Each key of an object is walked along its dot-separated
     * segments, because a document may hold a nested field as a literal dotted key ("spec.width"): reaching
     * a stripped prefix removes the key (the field itself, or a literal dotted child of it), consuming the
     * whole key in the middle of a path descends into its value, lists of objects item by item.
     */
    private const SOURCE = <<<'PAINLESS'
        void strip(def node, Map trie) {
          if (node instanceof List) { for (def item : node) { strip(item, trie); } return; }
          if (!(node instanceof Map) || !trie.containsKey('c')) { return; }
          Map children = trie.get('c');
          for (def key : new ArrayList(node.keySet())) {
            String k = key.toString();
            Map t = null;
            Map level = children;
            int from = 0;
            while (level != null) {
              int dot = k.indexOf('.', from);
              String segment = dot < 0 ? k.substring(from) : k.substring(from, dot);
              t = level.get(segment);
              if (t == null) { break; }
              if (t.containsKey('e')) { node.remove(key); break; }
              if (dot < 0) { strip(node.get(key), t); break; }
              level = t.containsKey('c') ? t.get('c') : null;
              from = dot + 1;
            }
          }
        }
        strip(ctx._source, params.tree);
        if (ctx._source.system_fields instanceof Map) { ctx._source.system_fields.remove('checksum'); }
        PAINLESS;

    /** @var string[][] */
    private array $paths;

    /**
     * @param array<mixed> $paths path segments per field, e.g. [['standard_fields', 'price']]
     */
    public function __construct(array $paths)
    {
        if ($paths === []) {
            throw new InvalidArgumentException('A strip script needs at least one field path.');
        }
        $validated = [];
        foreach ($paths as $path) {
            if (!is_array($path) || $path === [] || !array_is_list($path)) {
                throw new InvalidArgumentException('Each field path must be a non-empty list of segments.');
            }
            $segments = [];
            foreach ($path as $segment) {
                if (!is_string($segment) || $segment === '') {
                    throw new InvalidArgumentException('Field path segments must be non-empty strings.');
                }
                $segments[] = $segment;
            }
            $validated[] = $segments;
        }
        $this->paths = $validated;
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
     * @return array{lang: string, source: string, params: array{tree: stdClass}}
     */
    public function toRequestScript(): array
    {
        return [
            'lang' => 'painless',
            'source' => self::SOURCE,
            'params' => ['tree' => $this->buildTree()],
        ];
    }

    /**
     * The paths as a trie. Nodes are objects, so the request carries JSON objects even for a segment such as
     * "0" that a PHP array would turn into a list.
     */
    private function buildTree(): stdClass
    {
        $root = new stdClass();
        foreach ($this->paths as $path) {
            $node = $root;
            foreach ($path as $segment) {
                $node->c ??= new stdClass();
                $node->c->{$segment} ??= new stdClass();
                $node = $node->c->{$segment};
            }
            $node->e = true;
        }

        return $root;
    }
}
