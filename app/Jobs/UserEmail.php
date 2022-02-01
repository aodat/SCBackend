<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\MailResetPasswordNotification as MailResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $user, $type, $token;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, $type = 'verification', $token = null)
    {
        $this->user = $user;
        $this->type = $type;
        $this->token = $token;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->type == 'verification') {
            $this->user->sendEmailVerificationNotification();
        } else {
            $this->user->notify(new MailResetPasswordNotification($this->token));
        }

    }
}
