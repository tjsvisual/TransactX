# Submission: App Completion

Fill this in and include it with your solution.

## Time & tools

- Start time: 2026-08-19
- End time: 2026-08-19
- Tools/resources you used (docs, Stack Overflow, an AI assistant, etc., name them
  plainly; this is informational, not a trick question):
- If you used an AI tool, roughly how did you use it (e.g. "asked it to explain
   `lockForUpdate`", "generated a first draft and rewrote most of it", "wrote it myself
   and asked it to review"): Read the repository requirements and used GitHub Copilot to
   implement and review the transaction and locking logic.

## Debrief

Answer these about the code you're submitting, not in the abstract.

1. Without running it, what does `test_rejects_transfer_from_a_suspended_wallet`
   expect your code to do, and which lines make that pass? The service locks and loads
   both wallets, then checks `fromWallet->isActive()`. A suspended source throws
   `WALLET_NOT_ACTIVE` before any balance update or transfer record is created.

2. Why did you lock wallet rows in the order you chose? What would you observe in
   production if you'd locked them in request order (`from_wallet_id` then
   `to_wallet_id`) instead? I sort both IDs numerically and lock the lower ID first.
   Every transfer therefore acquires the pair's locks in the same order. Request-order
   locking could deadlock opposite transfers: each transaction could hold one wallet
   lock while waiting for the other, causing lock waits, retries, or a deadlock error.

3. Suppose `WalletTransfer::create()` threw an exception right after you'd already
   debited the source wallet, but `DB::transaction()` wasn't there to wrap the whole
   operation. What state would the database be left in? None of the automated tests
   force this failure directly. Why not, and how would you test for it if you had
   more time? The source would be debited while the destination and transfer ledger
   could be inconsistent, leaving money effectively missing. The current tests focus
   on the stated validation and happy-path behavior. I would attach a model event that
   throws during transfer creation, assert the exception, and verify both balances and
   the transfer count are unchanged.
