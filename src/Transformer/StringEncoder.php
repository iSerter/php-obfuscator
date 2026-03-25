<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\Transformer;

use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

final class StringEncoder extends NodeVisitorAbstract implements TransformerInterface
{
    private string $key;

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function transform(array $nodes, ObfuscationContext $context): array
    {
        if (!$context->config->encodeStrings) {
            return $nodes;
        }

        $this->key = bin2hex(random_bytes(4));

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        $nodes = $traverser->traverse($nodes);

        return $nodes;
    }

    public function enterNode(Node $node): ?int
    {
        // Skip strings in constant-only contexts
        if ($node instanceof EnumCase || $node instanceof Param) {
            return NodeTraverser::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
        }
        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof String_ && !$node->hasAttribute('encoded')) {
            return $this->encodeString($node);
        }
        return null;
    }

    private function encodeString(String_ $node): Node
    {
        $value = $node->value;
        $encoded = base64_encode($this->xorString($value, $this->key));

        $encodedNode = new String_(base64_encode($value)); // Simpler for now or keep XOR
        // Wait, I should use the XOR logic as before but ensure it's not in constant expressions.

        $encoded = base64_encode($this->xorString($value, $this->key));

        $encodedNode = new String_($encoded);
        $encodedNode->setAttribute('encoded', true);
        $keyNode = new String_($this->key);
        $keyNode->setAttribute('encoded', true);

        return new FuncCall(new Name('_d'), [
            new Arg($encodedNode),
            new Arg($keyNode)
        ]);
    }

    private function xorString(string $data, string $key): string
    {
        $out = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $out .= $data[$i] ^ $key[$i % strlen($key)];
        }
        return $out;
    }
}
