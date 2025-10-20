<?php

namespace App\Jobs;

use App\Traits\GuzzleTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSMSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GuzzleTrait;

    protected $details;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array
     */
    public function backoff()
    {
        return [10, 20, 30];
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Fetch the latest ticket data from database to ensure e_ticket_url is available
        if (isset($this->details['order_id'])) {
            $ticketSale = \App\Models\TicketSale::where('order_id', $this->details['order_id'])->first();
            
            if ($ticketSale && $ticketSale->e_ticket_url) {
                // Update details with the latest e_ticket_url from database
                $this->details['e_ticket_url'] = $ticketSale->e_ticket_url;
                Log::info("SendSMSJob: Retrieved e_ticket_url from database for Order ID: " . $this->details['order_id']);
            } else {
                Log::warning("SendSMSJob: e_ticket_url not yet available for Order ID: " . $this->details['order_id']);
            }
        }

        $MSISDN = $this->details["cust_mobile"];
        $SRC = config('sms.src');
        $MESSAGE = (urldecode($this->details["message"]));
        $AUTH = config('sms.auth');

        $response = $this->guzzleRequest("https://send.lk/sms/send.php?to={$MSISDN}&from={$SRC}&message={$MESSAGE}&token={$AUTH}");

        Log::info("Sending Ticket Booking SMS to: " . $MSISDN . " Message: " . $MESSAGE);

        if ($response) {
            Log::info("Ticket Booking SMS Sent: " . (string) $response->getBody());
        } else {
            Log::error("Ticket Booking SMS Error");
        }
    }
}
