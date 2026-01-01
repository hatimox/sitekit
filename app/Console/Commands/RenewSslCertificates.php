<?php

namespace App\Console\Commands;

use App\Models\SslCertificate;
use App\Notifications\SslExpiringWarning;
use Illuminate\Console\Command;

class RenewSslCertificates extends Command
{
    protected $signature = 'ssl:renew {--dry-run : Only show what would be renewed}';

    protected $description = 'Renew SSL certificates that are expiring soon';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find certificates expiring within 30 days
        $expiringCerts = SslCertificate::where('type', SslCertificate::TYPE_LETSENCRYPT)
            ->where('status', SslCertificate::STATUS_ACTIVE)
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->get();

        if ($expiringCerts->isEmpty()) {
            $this->info('No certificates need renewal.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiringCerts->count()} certificate(s) expiring soon:");

        foreach ($expiringCerts as $cert) {
            $days = $cert->getDaysUntilExpiry();
            $this->line("  - {$cert->domain} (expires in {$days} days)");

            if (!$dryRun) {
                // Dispatch renewal job
                $cert->dispatchRenew();
                $this->info("    → Renewal job queued");

                // Send warning notification if expiring within 7 days
                if ($days <= 7) {
                    $owner = $cert->webApp->team->owner;
                    if ($owner) {
                        $owner->notify(new SslExpiringWarning($cert));
                    }
                }
            }
        }

        // Also check for expired certificates
        $expiredCerts = SslCertificate::where('status', SslCertificate::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiredCerts->isNotEmpty()) {
            $this->newLine();
            $this->error("Found {$expiredCerts->count()} EXPIRED certificate(s):");

            foreach ($expiredCerts as $cert) {
                $this->line("  - {$cert->domain} (expired " . $cert->expires_at->diffForHumans() . ")");

                if (!$dryRun) {
                    $cert->update(['status' => SslCertificate::STATUS_EXPIRED]);

                    $owner = $cert->webApp->team->owner;
                    if ($owner) {
                        $owner->notify(new SslExpiringWarning($cert));
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
