# Submission: App Completion

Fill this in and include it with your solution.

## Time & tools

- Start time: 2026-08-19
- End time: 2026-08-19
- Tools/resources you used (docs, Stack Overflow, an AI assistant, etc., name them
   plainly; this is informational, not a trick question): Laravel documentation,
   PHPUnit tests, and GitHub Copilot.
- If you used an AI tool, roughly how did you use it (e.g. "asked it to explain
  `lockForUpdate`", "generated a first draft and rewrote most of it", "wrote it myself
  and asked it to review"): Read the repository requirements and used GitHub Copilot to
  implement and review the transaction and locking logic.

## Debrief

Answer these about the code you're submitting, not in the abstract.

1. Without running it, what does `test_rejects_transfer_from_a_suspended_wallet`
   expect your code to do, and which lines make that pass?
   **Answer:**

   1. The service loads and locks both wallet rows inside the transaction.
   2. It checks whether the source wallet is active.
   3. Because the source wallet is suspended, it throws a `WALLET_NOT_ACTIVE` exception.
   4. The transfer stops before either balance changes or a transfer record is created.

2. Why did you lock wallet rows in the order you chose? What would you observe in
   production if you'd locked them in request order (`from_wallet_id` then
   `to_wallet_id`) instead?
   I sort the two wallet IDs and always lock the lower ID first. This gives every
   transfer the same lock-acquisition order, regardless of its direction. If I locked
   wallets in request order, two opposite transfers could each lock one wallet and
   wait for the other. In production, that could cause deadlocks, lock timeouts, or
   failed transactions under load.

3. Suppose `WalletTransfer::create()` threw an exception right after you'd already
   debited the source wallet, but `DB::transaction()` wasn't there to wrap the whole
   operation. What state would the database be left in? None of the automated tests
   force this failure directly. Why not, and how would you test for it if you had
   more time?
   **Answer:**

   1. Without a transaction, the source wallet could be debited successfully.
   2. The transfer-record insert could then fail before the operation is complete.
   3. The destination update or transfer record could be missing, leaving the database
      inconsistent and the transferred money effectively unaccounted for.

   The existing tests cover validation and successful transfers, but do not force the
   transfer-record insert to fail. I would add a model event hook that throws during
   `WalletTransfer::create()`, then verify that both wallet balances and the transfer
   count remain unchanged. That would confirm that the database transaction rolls back
   the entire operation.
