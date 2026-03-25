# Deobfuscation Resistance

## The Problem

yakpro-po's obfuscation is largely reversible. Automated tools (decoder.code.blog, deobfuscation.com) claim ~99.5% restoration rates. The main weaknesses:

1. **Deterministic goto patterns** — Control flow flattening produces predictable goto/label structures that pattern-matching can undo.
2. **Inline decryption** — String encryption includes the decryption function in the output, making it trivially reversible.
3. **No fake complexity** — All code in the output is real; there's nothing to confuse automated analysis.

## Our Countermeasures

### 1. Opaque Predicates

An opaque predicate is a conditional expression whose outcome is known at obfuscation time but is difficult for an automated deobfuscator to determine.

**Simple examples:**
```php
// Always true (but requires symbolic evaluation to prove)
if (((42 * 42) % 7) === 0) {
    // real code
} else {
    // dead code (never executed)
}

// Always false
if (PHP_INT_SIZE < 0) {
    // dead code
}
```

**Stronger examples:**
```php
// Uses properties of integer arithmetic
$opaque = ($x * ($x + 1)) % 2; // Always 0 for any integer $x
if ($opaque === 0) { /* real */ } else { /* dead */ }
```

The key is variety — use many different predicate forms so pattern-matching deobfuscators can't just strip them all with one rule.

### 2. Dead Code Injection

Insert plausible-looking code blocks that never execute (guarded by opaque predicates). The dead code should:
- Use similar variable names and patterns as real code
- Include function calls, loops, and conditionals
- Reference the same scrambled identifiers (so it's not obvious which code is real)

### 3. Per-File String Encryption Keys

Instead of one global key, each file gets a unique random key. The decryption routine is also obfuscated and varies per file (different variable names, slightly different algorithm variants).

### 4. Control Flow Randomization

Instead of deterministic goto replacement:
- Randomly choose between different flattening strategies for each block
- Vary the state variable names and dispatch table structure
- Insert no-op state transitions that loop back without doing anything

### 5. Identifier Pollution

Generate additional fake variables and functions that are assigned/called but whose results are never used in the actual output path. This increases the noise-to-signal ratio for anyone reading the code.

## What We Cannot Defend Against

Be honest about limitations:
- A sufficiently motivated reverse engineer with dynamic analysis (running the code and tracing execution) can always understand what code does.
- The obfuscated code must be valid PHP, so the logic is always there — we're just making it harder to extract.
- Obfuscation is not encryption. It raises the bar, it doesn't make reversal impossible.

## Measuring Resistance

We should periodically test our output against:
1. Manual reading (how long does it take a developer to understand a simple function?)
2. Known deobfuscation tools (do they produce useful output?)
3. Static analysis tools (can PHPStan/Psalm infer types from obfuscated code?)

These are qualitative checks, not automated tests, but they inform whether our techniques are effective.
