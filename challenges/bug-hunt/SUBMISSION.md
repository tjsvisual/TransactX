# Submission: Bug Hunt

Fill this in and include it with your solution.

## Time & tools

- Start time: 2026-08-19
- End time: 2026-08-19
- Tools/resources you used (docs, Stack Overflow, an AI assistant, etc., name them
  plainly; this is informational, not a trick question): Laravel documentation,
  PHPUnit tests, and GitHub Copilot.
- If you used an AI tool, roughly how did you use it (e.g. "asked it to explain a
  stack trace", "generated a first draft and rewrote most of it", "wrote it myself and
  asked it to review"): Read the repository requirements and used GitHub Copilot to
  implement and review the idempotent transaction and unique-constraint handling.

## Debrief

Answer these about the code you're submitting, not in the abstract.

1. Walk through what happens, step by step, when `fund()` is called twice in immediate
   succession with the same `reference`, using your fixed code.
  **Answer:**

  **First request**

  1. The service validates that the funding amount is positive.
  2. It starts a database transaction.
  3. It checks whether the reference has already been recorded. No matching record is
    found.
  4. It locks the requested wallet row.
  5. It increments the wallet balance and creates the funding record.
  6. It dispatches one receipt job and commits the transaction.

  **Second request**

  1. The service validates the amount and starts another transaction.
  2. It finds the existing funding record for the same reference.
  3. It returns that original funding and balance immediately.
  4. It does not change the balance, create another record, or dispatch another receipt.

2. The unique constraint on `reference` is a safety net for true concurrent requests,
   not just sequential retries. Given your implementation, is there a scenario where
   two near-simultaneous requests would still cause one of them to receive a raw 500
   error instead of a graceful "already processed" response? If so, what would you
  change to close that gap?
  **Answer:**
  The unique constraint protects the database if two requests pass the initial lookup
  at the same time. The request that loses the insert race receives a unique-key
  exception, and the service catches that specific type of database error before
  returning the already-created funding record. Other database errors are not hidden
  and are rethrown normally.

  There is still a small timing window if the winning transaction has not committed
  by the time the losing request tries to read the record. In production, I would add
  a bounded retry with a short backoff, and identify the unique-key violation using
  the database driver's error code. I would also consider an explicit idempotency or
  outbox record for stronger guarantees around webhook processing.

3. Without running it, what does
   `test_rolls_back_the_wallet_balance_if_the_funding_record_fails_to_persist` actually
  simulate, and why does it use a model event hook instead of a real network or
  database failure?
  **Answer:**

  1. The test registers a `creating` event hook that throws an exception for a specific
    reference.
  2. The service increments the balance, then the event causes the funding insert to
    fail.
  3. The database transaction rolls back the balance change and leaves no funding row.

  A model event is used because it is deterministic, fast, and tests the exact failure
  point without depending on a real network outage or fragile database fault
  injection.
