<?php

namespace App\Console\Commands;

use App\Services\ColdStorage\ActionAlertMailService;
use Illuminate\Console\Command;

class SendColdStorageActionAlertEmailsCommand extends Command
{
    protected $signature = 'cold-storage:send-action-alert-emails
                            {--merchant= : Merchant UUID (optional; sends for all enabled merchants if omitted)}';

    protected $description = 'Send cold storage action-alert digest emails to the dedicated recipient list';

    public function handle(ActionAlertMailService $mailer): int
    {
        $merchantId = $this->option('merchant');

        $result = $mailer->sendDueDigests($merchantId ? (string) $merchantId : null);

        $this->newLine();
        $this->info('=== Cold storage action alert emails ===');
        $this->line('Mailer: '.config('mail.default'));
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Sent', $result['sent']],
                ['Skipped', $result['skipped']],
                ['Not sent (error)', $result['failed']],
            ]
        );

        $this->newLine();
        $this->info('Details:');

        foreach ($result['details'] as $line) {
            if (str_starts_with($line, 'SENT')) {
                $this->line('<fg=green>'.$line.'</>');
            } elseif (str_starts_with($line, 'NOT SENT')) {
                $this->line('<fg=red>'.$line.'</>');
            } else {
                $this->line('<fg=yellow>'.$line.'</>');
            }
        }

        if ($result['details'] === []) {
            $this->comment('No enabled alert-email settings found. Configure recipients under Cold Storage → Alert emails.');
        }

        return self::SUCCESS;
    }
}
