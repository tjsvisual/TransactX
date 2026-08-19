<?php

namespace App\Challenges\BugHunt\Services;

use App\Challenges\BugHunt\DataTransferObjects\FundingData;
use App\Challenges\BugHunt\DataTransferObjects\FundingResult;
use App\Challenges\BugHunt\Jobs\SendFundingReceipt;
use App\Challenges\BugHunt\Models\WalletFunding;
use App\Challenges\Shared\Exceptions\DomainException;
use App\Challenges\Shared\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WalletFundingService
{
    public function fund(FundingData $input): FundingResult
    {
        if ($input->amount <= 0) {
            throw new DomainException('INVALID_AMOUNT', 'Funding amount must be positive.');
        }

        try {
            return DB::transaction(function () use ($input): FundingResult {
                $existing = WalletFunding::query()
                    ->where('reference', $input->reference)
                    ->with('wallet')
                    ->first();

                if ($existing) {
                    return FundingResult::fromModels($existing, $existing->wallet->fresh());
                }

                $wallet = Wallet::query()->whereKey($input->walletId)->lockForUpdate()->first();

                if (! $wallet) {
                    throw new DomainException('WALLET_NOT_FOUND', 'Wallet was not found.', 404);
                }

                $wallet->increment('balance', $input->amount);
                $funding = WalletFunding::create([
                    'wallet_id' => $wallet->id,
                    'amount' => $input->amount,
                    'reference' => $input->reference,
                ]);

                SendFundingReceipt::dispatch($wallet->id, $funding->id);

                return FundingResult::fromModels($funding, $wallet->fresh());
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateReferenceException($exception)) {
                throw $exception;
            }

            $existing = WalletFunding::query()
                ->where('reference', $input->reference)
                ->with('wallet')
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return FundingResult::fromModels($existing, $existing->wallet->fresh());
        }
    }

    private function isDuplicateReferenceException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[0] ?? null) === '23505'
            || (($errorInfo[0] ?? null) === '23000' && in_array($errorInfo[1] ?? null, [19, 1062], true));
    }
}
