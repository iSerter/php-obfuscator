<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class SymbolCollector extends NodeVisitorAbstract implements TransformerInterface
{
    private ObfuscationContext $context;

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function transform(array $nodes, ObfuscationContext $context): array
    {
        $this->context = $context;

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        $traverser->traverse($nodes);

        return $nodes;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Class_ && $node->name !== null) {
            $this->addSymbol($node->name->toString());
        } elseif ($node instanceof Function_) {
            $this->addSymbol($node->name->toString());
        } elseif ($node instanceof ClassMethod) {
            $this->addSymbol($node->name->toString());
        } elseif ($node instanceof Property) {
            foreach ($node->props as $prop) {
                $this->addSymbol($prop->name->toString());
            }
        } elseif ($node instanceof Const_) {
            // Const_ names are strings in php-parser? No, they are identifiers?
            // Actually, Const_ has an array of consts.
            // Wait, nikic/php-parser v5: Const_ has an array of Const_ nodes (Wait, it's confusing)
            // Let's check Node/Stmt/Const_.
        } elseif ($node instanceof Variable && is_string($node->name)) {
            $this->addSymbol($node->name);
        }

        return null;
    }

    private function addSymbol(string $name): void
    {
        if ($this->context->getSymbol($name) === null) {
            // We only trigger scrambling if it's not already in the map.
            // This pre-fills the symbol map with declarations.
            $scrambled = $this->context->scrambler->scramble($name);
            $this->context->setSymbol($name, $scrambled);
        }
    }
}
