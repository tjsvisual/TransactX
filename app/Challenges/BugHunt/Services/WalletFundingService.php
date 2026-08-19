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
        $wallet = Wallet::find($input->walletId);

        if (! $wallet) {
            throw new DomainException('WALLET_NOT_FOUND', 'Wallet was not found.', 404);
        }

        if ($input->amount <= 0) {
            throw new DomainException('INVALID_AMOUNT', 'Funding amount must be positive.');
        }

        try {
            return DB::transaction(function () use ($input): FundingResult {
                $wallet = Wallet::query()->whereKey($input->walletId)->lockForUpdate()->first();

                if (! $wallet) {
                    throw new DomainException('WALLET_NOT_FOUND', 'Wallet was not found.', 404);
                }

                $existing = WalletFunding::query()
                    ->where('reference', $input->reference)
                    ->with('wallet')
                    ->first();

                if ($existing) {
                    return FundingResult::fromModels($existing, $existing->wallet->fresh());
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
}
