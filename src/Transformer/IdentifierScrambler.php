<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class IdentifierScrambler extends NodeVisitorAbstract implements TransformerInterface
{
    private ObfuscationContext $context;

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function transform(array $nodes, ObfuscationContext $context): array
    {
        if (!$context->config->scrambleIdentifiers) {
            return $nodes;
        }

        $this->context = $context;

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        return $traverser->traverse($nodes);
    }

    public function enterNode(Node $node): ?Node
    {
        // Variable variables: $$var
        if ($node instanceof Variable && !is_string($node->name)) {
            $this->addWarning("Variable variable detected ($$...). Scrambling might break this.");
        }

        // Variables
        if ($node instanceof Variable && is_string($node->name)) {
            if ($this->shouldScrambleVariable($node->name)) {
                $node->name = $this->getScrambledName($node->name, true);
            }
        }

        // Function declarations
        if ($node instanceof Function_) {
            $originalName = $node->name->toString();
            if ($this->shouldScrambleFunction($originalName)) {
                $node->name = new Identifier($this->getScrambledName($originalName, true));
            }
        }

        // Function calls
        if ($node instanceof FuncCall) {
            if ($node->name instanceof Name) {
                $originalName = $node->name->toString();
                if ($originalName === 'eval') {
                    $this->addWarning("eval() detected. Code inside eval() will not be obfuscated and might fail to find scrambled symbols.");
                }

                // ONLY scramble if it's in our symbol map (meaning it was declared in the project)
                if ($this->context->getSymbol($originalName) !== null) {
                    $node->name = new Name($this->getScrambledName($originalName));
                }
            } else {
                $this->addWarning("Dynamic function call detected. Scrambling might break this.");
            }
        }

        // Class/Interface/Trait/Enum declarations
        if (($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) && $node->name !== null) {
            $originalName = $node->name->toString();
            if ($this->shouldScrambleClass($originalName)) {
                $node->name = new Identifier($this->getScrambledName($originalName, true));
            }
        }

        // Class methods
        if ($node instanceof Node\Stmt\ClassMethod) {
            $originalName = $node->name->toString();
            if ($this->shouldScrambleFunction($originalName)) {
                $node->name = new Identifier($this->getScrambledName($originalName, true));
            }
        }

        // Properties
        if ($node instanceof Node\Stmt\Property) {
            foreach ($node->props as $prop) {
                $originalName = $prop->name->toString();
                if ($this->shouldScrambleVariable($originalName)) {
                    $prop->name = new Node\VarLikeIdentifier($this->getScrambledName($originalName, true));
                }
            }
        }

        // Constants and Class Constants
        if ($node instanceof Node\Stmt\Const_ || $node instanceof Node\Stmt\ClassConst) {
            foreach ($node->consts as $const) {
                $originalName = $const->name->toString();
                if ($this->shouldScrambleFunction($originalName)) { // Using shouldScrambleFunction as a proxy for constants
                    $const->name = new Identifier($this->getScrambledName($originalName, true));
                }
            }
        }

        // Enum cases
        if ($node instanceof Node\Stmt\EnumCase) {
            $originalName = $node->name->toString();
            if ($this->shouldScrambleClass($originalName)) { // Using shouldScrambleClass as a proxy for enums
                $node->name = new Identifier($this->getScrambledName($originalName, true));
            }
        }

        // Identifiers in expressions (MethodCall, PropertyFetch, ConstFetch, etc.)
        if ($node instanceof Identifier) {
            $originalName = $node->toString();
            $scrambled = $this->context->getSymbol($originalName);
            if ($scrambled !== null) {
                return new Identifier($scrambled);
            }
        }

        // Names (usages of classes, etc.)
        if ($node instanceof Name) {
            $originalName = $node->toString();
            // If it's a known symbol (already in map from collector), scramble it.
            $scrambled = $this->context->getSymbol($originalName);
            if ($scrambled !== null) {
                return new Name($scrambled);
            }

            // Fallback for namespaced names if scrambleNamespaces is false
            if (!$this->context->config->scrambleNamespaces && $node->isQualified()) {
                $lastPart = $node->getLast();
                $scrambledPart = $this->context->getSymbol($lastPart);
                if ($scrambledPart !== null) {
                    $parts = $node->getParts();
                    $parts[count($parts) - 1] = $scrambledPart;
                    $className = get_class($node);
                    return new $className($parts);
                }
            }
        }

        // Method call with dynamic name
        if ($node instanceof MethodCall && !($node->name instanceof Identifier)) {
            $this->addWarning("Dynamic method call detected. Scrambling might break this.");
        }

        // Property fetch with dynamic name
        if ($node instanceof Node\Expr\PropertyFetch && !($node->name instanceof Identifier)) {
            $this->addWarning("Dynamic property fetch detected. Scrambling might break this.");
        }
        
        // Static property fetch with dynamic name
        if ($node instanceof Node\Expr\StaticPropertyFetch && !($node->name instanceof Identifier)) {
            $this->addWarning("Dynamic static property fetch detected. Scrambling might break this.");
        }

        // Use statements: skip alias scrambling
        if ($node instanceof Node\Stmt\UseUse && $node->alias !== null) {
            // Traverse name part manually
            $result = $this->enterNode($node->name);
            if ($result instanceof Name) {
                $node->name = $result;
            }
            return $node; // Stop traversing children (alias is a child)
        }

        return null;
    }

    private function addWarning(string $message): void
    {
        $filePath = $this->context->currentFilePath ?? 'unknown';
        $this->context->warningRegistry->addWarning($filePath, $message);
    }

    private function shouldScrambleClass(string $name): bool
    {
        if (in_array($name, $this->context->config->ignoreClasses, true)) {
            return false;
        }

        return $this->context->config->scrambleClasses;
    }

    private function shouldScrambleVariable(string $name): bool
    {
        if ($name === 'this') {
            return false;
        }

        // Skip superglobals
        $superglobals = ['GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV'];
        if (in_array($name, $superglobals, true)) {
            return false;
        }

        if (in_array($name, $this->context->config->ignoreVariables, true)) {
            return false;
        }

        return $this->context->config->scrambleVariables;
    }

    private function shouldScrambleFunction(string $name): bool
    {
        if (str_starts_with($name, '__')) {
            return false;
        }

        if (in_array($name, $this->context->config->ignoreFunctions, true)) {
            return false;
        }

        return $this->context->config->scrambleFunctions;
    }

    private function getScrambledName(string $original, bool $createIfMissing = false): string
    {
        if ($createIfMissing) {
            return $this->context->generateUniqueSymbol($original);
        }

        return $this->context->getSymbol($original) ?? $original;
    }
}
