<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
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
        $config = $this->context->config;

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : null;
        } elseif (($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) && $node->name !== null) {
            if ($config->scrambleClasses) {
                $this->addSymbol($node->name->toString());
            }
        } elseif ($node instanceof Function_) {
            if ($config->scrambleFunctions) {
                $this->addSymbol($node->name->toString());
            }
        } elseif ($node instanceof ClassMethod) {
            if ($config->scrambleMethods) {
                $this->addSymbol($node->name->toString());
            }
        } elseif ($node instanceof Property) {
            if ($config->scrambleProperties) {
                foreach ($node->props as $prop) {
                    $this->addSymbol($prop->name->toString());
                }
            }
        } elseif ($node instanceof Const_) {
            if ($config->scrambleConstants) {
                foreach ($node->consts as $const) {
                    $this->addSymbol($const->name->toString());
                }
            }
        } elseif ($node instanceof Node\Stmt\ClassConst) {
            if ($config->scrambleConstants) {
                foreach ($node->consts as $const) {
                    $this->addSymbol($const->name->toString());
                }
            }
        } elseif ($node instanceof Node\Stmt\EnumCase) {
            if ($config->scrambleClasses) {
                $this->addSymbol($node->name->toString());
            }
        } elseif ($node instanceof Variable && is_string($node->name)) {
            if ($config->scrambleVariables) {
                $this->addSymbol($node->name);
            }
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

        $this->context->generateUniqueSymbol($name);
    }
}
