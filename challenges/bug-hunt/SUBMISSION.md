# Submission: Bug Hunt

Fill this in and include it with your solution.

## Time & tools

- Start time: 2026-08-19
- End time: 2026-08-19
- Tools/resources you used (docs, Stack Overflow, an AI assistant, etc., name them
  plainly; this is informational, not a trick question):
- If you used an AI tool, roughly how did you use it (e.g. "asked it to explain a
  stack trace", "generated a first draft and rewrote most of it", "wrote it myself and
  asked it to review"): Read the repository requirements and used GitHub Copilot to
  implement and review the idempotent transaction and unique-constraint handling.

## Debrief

Answer these about the code you're submitting, not in the abstract.

1. Walk through what happens, step by step, when `fund()` is called twice in immediate
  succession with the same `reference`, using your fixed code. The first call validates
  the wallet and amount, starts a transaction, locks the wallet, finds no matching
  reference, increments the balance, creates the funding row, dispatches one receipt,
  and commits. The second call locks the wallet, finds the unique reference, returns
  the original funding and current balance, and does not increment or dispatch again.

2. The unique constraint on `reference` is a safety net for true concurrent requests,
   not just sequential retries. Given your implementation, is there a scenario where
   two near-simultaneous requests would still cause one of them to receive a raw 500
   error instead of a graceful "already processed" response? If so, what would you
  change to close that gap? A database error unrelated to the unique constraint, or a
  race where the winning transaction has not committed and the losing transaction
  cannot yet read it, can still fail. The catch currently retries by reading the
  committed reference and rethrows only if it is absent. In production I would add a
  bounded retry with backoff, inspect the driver-specific unique-violation code, and
  use an outbox/idempotency record strategy for stronger provider-facing guarantees.

3. Without running it, what does
   `test_rolls_back_the_wallet_balance_if_the_funding_record_fails_to_persist` actually
   simulate, and why does it use a model event hook instead of a real network or
  database failure? The `creating` hook throws after the balance increment but before
  the funding row is persisted. The transaction must roll the balance back and leave
  no row. An event hook is deterministic, local, fast, and targets exactly the failure
  boundary without depending on external services or fragile database fault injection.
