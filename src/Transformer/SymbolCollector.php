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
    private ?string $currentNamespace = null;

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function transform(array $nodes, ObfuscationContext $context): array
    {
        $this->context = $context;
        $this->currentNamespace = null;

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        $traverser->traverse($nodes);

        return $nodes;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : null;
        } elseif (($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) && $node->name !== null) {
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
            foreach ($node->consts as $const) {
                $this->addSymbol($const->name->toString());
            }
        } elseif ($node instanceof Node\Stmt\ClassConst) {
            foreach ($node->consts as $const) {
                $this->addSymbol($const->name->toString());
            }
        } elseif ($node instanceof Node\Stmt\EnumCase) {
            $this->addSymbol($node->name->toString());
        } elseif ($node instanceof Variable && is_string($node->name)) {
            $this->addSymbol($node->name);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = null;
        }
    }

    private function addSymbol(string $name): void
    {
        if (str_starts_with($name, '__')) {
            return;
        }

        // For classes and functions, we might want to store the FQN
        // But for methods and properties, we usually store the short name because of polymorphism.
        
        // Let's keep it simple: if it's a class-like or function, use FQN if in namespace.
        // Actually, many obfuscators just use short names for everything and hope for the best, 
        // or they use a more sophisticated name resolution.
        
        // If we use FQN for classes, we must also resolve them in IdentifierScrambler.
        $this->context->generateUniqueSymbol($name);
    }
}
