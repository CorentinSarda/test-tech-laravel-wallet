<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PerformWalletTransfer;
use App\Exceptions\InsufficientBalance;
use App\Http\Requests\SendMoneyRequest;

class SendMoneyController
{
    public function __invoke(SendMoneyRequest $request, PerformWalletTransfer $performWalletTransfer)
    {
        $recipient = $request->getRecipient();
        $recurring = $request->getRecurring();
        $interval = $request->getInterval();

        try {
            $performWalletTransfer->execute(
                sender: $request->user(),
                recipient: $recipient,
                amount: $request->getAmountInCents(),
                reason: $request->input('reason'),
                recurring: $recurring,
                startDate: $request->input('start_date', null),
                endDate: $request->input('end_date', null),
                interval: $interval,
            );

            return redirect()->back()
                ->with('money-sent-status', 'success')
                ->with('money-sent-recipient-name', $recipient->name)
                ->with('money-sent-amount', $request->getAmountInCents());
        } catch (InsufficientBalance $exception) {
            return redirect()->back()->with('money-sent-status', 'insufficient-balance')
                ->with('money-sent-recipient-name', $recipient->name)
                ->with('money-sent-amount', $request->getAmountInCents());
        }
    }
}
