<?php

namespace Database\Seeders;

use App\Enums\Moscow;
use App\Enums\Status;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports the 42 deliverables from RAG_Tracker.xlsx into a dummy
 * "Sandbox / Imported deliverables" project owned by the user whose email
 * matches OWNER_EMAIL.
 *
 * Idempotent: re-running skips deliverables whose name already exists under
 * the imported project. Time logs are only seeded the first time a
 * deliverable is created (so re-running won't double-count the Actual column).
 *
 * The deliverable name carries its original spreadsheet ID as a prefix —
 *   "[CLN001] Clonallon Proposal"
 * — so you can scan by project code and re-assign each one to its real
 * project via the edit form whenever you're ready.
 *
 * Usage:
 *   php artisan db:seed --class=DummyDeliverablesSeeder
 */
class DummyDeliverablesSeeder extends Seeder
{
    private const OWNER_EMAIL = 'sepici@gmail.com';
    private const CLIENT_NAME = 'Sandbox';
    private const PROJECT_NAME = 'Imported deliverables';
    private const LOG_DATE = '2026-05-11'; // matches the spreadsheet's "Last Updated"

    /**
     * Raw rows from the Deliverables sheet of RAG_Tracker.xlsx.
     * Tuple order: [id, name, target_days, actual_days, moscow, rag].
     * null target/actual = blank in the source; null moscow/rag = blank.
     */
    private const ROWS = [
        ['AOC001', 'Build AoCA backend',                                                  2.0,  2.0,  null, null],
        ['AOC002', 'Test New AoCA features and apply fixes',                              1.0,  null, 'M',  'G'],
        ['AOC003', 'Apply The Change branding to AoCA',                                   1.0,  null, 'M',  'G'],
        ['CLN001', 'Clonallon Proposal',                                                  1.5,  1.0,  'M',  'A'],
        ['CLN002', 'Finalising Clonallon Bug Fixes',                                      3.0,  null, null, null],
        ['CLN003', 'Clonallon Staging Check and Sign Off',                                1.0,  null, 'M',  'A'],
        ['CLN004', 'Clonallon Client Testing Assistance After Deploy',                    1.0,  null, 'S',  'A'],
        ['HIT001', 'The HIIT Project Proposal',                                           0.5,  0.5,  null, null],
        ['HIT002', 'cPanel Explot Fix',                                                   null, 1.0,  null, null],
        ['HIT003', 'Get Paypal bug fixed',                                                1.0,  null, 'S',  'A'],
        ['HIT004', 'Move HIIT app to a new droplet without cPanel',                       1.0,  null, 'S',  'A'],
        ['SRV001', 'Backup sites and apps on DO droplets and close unnecessary droplets', 2.0,  null, 'W',  'G'],
        ['TCC001', 'The Change Carbon Legacy Score Interface',                            2.0,  1.0,  'M',  'G'],
        ['TCC002', 'Voluntary Market Carbon — back end holding',                          8.0,  5.0,  'M',  'A'],
        ['TCC003', 'EU ETS Market Carbon — back end holding',                             8.0,  2.5,  'M',  'A'],
        ['TCC004', 'UK ETS Market Carbon — back end holding',                             8.0,  2.0,  'M',  'A'],
        ['TCC005', 'Comparing Carbon Markets',                                            0.5,  0.5,  'M',  'G'],
        ['TCC006', 'CBAM India / UK Example',                                             6.0,  4.0,  'M',  'A'],
        ['TCC007', 'Import individual Shipment Upload',                                   null, null, 'S',  'G'],
        ['TCC008', 'Carbon Purchase on front end',                                        null, null, 'M',  'A'],
        ['TCC009', 'Migration of carbon to target ETS Client Account',                    null, null, 'M',  'G'],
        ['TCC010', 'Client Registration (Distributor / Importer / Producer)',             null, null, 'W',  'G'],
        ['TCC011', 'SECR / ESOS Equivalent',                                              null, null, 'S',  'G'],
        ['TCC012', 'Example Exotic Carbons',                                              9.0,  7.0,  'M',  'G'],
        ['TCC013', 'World Map',                                                           3.0,  1.0,  'S',  'G'],
        ['TCC014', 'ETS Prices',                                                          2.0,  null, 'M',  'A'],
        ['TCC015', 'EPD (Verdeo)',                                                        4.0,  2.0,  'M',  'G'],
        ['TCC016', 'Titles and Categorization',                                           2.5,  2.5,  null, null],
        ['TCC017', 'TCC Calculations and Displaying Correct Info',                        2.5,  2.0,  'M',  'G'],
        // Duplicate ID in the source — kept as-is; dedupe is on (project, name) not ID.
        ['TCC017', 'TCC Voluntary Market feature',                                        3.0,  null, 'S',  'A'],
        ['TCC018', 'GOs and REGOs',                                                       2.0,  null, 'S',  'A'],
        ['TCC019', 'Carbon Generation - RTOs',                                            null, null, 'W',  'G'],
        ['TCC020', 'Carbon Generation - Others',                                          null, null, 'W',  'G'],
        ['TCC021', 'Carbonfit Assesment',                                                 1.0,  null, 'S',  'A'],
        ['TCC022', 'Klimeo Code Assessment',                                              1.0,  null, 'S',  'A'],
        ['TCC023', 'Klimeo App UI/UX and Features Assessment',                            1.0,  null, 'S',  'A'],
        ['TCC024', 'EEX Registration - Broker (Fastest)',                                 2.0,  null, 'M',  'G'],
        ['TCC025', 'EEX Registration - Secondary Markets',                                4.0,  null, 'M',  'A'],
        ['TCC026', 'EEX Registration - Primary Markets',                                  6.0,  null, 'M',  'A'],
        ['TCC027', 'Tabs changes check and bug fixes',                                    2.0,  1.0,  'M',  'G'],
        ['TCC028', 'Complete didit integration and get document verification working',    3.0,  1.0,  'M',  'G'],
        ['JEA001', 'Build website',                                                       3.0,  1.0,  'S',  'G'],
    ];

    public function run(): void
    {
        $owner = User::where('email', self::OWNER_EMAIL)->first();
        if (! $owner) {
            $this->command?->error(sprintf(
                'User %s not found. Create that user first, or edit OWNER_EMAIL in %s.',
                self::OWNER_EMAIL,
                static::class,
            ));
            return;
        }

        DB::transaction(function () use ($owner) {
            $client = Client::firstOrCreate(
                ['legal_name' => self::CLIENT_NAME, 'owner_id' => $owner->id],
                ['notes' => 'Sandbox import bucket for the original spreadsheet — move deliverables to their real clients once we have them set up.'],
            );

            $project = Project::firstOrCreate(
                ['name' => self::PROJECT_NAME, 'owner_id' => $owner->id, 'client_id' => $client->id],
                ['description' => 'Seeded from RAG_Tracker.xlsx. Move deliverables out into their proper projects as you set them up.'],
            );

            $created = 0;
            $skipped = 0;
            $logged = 0;

            foreach (self::ROWS as [$id, $name, $targetDays, $actualDays, $moscow, $rag]) {
                $displayName = sprintf('[%s] %s', $id, $name);

                $existing = Deliverable::where('project_id', $project->id)
                    ->where('name', $displayName)
                    ->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }

                $deliverable = Deliverable::create([
                    'project_id' => $project->id,
                    'name' => $displayName,
                    'description' => 'Seeded from RAG_Tracker.xlsx.',
                    'target_hours' => $targetDays !== null ? $targetDays * 8.0 : 0.0,
                    'deadline' => null,
                    'status' => $this->mapStatus($rag),
                    'moscow' => $this->mapMoscow($moscow),
                ]);
                $created++;

                // Backfill the "Actual" column as a single seeded time_log so
                // the derived hours_spent shows the right cumulative number.
                if ($actualDays !== null && $actualDays > 0) {
                    TimeLog::create([
                        'owner_id' => $owner->id,
                        'log_date' => self::LOG_DATE,
                        'deliverable_id' => $deliverable->id,
                        'ad_hoc_name' => null,
                        'hours' => $actualDays * 8.0,
                        'notes' => 'Seeded from RAG_Tracker.xlsx (Actual column).',
                    ]);
                    $logged++;
                }
            }

            $this->command?->info(sprintf(
                'Seed complete: %d new, %d skipped (already present), %d time_logs created.',
                $created,
                $skipped,
                $logged,
            ));
        });
    }

    private function mapStatus(?string $rag): Status
    {
        return match ($rag) {
            'A' => Status::Amber,
            'G' => Status::Green,
            'B' => Status::Blocked,
            default => Status::Red, // blank or 'R'
        };
    }

    private function mapMoscow(?string $code): ?Moscow
    {
        return match ($code) {
            'M' => Moscow::Must,
            'S' => Moscow::Should,
            'C' => Moscow::Could,
            'W' => Moscow::Wont,
            default => null,
        };
    }
}
