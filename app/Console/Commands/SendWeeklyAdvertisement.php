<?php

namespace App\Console\Commands;

use App\Services\AdvertisementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-weekly-ad')]
#[Description('Post a weekly promotional message to groups/channels the bot has been added to')]
class SendWeeklyAdvertisement extends Command
{
    public function __construct(private AdvertisementService $service)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->service->sendWeeklyAds();
    }
}
