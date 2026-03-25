# Phase 3 — Advanced Transformers

**Goal:** Implement control flow flattening and statement shuffling with deobfuscation resistance.

**Depends on:** Phase 2 (core transformers working)

## Tasks

### 3.1 Control Flow Flattener [DONE]
- [x] `src/Transformer/ControlFlowFlattener.php` — Converts structured control flow into goto-based dispatch
- [x] **Targets:** `if/else/elseif`, `for`, `while`, `do-while`, `switch`
- [x] **Deobfuscation resistance:**
  - [x] Opaque predicates — inject conditionals that always evaluate to true/false but are hard to prove statically
  - [x] Dead code branches — plausible-looking code guarded by false predicates
  - [x] Vary flattening strategy randomly per block
- [x] **Must not break:** `break`, `continue` with levels, `return` inside loops, `try/catch` blocks, `yield`/`yield from`
- [x] **Tests:** `tests/Unit/Transformer/ControlFlowFlattenerTest.php`
  - [x] Each control structure type flattened correctly
  - [x] Opaque predicates present in output
  - [x] `break`/`continue`/`return` semantics preserved
  - [x] Output executes identically to input

### 3.2 Statement Shuffler [DONE]
- [x] `src/Transformer/StatementShuffler.php` — Reorders statements with goto to preserve execution order
- [x] **Modes:** fixed chunk size or ratio-based chunking
- [x] Inserts labels and goto jumps between chunks
- [x] Randomized ordering
- [x] **Tests:** `tests/Unit/Transformer/StatementShufflerTest.php`
  - [x] Statement order in output differs from input
  - [x] Execution order preserved (goto chain correct)
  - [x] Output executes identically to input

### 3.3 Combined Integration Tests [DONE]
- [x] `tests/Integration/ObfuscationPipelineTest.php` — all 5 transformers enabled together
- [x] Test with fixture file
- [x] Verify: obfuscated output executes identically, no original identifiers visible, control flow is flattened, statements are shuffled

## Milestone
Full obfuscation pipeline functional for single files with all 5 transformation passes.

## Acceptance Criteria
- All transformer unit tests pass
- Full-pipeline integration tests pass on fixtures
- Obfuscated code is significantly harder to read than input
- No functional regressions — obfuscated code behavior is identical to original
- PHPStan level 8 clean
