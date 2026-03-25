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

        // Names (usages of classes, etc.)
        if ($node instanceof Name) {
            $originalName = $node->toString();
            // If it's a known symbol (already in map from collector), scramble it.
            $scrambled = $this->context->getSymbol($originalName);
            if ($scrambled !== null) {
                return new Name($scrambled);
            }
        }

        // Named arguments
        if ($node instanceof Arg && $node->name !== null) {
            $originalName = $node->name->toString();
            $scrambled = $this->context->getSymbol($originalName);
            if ($scrambled !== null) {
                $node->name = new Identifier($scrambled);
            }
        }

        // New expression with dynamic class name
        if ($node instanceof New_ && !($node->class instanceof Name || $node->class instanceof Class_)) {
            $this->addWarning("New expression with dynamic class name detected. Scrambling might break this.");
        }

        // Method call with dynamic name
        if ($node instanceof MethodCall && !($node->name instanceof Identifier)) {
            $this->addWarning("Dynamic method call detected. Scrambling might break this.");
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
        if (in_array($name, $this->context->config->ignoreFunctions, true)) {
            return false;
        }

        return $this->context->config->scrambleFunctions;
    }

    private function getScrambledName(string $original, bool $createIfMissing = false): string
    {
        $scrambled = $this->context->getSymbol($original);
        if ($scrambled === null && $createIfMissing) {
            $scrambled = $this->context->scrambler->scramble($original);
            $this->context->setSymbol($original, $scrambled);
        }

        return $scrambled ?? $original;
    }
}
