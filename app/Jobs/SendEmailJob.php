<?php

namespace App\Jobs;

use App\Mail\TicketBookingConfirmationMail;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array
     */
    public function backoff()
    {
        return [300, 600, 900];
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
                Log::info("SendEmailJob: Retrieved e_ticket_url from database for Order ID: " . $this->details['order_id']);
            } else {
                Log::warning("SendEmailJob: e_ticket_url not yet available for Order ID: " . $this->details['order_id']);
            }
        }
        
        $email = new TicketBookingConfirmationMail($this->details);
        if(isset($this->details['cc_email'])) {
            Mail::to([$this->details['email']])->cc([$this->details['cc_email']])->send($email);
        } else {
            Mail::to($this->details['email'])->send($email);
        }
    }
}
