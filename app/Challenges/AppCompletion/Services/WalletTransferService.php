<?php

namespace App\Challenges\AppCompletion\Services;

use App\Challenges\AppCompletion\DataTransferObjects\TransferData;
use App\Challenges\AppCompletion\DataTransferObjects\TransferResult;
use App\Challenges\AppCompletion\Models\WalletTransfer;
use App\Challenges\Shared\Exceptions\DomainException;
use App\Challenges\Shared\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletTransferService
{
    public function transfer(TransferData $input): TransferResult
    {
        if ($input->amount <= 0) {
            throw new DomainException('INVALID_AMOUNT', 'Transfer amount must be positive.');
        }

        if ($input->fromWalletId === $input->toWalletId) {
            throw new DomainException(
                'SAME_WALLET_TRANSFER',
                'Source and destination wallets must be different.',
            );
        }

        return DB::transaction(function () use ($input): TransferResult {
            $walletIds = [$input->fromWalletId, $input->toWalletId];
            sort($walletIds, SORT_NUMERIC);
            $wallets = [];

            foreach ($walletIds as $walletId) {
                $wallet = Wallet::query()->whereKey($walletId)->lockForUpdate()->first();

                if (! $wallet) {
                    throw new DomainException('WALLET_NOT_FOUND', 'Wallet was not found.', 404);
                }

                $wallets[$walletId] = $wallet;
            }

            $fromWallet = $wallets[$input->fromWalletId];
            $toWallet = $wallets[$input->toWalletId];

            if (! $fromWallet->isActive()) {
                throw new DomainException('WALLET_NOT_ACTIVE', 'Source wallet is not active.');
            }

            if ($fromWallet->balance < $input->amount) {
                throw new DomainException('INSUFFICIENT_BALANCE', 'Wallet balance is insufficient.');
            }

            $fromWallet->balance -= $input->amount;
            $toWallet->balance += $input->amount;
            $fromWallet->save();
            $toWallet->save();

            $transfer = WalletTransfer::create([
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'amount' => $input->amount,
            ]);

            return TransferResult::fromModels($transfer, $fromWallet, $toWallet);
        });
    }
}
