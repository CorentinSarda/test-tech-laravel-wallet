<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientBalance;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use Illuminate\Support\Facades\DB;
use App\Notifications\LowBalance;
use Throwable;

readonly class PerformWalletTransaction
{
    /**
     * @throws InsufficientBalance
     */
    public function execute(Wallet $wallet, WalletTransactionType $type, int $amount, string $reason, ?WalletTransfer $transfer = null, bool $force = false): WalletTransaction
    {
        if (! $force && $type === WalletTransactionType::DEBIT) {
            $this->ensureWalletHasEnoughFunds($wallet, $amount);
        }

        return DB::transaction(function () use ($wallet, $type, $amount, $reason, $transfer) {
            $transaction = $wallet->transactions()->create([
                'amount' => $amount,
                'type' => $type,
                'reason' => $reason,
                'transfer_id' => $transfer?->id,
            ]);

            $this->updateWallet($wallet, $type, $amount);

            if ($type === WalletTransactionType::DEBIT && $wallet->balance < 1000) {
                $this->sendLowBalanceNotification($wallet);
            }

            return $transaction;
        });
    }

    /**
     * @throws Throwable
     */
    protected function updateWallet(Wallet $wallet, WalletTransactionType $type, int $amount): void
    {
        if ($type === WalletTransactionType::CREDIT) {
            $wallet->increment('balance', $amount);
        } else {
            $wallet->decrement('balance', $amount);
        }
    }

    /**
     * @throws InsufficientBalance
     */
    protected function ensureWalletHasEnoughFunds(Wallet $wallet, int $amount): void
    {
        if ($wallet->balance < $amount) {
            throw new InsufficientBalance($wallet, $amount);
        }
    }

    protected function sendLowBalanceNotification(Wallet $wallet): void
    {
        $wallet->user->notify(new LowBalance());
    }
}
