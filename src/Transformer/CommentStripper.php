<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class CommentStripper extends NodeVisitorAbstract implements TransformerInterface
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function transform(array $nodes, ObfuscationContext $context): array
    {
        if (!$context->config->stripComments) {
            return $nodes;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        return $traverser->traverse($nodes);
    }

    public function enterNode(Node $node): ?Node
    {
        $node->setAttribute('comments', []);
        return null;
    }
}
