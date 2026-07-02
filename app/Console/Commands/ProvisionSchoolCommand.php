<?php

namespace App\Console\Commands;

use App\Actions\ProvisionSchoolAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('school:provision
    {--name= : Full school name}
    {--abbreviation= : Short school abbreviation, for example FUTO}
    {--slug= : Optional domain slug. Defaults to the school name when omitted}
    {--admin-name= : Full name of the first school admin}
    {--admin-email= : Email address of the first school admin}
    {--admin-password= : Initial password for the first school admin}
    {--seed-sessions : Seed the default local-development academic sessions}')]
#[Description('Provision a school and its first school admin account')]
class ProvisionSchoolCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProvisionSchoolAction $provisionSchool): int
    {
        try {
            $result = $provisionSchool->execute([
                'name' => $this->requiredStringOption('name', 'School name'),
                'abbreviation' => $this->requiredStringOption('abbreviation', 'School abbreviation'),
                'slug' => $this->nullableStringOption('slug'),
                'admin_name' => $this->requiredStringOption('admin-name', 'School admin full name'),
                'admin_email' => $this->requiredStringOption('admin-email', 'School admin email'),
                'admin_password' => $this->requiredStringOption('admin-password', 'School admin password', secret: true),
                'seed_academic_sessions' => (bool) $this->option('seed-sessions'),
            ]);
        } catch (ValidationException $exception) {
            $this->error('School could not be provisioned.');

            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->line(" - {$message}");
                }
            }

            return self::FAILURE;
        }

        $this->info('School provisioned.');
        $this->line("School: {$result['school']->name} ({$result['school']->abbreviation})");
        $this->line("Slug: {$result['school']->slug}");
        $this->line("School admin: {$result['admin']->email}");

        if ($result['academic_sessions_seeded'] > 0) {
            $this->line("Academic sessions seeded: {$result['academic_sessions_seeded']}");
        }

        return self::SUCCESS;
    }

    private function requiredStringOption(string $option, string $question, bool $secret = false): string
    {
        $value = $this->nullableStringOption($option);

        if ($value !== null) {
            return $value;
        }

        if (! $this->input->isInteractive()) {
            throw ValidationException::withMessages([
                $option => "Pass --{$option}=... when running this command non-interactively.",
            ]);
        }

        $answer = $secret ? $this->secret($question) : $this->ask($question);

        return trim((string) $answer);
    }

    private function nullableStringOption(string $option): ?string
    {
        $value = $this->option($option);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
